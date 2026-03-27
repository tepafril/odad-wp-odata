# Swagger / OpenAPI 3.0 Documentation — Implementation Plan

## Overview

Automatically generate an OpenAPI 3.0 specification from the live schema registry
and serve Swagger UI inside the WordPress admin. No static files to maintain —
the spec is always in sync with whatever entity sets are registered.

**Spec endpoint:** `GET /wp-json/odata/v4/openapi.json`
**Swagger UI page:** WordPress admin → WP-OData Suite → API Docs
**Cached:** Yes (WP transient, same bust triggers as `$metadata`)

---

## What gets documented

| Endpoint pattern | Methods |
|---|---|
| `/odata/v4/` | GET (service document) |
| `/odata/v4/$metadata` | GET (CSDL XML + JSON) |
| `/odata/v4/{EntitySet}` | GET (collection), POST (create) |
| `/odata/v4/{EntitySet}({key})` | GET, PATCH, PUT, DELETE |
| `/odata/v4/$batch` | POST |
| `/wp-hr/v1/auth/login` | POST |
| `/wp-hr/v1/auth/refresh` | POST |
| `/wp-hr/v1/auth/logout` | POST |

All OData query parameters (`$filter`, `$select`, `$orderby`, `$top`, `$skip`,
`$count`, `$expand`, `$search`) are documented as reusable parameter components.

Security schemes: **Bearer JWT** and **HTTP Basic**.

---

## OData → OpenAPI type mapping

| OData type | OpenAPI type | format |
|---|---|---|
| `Edm.Int32` | `integer` | `int32` |
| `Edm.Int64` | `integer` | `int64` |
| `Edm.String` | `string` | — |
| `Edm.Boolean` | `boolean` | — |
| `Edm.Decimal` | `number` | — |
| `Edm.Double` | `number` | `double` |
| `Edm.DateTimeOffset` | `string` | `date-time` |
| `Edm.Date` | `string` | `date` |

---

## Architecture

```
ODAD_Schema_Registry
        │
        ▼
ODAD_OpenAPI_Generator ──→ OpenAPI 3.0 array
        │
        ▼
ODAD_OpenAPI_Cache (transient: 'ODAD_openapi_json')
        │
        ├──→ GET /odata/v4/openapi.json   (ODAD_Router)
        │
        └──→ Admin Swagger UI page        (ODAD_Admin_API_Docs)
                  └── Swagger UI assets (bundled in assets/swagger-ui/)
```

The cache is busted by `ODAD_Event_Schema_Changed` — same event that busts
`$metadata`. No extra bust logic needed.

---

## Phase 1 — OpenAPI Generator

### Task 1.1 — Core generator

**File:** `src/openapi/class-odad-openapi-generator.php`

```php
class ODAD_OpenAPI_Generator {

    public function __construct(
        private ODAD_Schema_Registry $registry,
    ) {}

    /**
     * Build and return the full OpenAPI 3.0 spec as a PHP array.
     * Caller is responsible for JSON-encoding.
     */
    public function generate(): array;

    // ── Private builders ──────────────────────────────────────────────────

    /** Top-level openapi / info / servers / security blocks. */
    private function build_info(): array;

    /** All reusable components: schemas, parameters, responses, securitySchemes. */
    private function build_components(): array;

    /** Generate schema object for one entity set (used in components/schemas). */
    private function build_entity_schema( string $entity_set, array $definition ): array;

    /** Generate write schema (exclude read_only properties). */
    private function build_write_schema( string $entity_set, array $definition ): array;

    /** Collection response wrapper: { "@odata.context", "@odata.count", "value": [...] } */
    private function build_collection_response_schema( string $entity_set ): array;

    /** All paths for a single entity set (collection + entity routes). */
    private function build_entity_paths( string $entity_set, array $definition ): array;

    /** Auth endpoint paths (/wp-hr/v1/auth/login, /refresh, /logout). */
    private function build_auth_paths(): array;

    /** Service document + $metadata + $batch paths. */
    private function build_system_paths(): array;

    /** Reusable OData query parameter components. */
    private function build_odata_parameters(): array;

    /** Map a single OData Edm type string to an OpenAPI schema fragment. */
    private function map_type( string $edm_type ): array;
}
```

**`generate()` output structure:**

```php
[
    'openapi' => '3.0.3',
    'info'    => [
        'title'       => 'WP-OData Suite API',
        'description' => 'OData v4.01 REST API for WordPress data.',
        'version'     => ODAD_VERSION,
    ],
    'servers' => [
        [ 'url' => rest_url('odata/v4'), 'description' => 'OData endpoint' ],
    ],
    'security' => [
        [ 'BearerAuth' => [] ],
        [ 'BasicAuth'  => [] ],
    ],
    'paths'      => [...],   // built by build_entity_paths + build_auth_paths + build_system_paths
    'components' => [...],   // schemas, parameters, responses, securitySchemes
]
```

**`build_components()` must include:**

```php
'securitySchemes' => [
    'BearerAuth' => [ 'type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT' ],
    'BasicAuth'  => [ 'type' => 'http', 'scheme' => 'basic' ],
],
'parameters' => [
    'filter'  => [ 'name' => '$filter',  'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'OData $filter expression. Example: Status eq \'publish\'' ],
    'select'  => [ 'name' => '$select',  'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Comma-separated list of properties to return.' ],
    'orderby' => [ 'name' => '$orderby', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Sort expression. Example: CreatedAt desc' ],
    'top'     => [ 'name' => '$top',     'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 100] ],
    'skip'    => [ 'name' => '$skip',    'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 0, 'default' => 0] ],
    'count'   => [ 'name' => '$count',   'in' => 'query', 'schema' => ['type' => 'boolean'], 'description' => 'Include total count in response as @odata.count.' ],
    'expand'  => [ 'name' => '$expand',  'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Navigation properties to expand. Example: Author' ],
    'search'  => [ 'name' => '$search',  'in' => 'query', 'schema' => ['type' => 'string'] ],
],
'responses' => [
    '400' => [ 'description' => 'Bad Request',   'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ODataError']]] ],
    '401' => [ 'description' => 'Unauthorized',  ... ],
    '403' => [ 'description' => 'Forbidden',     ... ],
    '404' => [ 'description' => 'Not Found',     ... ],
    '500' => [ 'description' => 'Server Error',  ... ],
],
'schemas' => [
    'ODataError' => [
        'type'       => 'object',
        'properties' => [
            'error' => [
                'type'       => 'object',
                'properties' => [
                    'code'    => [ 'type' => 'string' ],
                    'message' => [ 'type' => 'string' ],
                ],
            ],
        ],
    ],
    // + one schema per entity set, generated dynamically
],
```

**`build_entity_paths()` for one entity set (e.g. `Posts`):**

```php
'/Posts' => [
    'get' => [
        'tags'        => ['Posts'],
        'summary'     => 'List Posts',
        'operationId' => 'listPosts',
        'parameters'  => [
            ['$ref' => '#/components/parameters/filter'],
            ['$ref' => '#/components/parameters/select'],
            ['$ref' => '#/components/parameters/orderby'],
            ['$ref' => '#/components/parameters/top'],
            ['$ref' => '#/components/parameters/skip'],
            ['$ref' => '#/components/parameters/count'],
            ['$ref' => '#/components/parameters/expand'],
            ['$ref' => '#/components/parameters/search'],
        ],
        'responses' => [
            '200' => [
                'description' => 'Collection of Posts',
                'content' => [
                    'application/json' => [
                        'schema' => [ '$ref' => '#/components/schemas/PostsCollection' ],
                    ],
                ],
            ],
            '401' => [ '$ref' => '#/components/responses/401' ],
            '403' => [ '$ref' => '#/components/responses/403' ],
        ],
    ],
    'post' => [
        'tags'        => ['Posts'],
        'summary'     => 'Create Post',
        'operationId' => 'createPost',
        'requestBody' => [
            'required' => true,
            'content'  => [
                'application/json' => [
                    'schema' => [ '$ref' => '#/components/schemas/PostsWrite' ],
                ],
            ],
        ],
        'responses' => [
            '201' => [ 'description' => 'Created', 'content' => [...] ],
            '400' => [ '$ref' => '#/components/responses/400' ],
            '403' => [ '$ref' => '#/components/responses/403' ],
        ],
    ],
],
'/Posts({ID})' => [
    'parameters' => [
        [ 'name' => 'ID', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer'] ],
    ],
    'get'    => [ 'tags' => ['Posts'], 'summary' => 'Get Post by ID', ... ],
    'patch'  => [ 'tags' => ['Posts'], 'summary' => 'Update Post', ... ],
    'put'    => [ 'tags' => ['Posts'], 'summary' => 'Replace Post', ... ],
    'delete' => [ 'tags' => ['Posts'], 'summary' => 'Delete Post', ... ],
],
```

**Key property for read_only exclusion in write schema:**
Properties with `'read_only' => true` in the definition are excluded from
`PostsWrite` schema but included in `Posts` (read) schema.

---

### Task 1.2 — OpenAPI cache

**File:** `src/openapi/class-odad-openapi-cache.php`

```php
class ODAD_OpenAPI_Cache {
    private const TRANSIENT = 'ODAD_openapi_json';

    public function get(): ?string;           // returns cached JSON string or null
    public function set( string $json ): void; // store_transient with same TTL as metadata
    public function bust(): void;              // delete_transient
}
```

Wire bust into the existing `ODAD_Subscriber_Schema_Changed`:

```php
// In class-odad-subscriber-schema-changed.php — add openapi cache bust
public function handle( ODAD_Event $event ): void {
    $this->metadata_cache->bust();
    $this->openapi_cache->bust();  // ADD THIS
}
```

Update `ODAD_Subscriber_Schema_Changed` constructor to accept optional `ODAD_OpenAPI_Cache`.
Update bootstrapper to inject it.

---

### Task 1.3 — Router route

Add to `ODAD_Router::register_routes()`:

```php
register_rest_route( 'odata/v4', '/openapi.json', [
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => [ $this, 'handle_openapi' ],
    'permission_callback' => [ $this, 'check_public_access' ],
] );
```

```php
public function handle_openapi( WP_REST_Request $request ): WP_REST_Response {
    $cached = $this->openapi_cache->get();
    if ( $cached !== null ) {
        return new WP_REST_Response( json_decode( $cached, true ), 200 );
    }
    $spec = $this->openapi_generator->generate();
    $json = wp_json_encode( $spec, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    $this->openapi_cache->set( $json );
    return new WP_REST_Response( $spec, 200 );
}
```

Update `ODAD_Router` constructor to accept `ODAD_OpenAPI_Generator` and `ODAD_OpenAPI_Cache`.
Update bootstrapper accordingly.

---

## Phase 2 — Swagger UI Admin Page

### Task 2.1 — Swagger UI assets

Download Swagger UI distribution files and place at:

```
assets/swagger-ui/
├── swagger-ui-bundle.js        (~1.1 MB)
├── swagger-ui-bundle.js.map
├── swagger-ui.css
└── favicon-32x32.png
```

**Where to get them:**
Download the latest `swagger-ui-dist` release from:
`https://github.com/swagger-api/swagger-ui/releases`

Extract only: `swagger-ui-bundle.js`, `swagger-ui.css`

No `swagger-ui-standalone-preset.js` needed (we embed the spec URL directly).

### Task 2.2 — Admin page

**File:** `src/admin/class-odad-admin-api-docs.php`

Add submenu to existing `ODAD_Admin::register_menu()`:

```php
add_submenu_page(
    'odad-dashboard',
    __( 'API Docs', 'wp-odata-suite' ),
    __( 'API Docs', 'wp-odata-suite' ),
    'manage_options',
    'odad-api-docs',
    fn() => ODAD_container()->get( ODAD_Admin_API_Docs::class )->render()
);
```

**`render()` implementation:**

```php
public function render(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    $spec_url = rest_url( 'odata/v4/openapi.json' );
    wp_enqueue_style(  'odad-swagger-ui', ODAD_PLUGIN_URL . 'assets/swagger-ui/swagger-ui.css', [], ODAD_VERSION );
    wp_enqueue_script( 'odad-swagger-ui', ODAD_PLUGIN_URL . 'assets/swagger-ui/swagger-ui-bundle.js', [], ODAD_VERSION, true );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'API Documentation', 'wp-odata-suite' ); ?></h1>
        <p>
            <?php esc_html_e( 'Live OpenAPI spec:', 'wp-odata-suite' ); ?>
            <a href="<?php echo esc_url( $spec_url ); ?>" target="_blank">
                <?php echo esc_html( $spec_url ); ?>
            </a>
        </p>
        <div id="odad-swagger-ui-container"></div>
        <script>
        SwaggerUIBundle({
            url:              <?php echo wp_json_encode( $spec_url ); ?>,
            dom_id:           '#odad-swagger-ui-container',
            presets:          [ SwaggerUIBundle.presets.apis ],
            layout:           'BaseLayout',
            deepLinking:      true,
            displayRequestDuration: true,
            requestInterceptor: function(req) {
                // Pass WP nonce for cookie-auth requests from admin
                req.headers['X-WP-Nonce'] = <?php echo wp_json_encode( wp_create_nonce('wp_rest') ); ?>;
                return req;
            },
        });
        </script>
    </div>
    <?php
}
```

### Task 2.3 — Bootstrapper updates

Add to `ODAD_Bootstrapper::build()`:

```php
$container->singleton( ODAD_OpenAPI_Cache::class, fn() => new ODAD_OpenAPI_Cache() );

$container->singleton( ODAD_OpenAPI_Generator::class, fn( ODAD_Container $c ) => new ODAD_OpenAPI_Generator(
    $c->get( ODAD_Schema_Registry::class ),
) );

$container->singleton( ODAD_Admin_API_Docs::class, fn() => new ODAD_Admin_API_Docs() );
```

Update `ODAD_Router` singleton to add the two new constructor args:
```php
openapi_generator: $c->get( ODAD_OpenAPI_Generator::class ),
openapi_cache:     $c->get( ODAD_OpenAPI_Cache::class ),
```

Update `ODAD_Subscriber_Schema_Changed` singleton to inject `ODAD_OpenAPI_Cache`.

---

## Phase 3 — Tests

### Task 3.1 — Unit tests

**File:** `tests/unit/openapi/OpenAPIGeneratorTest.php`

```php
class OpenAPIGeneratorTest extends PHPUnit\Framework\TestCase {

    private ODAD_OpenAPI_Generator $generator;

    protected function setUp(): void {
        $registry = new ODAD_Schema_Registry();
        $registry->register( 'Posts', [
            'key'        => 'ID',
            'properties' => [
                'ID'    => [ 'column' => 'ID',         'type' => 'Edm.Int64',  'read_only' => true ],
                'Title' => [ 'column' => 'post_title', 'type' => 'Edm.String' ],
            ],
        ]);
        $this->generator = new ODAD_OpenAPI_Generator( $registry );
    }

    public function test_generates_valid_openapi_version(): void;
        // $spec['openapi'] === '3.0.3'

    public function test_contains_entity_set_paths(): void;
        // array_key_exists('/Posts', $spec['paths'])
        // array_key_exists('/Posts({ID})', $spec['paths'])

    public function test_collection_path_has_odata_parameters(): void;
        // GET /Posts parameters includes $ref to filter, select, orderby, top, skip

    public function test_entity_schema_generated(): void;
        // $spec['components']['schemas']['Posts'] has 'ID' and 'Title' properties

    public function test_write_schema_excludes_read_only(): void;
        // $spec['components']['schemas']['PostsWrite'] has 'Title' but NOT 'ID'

    public function test_type_mapping_edm_string(): void;
        // map Edm.String → { type: 'string' }

    public function test_type_mapping_edm_datetime(): void;
        // map Edm.DateTimeOffset → { type: 'string', format: 'date-time' }

    public function test_security_schemes_present(): void;
        // components/securitySchemes has BearerAuth and BasicAuth

    public function test_auth_paths_included(): void;
        // /wp-hr/v1/auth/login, /refresh, /logout present

    public function test_odata_error_schema_present(): void;
        // components/schemas/ODataError exists with correct shape
}
```

---

## Implementation Order

```
Phase 1 (sequential — cache and route depend on generator)
  1.1 (Generator) → 1.2 (Cache) → 1.3 (Router route)

Phase 2 (2.1 asset download first, then 2.2 and 2.3 parallel)
  2.1 (Assets) → [ 2.2 (Admin page) ∥ 2.3 (Bootstrapper) ]

Phase 3 (independent)
  3.1 (Unit tests)
```

---

## File changes summary

| File | Action |
|---|---|
| `src/openapi/class-odad-openapi-generator.php` | **Create** |
| `src/openapi/class-odad-openapi-cache.php` | **Create** |
| `src/admin/class-odad-admin-api-docs.php` | **Create** |
| `assets/swagger-ui/swagger-ui-bundle.js` | **Download** |
| `assets/swagger-ui/swagger-ui.css` | **Download** |
| `src/http/class-odad-router.php` | Add `/openapi.json` route + `handle_openapi()` |
| `src/admin/class-odad-admin.php` | Add "API Docs" submenu item |
| `src/hooks/subscribers/class-odad-subscriber-schema-changed.php` | Add `openapi_cache->bust()` |
| `src/bootstrap/class-odad-bootstrapper.php` | Register new singletons, update Router + SchemaChanged args |
| `wp-odata-suite.php` autoloader | Add `src/openapi/` directory |
| `tests/unit/openapi/OpenAPIGeneratorTest.php` | **Create** |

---

## Result for buyers / mobile developers

After implementation, a developer building the mobile app opens:

```
https://yoursite.com/wp-admin/ → WP-OData Suite → API Docs
```

And sees a fully interactive Swagger UI where they can:
- Browse every entity set and its properties
- Read all query parameter descriptions
- Try requests directly in the browser (authenticated via WP session)
- Copy `curl` commands for each operation
- Download the raw `openapi.json` for import into Postman, Insomnia, or code generators
