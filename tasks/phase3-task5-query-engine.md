# Task 3.5 — Query Engine

## Dependencies
- Tasks 3.1–3.4 (all compilers)
- Task 2.1 (adapter interface + resolver)
- Task 1.2 (event bus — Query Before/After events)

## Goal
Build `WPOS_Query_Engine` — the orchestrator that combines all compilers,
the adapter, and the event bus to execute an OData query end-to-end.
Also implement server-driven pagination and the `/$query` POST body endpoint.

---

## File

### `src/query/class-wpos-query-engine.php`

```php
class WPOS_Query_Engine {

    public function __construct(
        private WPOS_Filter_Parser    $filter_parser,
        private WPOS_Filter_Compiler  $filter_compiler,
        private WPOS_Select_Compiler  $select_compiler,
        private WPOS_Expand_Compiler  $expand_compiler,
        private WPOS_Compute_Compiler $compute_compiler,
        private WPOS_Orderby_Compiler $orderby_compiler,
        private WPOS_Search_Compiler  $search_compiler,
        private WPOS_Adapter_Resolver $adapter_resolver,
        private WPOS_Event_Bus        $event_bus,
    ) {}

    /**
     * Execute an OData collection query.
     *
     * @param WPOS_Request $request  Parsed incoming OData request
     * @param WP_User      $user     Current WordPress user
     * @return WPOS_Query_Result
     */
    public function execute( WPOS_Request $request, \WP_User $user ): WPOS_Query_Result;

    /**
     * Fetch a single entity by key.
     */
    public function get_entity( WPOS_Request $request, \WP_User $user ): ?array;
}
```

---

## Execution Flow

```
execute():
  1. Resolve adapter = adapter_resolver->resolve($request->entity_set)
  2. Build WPOS_Query_Context from $request:
       - Parse $filter string → AST via filter_parser
       - Compile AST → SQL fragment via filter_compiler
       - Compile $select → column list via select_compiler
       - Compile $orderby via orderby_compiler
       - Compile $compute via compute_compiler
       - Compile $search via search_compiler
       - Set $top, $skip
  3. dispatch(WPOS_Event_Query_Before) — subscribers may modify $ctx
       (row-level security injected here by WPOS_Subscriber_Query_Before)
  4. rows = adapter->get_collection($ctx)
  5. total = $request->count ? adapter->get_count($ctx) : null
  6. If $request->expand: rows = expand_compiler->execute(rows, expand_plan, entity_set)
  7. dispatch(WPOS_Event_Query_After) — subscribers may modify $results
       (field ACL stripping happens here)
  8. Compute @odata.nextLink if rows === $top (more pages may exist)
  9. Return WPOS_Query_Result
```

---

## `WPOS_Query_Result` Class

Create `src/query/class-wpos-query-result.php`:

```php
class WPOS_Query_Result {
    public function __construct(
        public readonly array   $rows,
        public readonly ?int    $total_count = null,
        public readonly ?string $next_link   = null,  // @odata.nextLink URL
    ) {}
}
```

---

## Pagination (`@odata.nextLink`)

Server-driven pagination:
- If `count(rows) === $top`, assume there may be more pages.
- Construct `@odata.nextLink` by appending/updating `$skip` in the request URL.
- Format: `{base_url}/{EntitySet}?$top={top}&$skip={skip+top}&...`

The `@odata.nextLink` is included in `WPOS_Response::collection()` output.

---

## `/$query` Endpoint Support

The `/$query` POST body endpoint allows sending long filter expressions in a POST body
instead of a URL query string. The router dispatches `/$query` requests to
`query_engine->execute()` after merging body params into the request.

In `WPOS_Request`, detect `is_query_post = true` and read `$filter`, `$select`, etc.
from the POST body instead of URL parameters.

---

## Bootstrapper Update

```php
$c->singleton( WPOS_Query_Engine::class, fn($c) => new WPOS_Query_Engine(
    $c->get(WPOS_Filter_Parser::class),
    $c->get(WPOS_Filter_Compiler::class),
    $c->get(WPOS_Select_Compiler::class),
    $c->get(WPOS_Expand_Compiler::class),
    $c->get(WPOS_Compute_Compiler::class),
    $c->get(WPOS_Orderby_Compiler::class),
    $c->get(WPOS_Search_Compiler::class),
    $c->get(WPOS_Adapter_Resolver::class),
    $c->get(WPOS_Event_Bus::class),
));
```

---

## Acceptance Criteria

- `GET /odata/v4/Posts?$filter=Status eq 'publish'&$select=ID,Title&$top=10` returns paginated results.
- `@odata.count` is included when `$count=true`.
- `@odata.nextLink` is included when results equal `$top`.
- `POST /odata/v4/Posts/$query` with body `{"$filter": "Status eq 'publish'"}` produces same result as GET with URL params.
- `WPOS_Event_Query_Before` is dispatched before query execution.
- `WPOS_Event_Query_After` is dispatched after, allowing result modification.
- `$expand=Author` loads Author data without N+1 queries.
- `$top` default = 100, enforced max = 1000.
