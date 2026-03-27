# Phase 9 — Swagger / OpenAPI 3.0 Documentation

## Overview

Automatically generate an OpenAPI 3.0 specification from the live schema registry
and serve Swagger UI inside the WordPress admin. No static files to maintain —
the spec is always in sync with whatever entity sets are registered.

**Spec endpoint:** `GET /wp-json/odata/v4/openapi.json`
**Swagger UI page:** WordPress admin → WP-OData Suite → API Docs
**Cached:** Yes — WP transient `ODAD_openapi_json`, `DAY_IN_SECONDS` TTL, same bust as `$metadata`

---

## Corrections vs original draft

| Issue in original | Correction |
|---|---|
| Auth paths used `/wp-hr/v1/auth/*` | Actual namespace is `odad/v1` → paths are `/odad/v1/auth/*` |
| `permission_callback => [this, 'check_public_access']` | `check_public_access()` is **private** and takes `($entity, $method)` — not a valid WP callback. Use `'__return_true'` (consistent with all other OData routes) |
| Single server URL `rest_url('odata/v4')` | OData and auth live in different namespaces. Use `rest_url('/')` as server base; all paths include their full namespace prefix |

---

## What gets documented

| Endpoint pattern | Methods |
|---|---|
| `/odata/v4/` | GET (service document) |
| `/odata/v4/$metadata` | GET (CSDL XML + JSON) |
| `/odata/v4/{EntitySet}` | GET (collection), POST (create) |
| `/odata/v4/{EntitySet}({key})` | GET, PATCH, PUT, DELETE |
| `/odata/v4/$batch` | POST |
| `/odad/v1/auth/login` | POST |
| `/odad/v1/auth/refresh` | POST |
| `/odad/v1/auth/logout` | POST |

All OData query parameters (`$filter`, `$select`, `$orderby`, `$top`, `$skip`,
`$count`, `$expand`, `$search`) documented as reusable parameter components.

Security schemes: **Bearer JWT** and **WP Nonce** (`X-WP-Nonce` header).

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
ODAD_OpenAPI_Generator ──► OpenAPI 3.0 array
        │
        ▼
ODAD_OpenAPI_Cache  (transient: 'ODAD_openapi_json', TTL: DAY_IN_SECONDS)
        │
        ├──► GET /odata/v4/openapi.json   (ODAD_Router::handle_openapi)
        │
        └──► Admin Swagger UI page        (ODAD_Admin_API_Docs::render)
                  └── Swagger UI assets (assets/swagger-ui/)
```

Cache busted by `ODAD_Event_Schema_Changed` — same event that busts `$metadata`.

---

## Phase 1 — Core classes

### Task 1.1 — OpenAPI Generator

**File:** `src/openapi/class-odad-openapi-generator.php`

```php
class ODAD_OpenAPI_Generator {

    public function __construct(
        private ODAD_Schema_Registry $registry,
    ) {}

    /** Build and return the full OpenAPI 3.0 spec as a PHP array. */
    public function generate(): array;

    private function build_info(): array;
    private function build_components(): array;
    private function build_entity_schema( string $entity_set, array $definition ): array;
    private function build_write_schema( string $entity_set, array $definition ): array;
    private function build_collection_response_schema( string $entity_set ): array;
    private function build_entity_paths( string $entity_set, array $definition ): array;
    private function build_auth_paths(): array;
    private function build_system_paths(): array;
    private function build_odata_parameters(): array;
    private function map_type( string $edm_type ): array;
}
```

**`generate()` top-level structure:**

```php
[
    'openapi' => '3.0.3',
    'info'    => [
        'title'       => 'WP-OData Suite API',
        'description' => 'OData v4.01 REST API for WordPress data.',
        'version'     => ODAD_VERSION,
    ],
    'servers' => [
        // Use REST root so both /odata/v4/* and /odad/v1/* paths work
        [ 'url' => rest_url( '/' ), 'description' => 'WordPress REST root' ],
    ],
    'security' => [
        [ 'BearerAuth' => [] ],
        [ 'WpNonce'    => [] ],
    ],
    'paths'      => [...],   // build_entity_paths + build_auth_paths + build_system_paths
    'components' => [...],   // schemas, parameters, responses, securitySchemes
]
```

**`build_components()` securitySchemes:**

```php
'securitySchemes' => [
    'BearerAuth' => [
        'type'         => 'http',
        'scheme'       => 'bearer',
        'bearerFormat' => 'JWT',
        'description'  => 'Access token from POST /odad/v1/auth/login. Expires in 15 min.',
    ],
    'WpNonce' => [
        'type'        => 'apiKey',
        'in'          => 'header',
        'name'        => 'X-WP-Nonce',
        'description' => 'WordPress REST nonce (wp_create_nonce("wp_rest")). For admin UI use.',
    ],
],
```

**`build_odata_parameters()`:**

```php
'parameters' => [
    'filter'  => [ 'name' => '$filter',  'in' => 'query', 'schema' => ['type' => 'string'],  'description' => 'OData $filter expression. Example: Status eq \'publish\'' ],
    'select'  => [ 'name' => '$select',  'in' => 'query', 'schema' => ['type' => 'string'],  'description' => 'Comma-separated property names to return.' ],
    'orderby' => [ 'name' => '$orderby', 'in' => 'query', 'schema' => ['type' => 'string'],  'description' => 'Sort expression. Example: CreatedAt desc' ],
    'top'     => [ 'name' => '$top',     'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 100] ],
    'skip'    => [ 'name' => '$skip',    'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 0, 'default' => 0] ],
    'count'   => [ 'name' => '$count',   'in' => 'query', 'schema' => ['type' => 'boolean'], 'description' => 'Include @odata.count in response.' ],
    'expand'  => [ 'name' => '$expand',  'in' => 'query', 'schema' => ['type' => 'string'],  'description' => 'Navigation properties to expand. Example: Author' ],
    'search'  => [ 'name' => '$search',  'in' => 'query', 'schema' => ['type' => 'string'] ],
],
```

**`build_entity_paths()` example for `Posts`:**

Paths are prefixed with full WP REST namespace because server URL is the REST root `/`.

```php
'/odata/v4/Posts' => [
    'get'  => [
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
            '200' => [ 'description' => 'Collection of Posts', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PostsCollection']]] ],
            '401' => ['$ref' => '#/components/responses/401'],
            '403' => ['$ref' => '#/components/responses/403'],
        ],
    ],
    'post' => [
        'tags'        => ['Posts'],
        'summary'     => 'Create Post',
        'operationId' => 'createPost',
        'requestBody' => [ 'required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PostsWrite']]] ],
        'responses' => [
            '201' => [ 'description' => 'Created', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Posts']]] ],
            '400' => ['$ref' => '#/components/responses/400'],
            '403' => ['$ref' => '#/components/responses/403'],
        ],
    ],
],
'/odata/v4/Posts({ID})' => [
    'parameters' => [
        [ 'name' => 'ID', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer'] ],
    ],
    'get'    => [ 'tags' => ['Posts'], 'summary' => 'Get Post',     'operationId' => 'getPost',     'responses' => ['200' => [...], '404' => ['$ref' => '#/components/responses/404']] ],
    'patch'  => [ 'tags' => ['Posts'], 'summary' => 'Update Post',  'operationId' => 'updatePost',  'requestBody' => [...], 'responses' => ['204' => [...], '404' => [...]] ],
    'put'    => [ 'tags' => ['Posts'], 'summary' => 'Replace Post', 'operationId' => 'replacePost', 'requestBody' => [...], 'responses' => ['204' => [...], '404' => [...]] ],
    'delete' => [ 'tags' => ['Posts'], 'summary' => 'Delete Post',  'operationId' => 'deletePost',  'responses' => ['204' => [...], '404' => [...]] ],
],
```

Properties with `'read_only' => true` are **excluded from `PostsWrite`** but included in `Posts`.

**`build_auth_paths()` — correct namespace `odad/v1`:**

```php
'/odad/v1/auth/login' => [
    'post' => [
        'tags'        => ['Authentication'],
        'summary'     => 'Login',
        'operationId' => 'authLogin',
        'security'    => [],   // no auth required
        'requestBody' => [
            'required' => true,
            'content'  => [ 'application/json' => [ 'schema' => [
                'type'       => 'object',
                'required'   => ['username', 'password'],
                'properties' => [
                    'username' => ['type' => 'string'],
                    'password' => ['type' => 'string', 'format' => 'password'],
                    'device'   => ['type' => 'string', 'description' => 'Device label (optional).'],
                ],
            ] ] ],
        ],
        'responses' => [
            '200' => [ 'description' => 'OK', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/LoginResponse']]] ],
            '401' => [ 'description' => 'Invalid credentials' ],
            '429' => [ 'description' => 'Too many attempts' ],
        ],
    ],
],
'/odad/v1/auth/refresh' => [
    'post' => [
        'tags'        => ['Authentication'],
        'summary'     => 'Refresh access token',
        'operationId' => 'authRefresh',
        'security'    => [],
        'requestBody' => [ 'required' => true, 'content' => ['application/json' => ['schema' => [
            'type' => 'object', 'required' => ['refresh_token'],
            'properties' => [ 'refresh_token' => ['type' => 'string', 'description' => '64-char hex token from login.'] ],
        ]]] ],
        'responses' => [
            '200' => [ 'description' => 'New access token', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RefreshResponse']]] ],
            '401' => [ 'description' => 'Invalid or expired refresh token' ],
        ],
    ],
],
'/odad/v1/auth/logout' => [
    'post' => [
        'tags'        => ['Authentication'],
        'summary'     => 'Logout',
        'operationId' => 'authLogout',
        'requestBody' => [ 'required' => false, 'content' => ['application/json' => ['schema' => [
            'type' => 'object',
            'properties' => [
                'refresh_token' => ['type' => 'string'],
                'all_devices'   => ['type' => 'boolean', 'default' => false],
            ],
        ]]] ],
        'responses' => [
            '204' => [ 'description' => 'Logged out' ],
        ],
    ],
],
```

**Auth response schemas to add to `components/schemas`:**

```php
'LoginResponse' => [
    'type'       => 'object',
    'properties' => [
        'access_token'  => ['type' => 'string'],
        'refresh_token' => ['type' => 'string'],
        'expires_in'    => ['type' => 'integer', 'example' => 900],
        'user'          => ['$ref' => '#/components/schemas/AuthUser'],
    ],
],
'AuthUser' => [
    'type'       => 'object',
    'properties' => [
        'id'           => ['type' => 'integer'],
        'login'        => ['type' => 'string'],
        'email'        => ['type' => 'string', 'format' => 'email'],
        'display_name' => ['type' => 'string'],
        'roles'        => ['type' => 'array', 'items' => ['type' => 'string']],
    ],
],
'RefreshResponse' => [
    'type'       => 'object',
    'properties' => [
        'access_token' => ['type' => 'string'],
        'expires_in'   => ['type' => 'integer', 'example' => 900],
    ],
],
```

---

### Task 1.2 — OpenAPI Cache

**File:** `src/openapi/class-odad-openapi-cache.php`

```php
class ODAD_OpenAPI_Cache {
    private const TRANSIENT = 'ODAD_openapi_json';
    private const TTL       = DAY_IN_SECONDS;   // same as ODAD_Metadata_Cache

    public function get(): ?string;            // get_transient → string or null
    public function set( string $json ): void; // set_transient with TTL
    public function bust(): void;              // delete_transient
}
```

**Wire into `ODAD_Subscriber_Schema_Changed`** (update existing file):

```php
// class-odad-subscriber-schema-changed.php — updated constructor + handle()
class ODAD_Subscriber_Schema_Changed implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Metadata_Cache        $cache,
        private ?ODAD_OpenAPI_Cache        $openapi_cache = null,  // ADD
    ) {}

    public function handle( ODAD_Event $event ): void {
        $this->cache->bust();
        $this->openapi_cache?->bust();   // ADD
    }
}
```

**Update bootstrapper subscriber registration** (in `register_subscribers()`):

```php
// Before (line ~209 in class-odad-bootstrapper.php):
$bus->subscribe( new ODAD_Subscriber_Schema_Changed( $container->get( ODAD_Metadata_Cache::class ) ) );

// After:
$bus->subscribe( new ODAD_Subscriber_Schema_Changed(
    $container->get( ODAD_Metadata_Cache::class ),
    $container->get( ODAD_OpenAPI_Cache::class ),
) );
```

---

### Task 1.3 — Router route

**Add to `ODAD_Router::register_routes()`** — use `'__return_true'` (consistent with all other OData routes; spec is public information):

```php
register_rest_route( self::NAMESPACE, '/openapi\.json', [
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => [ $this, 'handle_openapi' ],
    'permission_callback' => '__return_true',
] );
```

**Add handler method to `ODAD_Router`:**

```php
public function handle_openapi( WP_REST_Request $request ): WP_REST_Response {
    if ( null === $this->openapi_cache || null === $this->openapi_generator ) {
        return ODAD_Error::not_implemented( 'OpenAPI spec is not available.' );
    }

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

**Update `ODAD_Router` constructor** — add two optional args (nullable so existing construction still works without them):

```php
public function __construct(
    private readonly mixed                      $query_engine,
    private readonly mixed                      $write_handler,
    private readonly mixed                      $metadata_builder,
    private readonly mixed                      $permission_engine,
    private readonly ?ODAD_Hook_Bridge          $bridge             = null,
    private readonly ?ODAD_Function_Registry    $function_registry  = null,
    private readonly ?ODAD_Action_Registry      $action_registry    = null,
    private readonly ?ODAD_Async_Handler        $async_handler      = null,
    private readonly ?ODAD_Batch_Handler        $batch_handler      = null,
    private readonly ?ODAD_OpenAPI_Generator    $openapi_generator  = null,  // ADD
    private readonly ?ODAD_OpenAPI_Cache        $openapi_cache      = null,  // ADD
) {}
```

---

## Phase 2 — Swagger UI Admin Page

### Task 2.1 — Swagger UI assets

Download from: `https://github.com/swagger-api/swagger-ui/releases` (latest `swagger-ui-dist`)

Place at:

```
assets/swagger-ui/
├── swagger-ui-bundle.js    (~1.1 MB minified)
├── swagger-ui.css
```

Only these two files needed. No standalone preset or map files required.

### Task 2.2 — Admin API Docs page

**File:** `src/admin/class-odad-admin-api-docs.php`

```php
class ODAD_Admin_API_Docs {

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'wp-odata-suite' ) );
        }

        $spec_url = rest_url( 'odata/v4/openapi.json' );

        wp_enqueue_style(
            'odad-swagger-ui',
            ODAD_PLUGIN_URL . 'assets/swagger-ui/swagger-ui.css',
            [],
            ODAD_VERSION
        );
        wp_enqueue_script(
            'odad-swagger-ui',
            ODAD_PLUGIN_URL . 'assets/swagger-ui/swagger-ui-bundle.js',
            [],
            ODAD_VERSION,
            true
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'API Documentation', 'wp-odata-suite' ); ?></h1>
            <p>
                <?php esc_html_e( 'Live OpenAPI spec:', 'wp-odata-suite' ); ?>
                <a href="<?php echo esc_url( $spec_url ); ?>" target="_blank">
                    <?php echo esc_html( $spec_url ); ?>
                </a>
            </p>
            <div id="odad-swagger-ui-container" style="margin-top:16px"></div>
            <script>
            SwaggerUIBundle({
                url:    <?php echo wp_json_encode( $spec_url ); ?>,
                dom_id: '#odad-swagger-ui-container',
                presets: [ SwaggerUIBundle.presets.apis ],
                layout:  'BaseLayout',
                deepLinking: true,
                displayRequestDuration: true,
                requestInterceptor: function( req ) {
                    // Inject WP nonce so Try-it-out works from the admin without a separate login
                    req.headers['X-WP-Nonce'] = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
                    return req;
                },
            });
            </script>
        </div>
        <?php
    }
}
```

**Add submenu to `ODAD_Admin::register_menu()`** — after the existing Permissions submenu:

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

### Task 2.3 — Bootstrapper updates

**Register new singletons** — add before the `ODAD_Router` singleton in `build()`:

```php
// ── OpenAPI (Phase 9) ─────────────────────────────────────────────────────
$container->singleton( ODAD_OpenAPI_Cache::class, fn() => new ODAD_OpenAPI_Cache() );

$container->singleton( ODAD_OpenAPI_Generator::class, fn( ODAD_Container $c ) => new ODAD_OpenAPI_Generator(
    $c->get( ODAD_Schema_Registry::class ),
) );

$container->singleton( ODAD_Admin_API_Docs::class, fn() => new ODAD_Admin_API_Docs() );
```

**Update `ODAD_Router` singleton** — add two new named args:

```php
$container->singleton( ODAD_Router::class, function ( ODAD_Container $c ): ODAD_Router {
    return new ODAD_Router(
        query_engine:       $c->get( ODAD_Query_Engine::class ),
        write_handler:      $c->get( ODAD_Write_Handler::class ),
        metadata_builder:   $c->get( ODAD_Metadata_Builder::class ),
        permission_engine:  $c->get( ODAD_Permission_Engine::class ),
        bridge:             $c->get( ODAD_Hook_Bridge::class ),
        function_registry:  $c->get( ODAD_Function_Registry::class ),
        action_registry:    $c->get( ODAD_Action_Registry::class ),
        async_handler:      $c->get( ODAD_Async_Handler::class ),
        batch_handler:      $c->get( ODAD_Batch_Handler::class ),
        openapi_generator:  $c->get( ODAD_OpenAPI_Generator::class ),  // ADD
        openapi_cache:      $c->get( ODAD_OpenAPI_Cache::class ),       // ADD
    );
} );
```

**Update `ODAD_Subscriber_Schema_Changed` singleton** in `register_subscribers()`:

```php
$bus->subscribe( new ODAD_Subscriber_Schema_Changed(
    $container->get( ODAD_Metadata_Cache::class ),
    $container->get( ODAD_OpenAPI_Cache::class ),   // ADD
) );
```

---

### Task 2.4 — Autoloader

**Add to `$paths` array in `wp-odata-suite.php`** (after the `src/auth/` line):

```php
ODAD_PLUGIN_DIR . "src/openapi/class-odad-{$filename}.php",
```

---

## Phase 3 — Tests

### Task 3.1 — Unit tests

**File:** `tests/unit/openapi/OpenAPIGeneratorTest.php`

Add to bootstrap (`tests/unit/bootstrap.php`):
- Stub `rest_url( string $path )` → `'https://example.com/wp-json/' . ltrim($path, '/')`
- Require `src/openapi/class-odad-openapi-generator.php`
- Require `src/metadata/class-odad-schema-registry.php`

```php
class OpenAPIGeneratorTest extends PHPUnit\Framework\TestCase {

    private ODAD_OpenAPI_Generator $generator;
    private array                  $spec;

    protected function setUp(): void {
        $registry = new ODAD_Schema_Registry();
        $registry->register( 'Posts', [
            'key'        => 'ID',
            'properties' => [
                'ID'    => [ 'column' => 'ID',         'type' => 'Edm.Int64',  'read_only' => true ],
                'Title' => [ 'column' => 'post_title', 'type' => 'Edm.String' ],
                'Score' => [ 'column' => 'score',      'type' => 'Edm.Double' ],
                'Published' => [ 'column' => 'post_date', 'type' => 'Edm.DateTimeOffset' ],
            ],
        ] );
        $this->generator = new ODAD_OpenAPI_Generator( $registry );
        $this->spec      = $this->generator->generate();
    }

    public function test_openapi_version(): void {
        $this->assertSame( '3.0.3', $this->spec['openapi'] );
    }

    public function test_entity_set_collection_path_exists(): void {
        $this->assertArrayHasKey( '/odata/v4/Posts', $this->spec['paths'] );
    }

    public function test_entity_key_path_exists(): void {
        $this->assertArrayHasKey( '/odata/v4/Posts({ID})', $this->spec['paths'] );
    }

    public function test_collection_path_has_odata_parameters(): void {
        $params = $this->spec['paths']['/odata/v4/Posts']['get']['parameters'];
        $refs   = array_column( $params, '$ref' );
        $this->assertContains( '#/components/parameters/filter', $refs );
        $this->assertContains( '#/components/parameters/top', $refs );
    }

    public function test_entity_schema_generated(): void {
        $schema = $this->spec['components']['schemas']['Posts'];
        $this->assertArrayHasKey( 'ID',    $schema['properties'] );
        $this->assertArrayHasKey( 'Title', $schema['properties'] );
    }

    public function test_write_schema_excludes_read_only(): void {
        $write = $this->spec['components']['schemas']['PostsWrite'];
        $this->assertArrayHasKey( 'Title', $write['properties'] );
        $this->assertArrayNotHasKey( 'ID', $write['properties'] );
    }

    public function test_type_mapping_edm_string(): void {
        $this->assertSame(
            [ 'type' => 'string' ],
            $this->spec['components']['schemas']['Posts']['properties']['Title']
        );
    }

    public function test_type_mapping_edm_double(): void {
        $prop = $this->spec['components']['schemas']['Posts']['properties']['Score'];
        $this->assertSame( 'number', $prop['type'] );
        $this->assertSame( 'double', $prop['format'] );
    }

    public function test_type_mapping_edm_datetime(): void {
        $prop = $this->spec['components']['schemas']['Posts']['properties']['Published'];
        $this->assertSame( 'string',    $prop['type'] );
        $this->assertSame( 'date-time', $prop['format'] );
    }

    public function test_security_schemes_present(): void {
        $schemes = $this->spec['components']['securitySchemes'];
        $this->assertArrayHasKey( 'BearerAuth', $schemes );
        $this->assertArrayHasKey( 'WpNonce',    $schemes );
    }

    public function test_auth_paths_included(): void {
        $this->assertArrayHasKey( '/odad/v1/auth/login',   $this->spec['paths'] );
        $this->assertArrayHasKey( '/odad/v1/auth/refresh', $this->spec['paths'] );
        $this->assertArrayHasKey( '/odad/v1/auth/logout',  $this->spec['paths'] );
    }

    public function test_odata_error_schema_present(): void {
        $this->assertArrayHasKey( 'ODataError', $this->spec['components']['schemas'] );
        $this->assertArrayHasKey( 'error', $this->spec['components']['schemas']['ODataError']['properties'] );
    }

    public function test_login_response_schema_present(): void {
        $this->assertArrayHasKey( 'LoginResponse', $this->spec['components']['schemas'] );
        $this->assertArrayHasKey( 'AuthUser',      $this->spec['components']['schemas'] );
        $this->assertArrayHasKey( 'RefreshResponse', $this->spec['components']['schemas'] );
    }
}
```

---

## Implementation Order

```
Phase 1 (sequential — each depends on the previous)
  1.1 Generator → 1.2 Cache → 1.3 Router route

Phase 2 (2.1 first, then 2.2–2.4 in any order)
  2.1 Download Swagger UI assets
  2.2 Admin API Docs page
  2.3 Bootstrapper updates
  2.4 Autoloader path

Phase 3 (independent — add rest_url stub to bootstrap first)
  3.1 Unit tests
```

---

## Complete file change list

| File | Change |
|---|---|
| `src/openapi/class-odad-openapi-generator.php` | **Create** |
| `src/openapi/class-odad-openapi-cache.php` | **Create** |
| `src/admin/class-odad-admin-api-docs.php` | **Create** |
| `assets/swagger-ui/swagger-ui-bundle.js` | **Download** |
| `assets/swagger-ui/swagger-ui.css` | **Download** |
| `src/http/class-odad-router.php` | Add 2 constructor params + `handle_openapi()` + route registration |
| `src/admin/class-odad-admin.php` | Add `odad-api-docs` submenu to `register_menu()` |
| `src/hooks/subscribers/class-odad-subscriber-schema-changed.php` | Add optional `ODAD_OpenAPI_Cache` param; bust in `handle()` |
| `src/bootstrap/class-odad-bootstrapper.php` | Register 3 new singletons; update Router + SchemaChanged args |
| `wp-odata-suite.php` | Add `src/openapi/` to autoloader `$paths` array |
| `tests/unit/openapi/OpenAPIGeneratorTest.php` | **Create** |
| `tests/unit/bootstrap.php` | Add `rest_url()` stub + require openapi source files |

---

## Implementation Status

| Task | File | Status |
|---|---|---|
| 1.1 OpenAPI Generator | `src/openapi/class-odad-openapi-generator.php` | ✅ Done |
| 1.2 OpenAPI Cache | `src/openapi/class-odad-openapi-cache.php` | ✅ Done |
| 1.3 Router route | `src/http/class-odad-router.php` | ✅ Done |
| 2.1 Swagger UI assets | `assets/swagger-ui/` | ✅ Done |
| 2.2 Admin API Docs page | `src/admin/class-odad-admin-api-docs.php` | ✅ Done |
| 2.3 Bootstrapper updates | `src/bootstrap/class-odad-bootstrapper.php` | ✅ Done |
| 2.4 Autoloader | `wp-odata-suite.php` | ✅ Done |
| 3.1 Unit tests | `tests/unit/openapi/OpenAPIGeneratorTest.php` | ✅ Done |
