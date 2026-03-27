# Task 4.3 — Permission Check, Write Before, Write After Subscribers

## Dependencies
- Task 1.3 (subscriber stubs)
- Task 4.1 (ODAD_Permission_Engine)
- Task 4.2 (ODAD_Field_ACL)

## Goal
Implement the three permission-related subscribers that bridge the event bus
to WP filter hooks and the permission/ACL domain services.

---

## File 1: `src/hooks/subscribers/class-odad-subscriber-permission-check.php`

Handles `ODAD_Event_Permission_Check`. Applies the `ODAD_can_{operation}` WP filter
so external plugins can override the permission decision.

```php
class ODAD_Subscriber_Permission_Check implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Permission_Engine $permissions,
        private ODAD_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Permission_Check::class;
    }

    public function handle( ODAD_Event $event ): void {
        /** @var ODAD_Event_Permission_Check $event */

        // 1. Domain logic: check WP capability
        $granted = $this->permissions->can(
            $event->entity_set,
            $event->operation,
            $event->user,
            $event->key
        );

        // 2. Apply WP filter: ODAD_can_read / ODAD_can_insert / ODAD_can_update / ODAD_can_delete
        $hook_name = "ODAD_can_{$event->operation}";
        $context   = match( $event->operation ) {
            'update', 'delete' => [ $event->entity_set, $event->key, $event->user ],
            default             => [ $event->entity_set, $event->user ],
        };

        $granted = (bool) $this->bridge->filter( $hook_name, $granted, $context );

        // 3. Also apply ODAD_allowed_properties filter for field-level permission
        //    (Result not used here — used by Field ACL. But fire it so plugins see it.)

        // 4. Write result back
        $event->granted = $granted;
    }
}
```

---

## File 2: `src/hooks/subscribers/class-odad-subscriber-write-before.php`

Handles `ODAD_Event_Write_Before`.
- Checks permission via `ODAD_Permission_Engine`
- Validates field ACL on the payload
- Applies `ODAD_before_insert` or `ODAD_before_update` WP filter
- Sets `$event->cancelled = true` if permission denied

```php
class ODAD_Subscriber_Write_Before implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Permission_Engine $permissions,
        private ODAD_Field_ACL         $field_acl,
        private ODAD_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Write_Before::class;
    }

    public function handle( ODAD_Event $event ): void {
        /** @var ODAD_Event_Write_Before $event */

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
            'insert' => 'ODAD_before_insert',
            'update' => 'ODAD_before_update',
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

## File 3: `src/hooks/subscribers/class-odad-subscriber-write-after.php`

Handles `ODAD_Event_Write_After`. Fires the appropriate WP action notification.

```php
class ODAD_Subscriber_Write_After implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Hook_Bridge $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Write_After::class;
    }

    public function handle( ODAD_Event $event ): void {
        /** @var ODAD_Event_Write_After $event */

        $hook    = "ODAD_{$event->operation}d";   // ODAD_inserted / ODAD_updated / ODAD_deleted
        $context = match( $event->operation ) {
            'delete' => [ $event->entity_set, $event->key ],
            default  => [ $event->entity_set, $event->key, $event->result ],
        };

        $this->bridge->action( $hook, $context );
    }
}
```

---

## `ODAD_allow_public_access` Filter

The router must check unauthenticated access before dispatching permission events.
In `ODAD_Router`, before dispatching any request for a non-authenticated user:

```php
$allow_public = (bool) $bridge->filter(
    'ODAD_allow_public_access',
    false,
    [ $entity_set, $method ]
);

if ( ! $allow_public && ! is_user_logged_in() ) {
    return ODAD_Error::forbidden( 'Authentication required.' );
}
```

---

## Bootstrapper Update

```php
new ODAD_Subscriber_Permission_Check(
    $c->get(ODAD_Permission_Engine::class),
    $c->get(ODAD_Hook_Bridge::class),
),
new ODAD_Subscriber_Write_Before(
    $c->get(ODAD_Permission_Engine::class),
    $c->get(ODAD_Field_ACL::class),
    $c->get(ODAD_Hook_Bridge::class),
),
new ODAD_Subscriber_Write_After(
    $c->get(ODAD_Hook_Bridge::class),
),
```

---

## Acceptance Criteria

- `ODAD_can_read` filter is applied for every read request.
- `ODAD_can_insert` filter is applied before every insert.
- External plugin returning `false` from `ODAD_can_read` causes a 403 response.
- External plugin returning `true` from `ODAD_can_read` for a normally-denied user grants access.
- `ODAD_before_insert` filter fires with entity set, user as context arguments.
- `ODAD_inserted` action fires after successful insert with entity set, new key, result payload.
- Write request with a read-only field in the payload is rejected with 400.
- Unauthenticated request to a protected entity set returns 403 unless `ODAD_allow_public_access` returns true.
