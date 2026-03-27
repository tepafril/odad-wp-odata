# Task 5.4 — OData Functions + Actions Registry + Routing

## Dependencies
- Task 1.3 (hook bridge — `ODAD_register_functions`, `ODAD_register_actions` hooks)
- Task 1.4 (router — new routes needed)
- Task 4.3 (permission check flow)

## Goal
Implement registries and routing for OData bound/unbound functions and actions.
External plugins register callables; the router dispatches to them.

---

## OData Functions vs Actions

| Aspect | Function | Action |
|---|---|---|
| HTTP method | GET | POST |
| Side effects | None (read-only) | Yes (write) |
| Binding | Bound to entity/set or unbound | Same |
| Return value | Any value | Any value or void |

Examples:
```
GET  /odata/v4/NS.GetPublishedCount()           → unbound function
GET  /odata/v4/Posts(42)/NS.GetRelatedPosts()   → bound function
POST /odata/v4/NS.SendNotification              → unbound action
POST /odata/v4/Posts(42)/NS.PublishNow          → bound action
```

---

## File 1: `src/query/class-odad-function-registry.php`

```php
class ODAD_Function_Registry {

    private array $functions = [];

    /**
     * Register an OData function.
     *
     * @param string   $name       Qualified name, e.g. 'NS.GetPublishedCount'
     * @param callable $handler    fn(array $params, ?WP_User $user): mixed
     * @param array    $binding    null = unbound | ['entity_set' => 'Posts'] = bound to entity set
     *                             | ['entity_set' => 'Posts', 'bound_to' => 'entity'] = bound to single entity
     * @param array    $parameters [ ['name' => 'status', 'type' => 'Edm.String', 'required' => true], ... ]
     * @param string   $return_type  OData return type, e.g. 'Edm.Int32', 'Collection(WPOData.PostEntityType)'
     */
    public function register(
        string   $name,
        callable $handler,
        array    $binding    = [],
        array    $parameters = [],
        string   $return_type = 'Edm.String'
    ): void;

    public function has( string $name ): bool;
    public function get( string $name ): array;   // returns the registration entry
    public function all(): array;
}
```

## File 2: `src/write/class-odad-action-registry.php`

Same structure as `ODAD_Function_Registry` but for actions (POST).

---

## Router Updates

Add routes to `ODAD_Router`:

```
// Unbound function
GET  /odata/v4/(?P<function>[a-zA-Z0-9_.]+)\((?P<params>[^)]*)\)

// Bound function (entity set)
GET  /odata/v4/(?P<entity>[a-zA-Z0-9_]+)/(?P<function>[a-zA-Z0-9_.]+)\((?P<params>[^)]*)\)

// Bound function (single entity)
GET  /odata/v4/(?P<entity>[a-zA-Z0-9_]+)\((?P<key>[^)]+)\)/(?P<function>[a-zA-Z0-9_.]+)\((?P<params>[^)]*)\)

// Unbound action
POST /odata/v4/(?P<action>[a-zA-Z0-9_.]+)

// Bound action (single entity)
POST /odata/v4/(?P<entity>[a-zA-Z0-9_]+)\((?P<key>[^)]+)\)/(?P<action>[a-zA-Z0-9_.]+)
```

Router dispatch for functions:
```php
$fn_entry  = $function_registry->get($function_name);
$params    = parse_function_params($raw_params);   // parse key=value from URL
$result    = ($fn_entry['handler'])($params, $user);
return ODAD_Response::entity(['value' => $result], $context_url);
```

---

## Registration via WP Hooks

In `ODAD_Subscriber_Schema_Init` (or a dedicated init subscriber), fire:
```php
$this->bridge->action('ODAD_register_functions', [$function_registry]);
$this->bridge->action('ODAD_register_actions',   [$action_registry]);
```

External plugin example:
```php
add_action('ODAD_register_functions', function(ODAD_Function_Registry $r) {
    $r->register(
        'NS.GetPublishedCount',
        fn($params, $user) => (new \WP_Query(['post_status'=>'publish','post_type'=>'post']))->found_posts,
        [],  // unbound
        [],  // no params
        'Edm.Int32'
    );
});
```

---

## Metadata Integration

Functions and actions must appear in `$metadata` CSDL output.
In `ODAD_Metadata_Builder::build_xml()`, iterate `function_registry->all()` and
`action_registry->all()` to emit `<Function>` and `<Action>` CSDL elements.

---

## Acceptance Criteria

- `GET /odata/v4/NS.MyFunction()` dispatches to the registered handler.
- `POST /odata/v4/Posts(42)/NS.Publish` dispatches to the bound action handler with `$key = 42`.
- Unregistered function/action returns 404.
- Functions and actions appear in `$metadata` CSDL output.
- `ODAD_register_functions` fires during `init` and receives `ODAD_Function_Registry` as argument.
- `ODAD_register_actions` fires during `init` and receives `ODAD_Action_Registry` as argument.
