# Task 1.4 — HTTP Layer: Router, Request, Response, Error

## Dependencies
- Task 1.1 (DI container)
- Task 1.2 (event bus — specifically `WPOS_Event_REST_Init`)
- Task 1.3 (hook bridge)

## Goal
Build the WordPress REST API boundary layer. This layer registers OData routes with
`WP_REST_Server` and translates between WordPress REST request objects and the plugin's
internal `WPOS_Request` / `WPOS_Response` types.

The router is WP-aware. Everything it calls below it (query engine, write handler) is
pure PHP via injected services.

---

## Base URL

All OData endpoints live under:
```
/wp-json/odata/v4/
```

---

## Files to Create

### `src/http/class-wpos-request.php`

Parses an incoming `WP_REST_Request` into a typed OData request object.

Properties to expose:
```php
class WPOS_Request {
    public readonly string  $entity_set;     // e.g. 'Posts'
    public readonly string  $method;         // 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE'
    public readonly ?mixed  $key;            // entity key, null for collection
    public readonly ?string $nav_property;   // navigation property if present
    public readonly ?string $filter;         // raw $filter string
    public readonly ?string $select;         // raw $select string
    public readonly ?string $expand;         // raw $expand string
    public readonly ?string $orderby;        // raw $orderby string
    public readonly ?int    $top;            // $top value (default 100, max 1000)
    public readonly ?int    $skip;           // $skip value
    public readonly bool    $count;          // $count=true
    public readonly ?string $search;         // $search string
    public readonly ?string $compute;        // $compute string
    public readonly array   $body;           // parsed JSON body
    public readonly ?string $format;         // $format override
    public readonly ?string $prefer;         // Prefer: header value
    public readonly bool    $is_batch;
    public readonly bool    $is_query_post;  // /$query POST

    public static function from_wp( WP_REST_Request $wp_request, array $path_params ): self;
}
```

Enforce: `$top` default = 100, max = 1000. If client requests > 1000, cap silently at 1000.

### `src/http/class-wpos-response.php`

Formats the response as OData-compliant JSON.

```php
class WPOS_Response {
    // OData JSON response with @odata.context, @odata.count, value array
    public static function collection(
        array   $rows,
        string  $context_url,
        ?int    $total_count = null,
        ?string $next_link   = null,
    ): WP_REST_Response;

    public static function entity(
        array  $row,
        string $context_url,
    ): WP_REST_Response;

    public static function created(
        array  $row,
        string $entity_url,
    ): WP_REST_Response;    // 201 Created + Location header

    public static function no_content(): WP_REST_Response;  // 204

    public static function metadata_xml( string $csdl ): WP_REST_Response;
    public static function metadata_json( string $csdl ): WP_REST_Response;
    public static function service_document( array $entity_sets, string $base_url ): WP_REST_Response;
}
```

Response must set:
- `Content-Type: application/json;odata.metadata=minimal;odata.streaming=true`
- `OData-Version: 4.01`

### `src/http/class-wpos-error.php`

OData error format: `{"error": {"code": "...", "message": "..."}}`.

```php
class WPOS_Error {
    public static function not_found( string $message = '' ): WP_REST_Response;        // 404
    public static function forbidden( string $message = '' ): WP_REST_Response;        // 403
    public static function bad_request( string $code, string $message ): WP_REST_Response; // 400
    public static function method_not_allowed(): WP_REST_Response;                     // 405
    public static function internal( string $message = '' ): WP_REST_Response;         // 500
    public static function from_wp_error( WP_Error $error ): WP_REST_Response;
}
```

### `src/http/class-wpos-router.php`

Registers all OData routes with `WP_REST_Server`. Dispatches to injected services.
At Phase 1, only the `$metadata` and service document endpoints need real implementations.
All other routes return `WPOS_Error::not_found()` stubs until later phases wire them up.

Constructor:
```php
public function __construct(
    private WPOS_Query_Engine    $query_engine,    // injected, may be null stub in Phase 1
    private WPOS_Write_Handler   $write_handler,   // injected, may be null stub in Phase 1
    private WPOS_Metadata_Builder $metadata_builder,
    private WPOS_Permission_Engine $permission_engine, // may be null stub in Phase 1
) {}
```

Routes to register (in `register_routes()` called from the `rest_api_init` subscriber):

```
GET  /odata/v4/                            → service_document()
GET  /odata/v4/$metadata                   → metadata()
POST /odata/v4/$batch                      → batch()          [stub: 501]
GET  /odata/v4/(?P<entity>[a-zA-Z0-9_]+)  → collection()
POST /odata/v4/(?P<entity>[a-zA-Z0-9_]+)  → create()
...  (all routes from Section 16 of the master plan)
```

The `$metadata` endpoint must work in Phase 1. All entity CRUD routes can be stubs
returning 501 Not Implemented until Phase 2/3 land.

---

## OData Headers

All responses must include:
```
OData-Version: 4.01
Content-Type: application/json;odata.metadata=minimal
```

`$metadata` XML response:
```
Content-Type: application/xml
```

---

## Bootstrapper Update

In `class-wpos-bootstrapper.php`, register `WPOS_Router` as a singleton and update
`WPOS_Subscriber_Rest_Init` (or create the REST init wiring in `WPOS_Hook_Bridge`)
to call `$router->register_routes()` when `rest_api_init` fires.

The `WPOS_Event_REST_Init` event should be caught by a listener that calls
`$router->register_routes()`.

---

## Acceptance Criteria

- `GET /wp-json/odata/v4/` returns a valid OData service document JSON.
- `GET /wp-json/odata/v4/$metadata` returns a valid (minimal) CSDL XML response with `OData-Version: 4.01` header.
- `GET /wp-json/odata/v4/$metadata?$format=application/json` returns JSON CSDL (can be minimal stub).
- All unimplemented entity routes return 501 with an OData-formatted error body.
- `$top` is capped at 1000; default is 100 when not specified.
- `Content-Type` header is correct on all OData responses.
