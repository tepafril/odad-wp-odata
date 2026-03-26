# Task 4.3 — Permission Check, Write Before, Write After Subscribers

## Dependencies
- Task 1.3 (subscriber stubs)
- Task 4.1 (WPOS_Permission_Engine)
- Task 4.2 (WPOS_Field_ACL)

## Goal
Implement the three permission-related subscribers that bridge the event bus
to WP filter hooks and the permission/ACL domain services.

---

## File 1: `src/hooks/subscribers/class-wpos-subscriber-permission-check.php`

Handles `WPOS_Event_Permission_Check`. Applies the `wpos_can_{operation}` WP filter
so external plugins can override the permission decision.

```php
class WPOS_Subscriber_Permission_Check implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Permission_Engine $permissions,
        private WPOS_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Permission_Check::class;
    }

    public function handle( WPOS_Event $event ): void {
        /** @var WPOS_Event_Permission_Check $event */

        // 1. Domain logic: check WP capability
        $granted = $this->permissions->can(
            $event->entity_set,
            $event->operation,
            $event->user,
            $event->key
        );

        // 2. Apply WP filter: wpos_can_read / wpos_can_insert / wpos_can_update / wpos_can_delete
        $hook_name = "wpos_can_{$event->operation}";
        $context   = match( $event->operation ) {
            'update', 'delete' => [ $event->entity_set, $event->key, $event->user ],
            default             => [ $event->entity_set, $event->user ],
        };

        $granted = (bool) $this->bridge->filter( $hook_name, $granted, $context );

        // 3. Also apply wpos_allowed_properties filter for field-level permission
        //    (Result not used here — used by Field ACL. But fire it so plugins see it.)

        // 4. Write result back
        $event->granted = $granted;
    }
}
```

---

## File 2: `src/hooks/subscribers/class-wpos-subscriber-write-before.php`

Handles `WPOS_Event_Write_Before`.
- Checks permission via `WPOS_Permission_Engine`
- Validates field ACL on the payload
- Applies `wpos_before_insert` or `wpos_before_update` WP filter
- Sets `$event->cancelled = true` if permission denied

```php
class WPOS_Subscriber_Write_Before implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Permission_Engine $permissions,
        private WPOS_Field_ACL         $field_acl,
        private WPOS_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Write_Before::class;
    }

    public function handle( WPOS_Event $event ): void {
        /** @var WPOS_Event_Write_Before $event */

        // 1. Check entity-level permission
        $granted = $this->permissions->can(
            $event->entity_set, $event->operation, $event->user, $event->key
        );
        if ( ! $granted ) {
            $event->cancelled = true;
            return;
        }

        // 2. Validate field-level write permission
        if ( $event->operation !== 'delete' ) {
            $this->field_acl->validate_write(
                $event->payload, $event->entity_set, $event->user, $event->operation
            );
        }

        // 3. Apply WP filter to allow payload modification
        $hook = match( $event->operation ) {
            'insert' => 'wpos_before_insert',
            'update' => 'wpos_before_update',
            default  => null,
        };

        if ( $hook ) {
            $context = $event->operation === 'update'
                ? [ $event->entity_set, $event->key, $event->user ]
                : [ $event->entity_set, $event->user ];

            $event->payload = $this->bridge->filter( $hook, $event->payload, $context );
        }
    }
}
```

---

## File 3: `src/hooks/subscribers/class-wpos-subscriber-write-after.php`

Handles `WPOS_Event_Write_After`. Fires the appropriate WP action notification.

```php
class WPOS_Subscriber_Write_After implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Hook_Bridge $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Write_After::class;
    }

    public function handle( WPOS_Event $event ): void {
        /** @var WPOS_Event_Write_After $event */

        $hook    = "wpos_{$event->operation}d";   // wpos_inserted / wpos_updated / wpos_deleted
        $context = match( $event->operation ) {
            'delete' => [ $event->entity_set, $event->key ],
            default  => [ $event->entity_set, $event->key, $event->result ],
        };

        $this->bridge->action( $hook, $context );
    }
}
```

---

## `wpos_allow_public_access` Filter

The router must check unauthenticated access before dispatching permission events.
In `WPOS_Router`, before dispatching any request for a non-authenticated user:

```php
$allow_public = (bool) $bridge->filter(
    'wpos_allow_public_access',
    false,
    [ $entity_set, $method ]
);

if ( ! $allow_public && ! is_user_logged_in() ) {
    return WPOS_Error::forbidden( 'Authentication required.' );
}
```

---

## Bootstrapper Update

```php
new WPOS_Subscriber_Permission_Check(
    $c->get(WPOS_Permission_Engine::class),
    $c->get(WPOS_Hook_Bridge::class),
),
new WPOS_Subscriber_Write_Before(
    $c->get(WPOS_Permission_Engine::class),
    $c->get(WPOS_Field_ACL::class),
    $c->get(WPOS_Hook_Bridge::class),
),
new WPOS_Subscriber_Write_After(
    $c->get(WPOS_Hook_Bridge::class),
),
```

---

## Acceptance Criteria

- `wpos_can_read` filter is applied for every read request.
- `wpos_can_insert` filter is applied before every insert.
- External plugin returning `false` from `wpos_can_read` causes a 403 response.
- External plugin returning `true` from `wpos_can_read` for a normally-denied user grants access.
- `wpos_before_insert` filter fires with entity set, user as context arguments.
- `wpos_inserted` action fires after successful insert with entity set, new key, result payload.
- Write request with a read-only field in the payload is rejected with 400.
- Unauthenticated request to a protected entity set returns 403 unless `wpos_allow_public_access` returns true.
