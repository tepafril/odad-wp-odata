# Task 3.6 — Query Before + Query After Subscribers

## Dependencies
- Task 1.3 (subscriber stubs)
- Task 3.5 (WPOS_Query_Engine — event types used)
- Task 4.1 (WPOS_Permission_Engine — used by Query Before subscriber)
- Task 4.2 (WPOS_Field_ACL — used by Query After subscriber)

> Note: This task can be started with stub permission/ACL dependencies and
> finalized once Phase 4 lands.

## Goal
Implement the two query subscribers that sit between the Hook Bridge and the domain:
- `WPOS_Subscriber_Query_Before` — row-level security + `wpos_query_context` filter
- `WPOS_Subscriber_Query_After` — field ACL stripping + `wpos_query_results` filter

---

## File 1: `src/hooks/subscribers/class-wpos-subscriber-query-before.php`

```php
class WPOS_Subscriber_Query_Before implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Permission_Engine $permissions,
        private WPOS_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Query_Before::class;
    }

    public function handle( WPOS_Event $event ): void {
        /** @var WPOS_Event_Query_Before $event */

        // 1. Row-level security: inject WHERE conditions into query context
        $ctx = $this->permissions->apply_row_filter(
            $event->entity_set,
            $event->user,
            $event->query_context
        );

        // 2. Public WP filter — external plugins can further modify query context
        $ctx = $this->bridge->filter(
            'wpos_query_context',
            $ctx,
            [ $event->entity_set, $event->user ]
        );

        // 3. Write modified context back to event
        $event->query_context = $ctx;
    }
}
```

---

## File 2: `src/hooks/subscribers/class-wpos-subscriber-query-after.php`

```php
class WPOS_Subscriber_Query_After implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Field_ACL   $field_acl,
        private WPOS_Hook_Bridge $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Query_After::class;
    }

    public function handle( WPOS_Event $event ): void {
        /** @var WPOS_Event_Query_After $event */

        // 1. Strip fields the user is not permitted to see
        $results = $this->field_acl->apply(
            $event->results,
            $event->entity_set,
            $event->user,
            'read'
        );

        // 2. Public WP filter — external plugins can modify final results
        $results = $this->bridge->filter(
            'wpos_query_results',
            $results,
            [ $event->entity_set, $event->user ]
        );

        // 3. Write back
        $event->results = $results;
    }
}
```

---

## Bootstrapper Update

Update `WPOS_Bootstrapper::register_subscribers()` to pass proper dependencies:
```php
new WPOS_Subscriber_Query_Before(
    $c->get(WPOS_Permission_Engine::class),
    $c->get(WPOS_Hook_Bridge::class),
),
new WPOS_Subscriber_Query_After(
    $c->get(WPOS_Field_ACL::class),
    $c->get(WPOS_Hook_Bridge::class),
),
```

---

## Acceptance Criteria

- `wpos_query_context` WP filter is fired for every collection query.
- External plugin returning a modified `WPOS_Query_Context` from `wpos_query_context` has its changes used by the adapter.
- `wpos_query_results` WP filter is fired after every query.
- External plugin modifying results in `wpos_query_results` has its changes returned to the client.
- Field ACL stripping runs before `wpos_query_results` fires (so plugin sees already-stripped results).
- Row-level security conditions from `apply_row_filter()` are injected into `$ctx->extra_conditions`.
