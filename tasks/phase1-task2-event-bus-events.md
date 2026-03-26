# Task 1.2 — Event Bus + Interfaces + All Event Value Objects

## Dependencies
- Task 1.1 must be complete (plugin entry + autoloader + DI container).

## Goal
Build the internal event system: interfaces, the event bus dispatcher, and every
event value object used by the entire plugin. Events are pure PHP value objects —
no WordPress dependencies.

---

## Files to Create

### `src/events/interface-wpos-event.php`
Marker interface. All event value objects implement this.

```php
interface WPOS_Event {}
```

### `src/events/interface-wpos-stoppable-event.php`
```php
interface WPOS_Stoppable_Event extends WPOS_Event {
    public function is_stopped(): bool;
    public function stop_propagation(): void;
}
```

### `src/events/interface-wpos-event-listener.php`
```php
interface WPOS_Event_Listener {
    /** Return the fully-qualified class name of the event this listener handles. */
    public function get_event(): string;
    public function handle( WPOS_Event $event ): void;
}
```

### `src/events/class-wpos-event-bus.php`
```php
class WPOS_Event_Bus {
    /** @var array<string, WPOS_Event_Listener[]> */
    private array $listeners = [];

    public function subscribe( WPOS_Event_Listener $listener ): void {
        $this->listeners[ $listener->get_event() ][] = $listener;
    }

    public function dispatch( WPOS_Event $event ): WPOS_Event {
        foreach ( $this->listeners[ get_class( $event ) ] ?? [] as $listener ) {
            $listener->handle( $event );
            if ( $event instanceof WPOS_Stoppable_Event && $event->is_stopped() ) {
                break;
            }
        }
        return $event;
    }
}
```

---

## Event Value Objects

All files live in `src/events/events/`.

### Schema Events

**`class-wpos-event-wp-init.php`**
```php
class WPOS_Event_WP_Init implements WPOS_Event {}
```

**`class-wpos-event-rest-init.php`**
```php
class WPOS_Event_REST_Init implements WPOS_Event {}
```

**`class-wpos-event-schema-register.php`**
```php
class WPOS_Event_Schema_Register implements WPOS_Event {
    public function __construct(
        public WPOS_Schema_Registry $registry,
    ) {}
}
```

**`class-wpos-event-schema-changed.php`**
```php
class WPOS_Event_Schema_Changed implements WPOS_Event {
    // $reason: 'entity_registered' | 'config_updated' | 'entity_removed'
    public function __construct(
        public string $reason,
        public string $entity_set,
    ) {}
}
```

**`class-wpos-event-metadata-build.php`**
```php
class WPOS_Event_Metadata_Build implements WPOS_Event {
    public function __construct(
        public array $entity_types,   // mutable
        public array $entity_sets,    // mutable
    ) {}
}
```

---

### Query Events

**`class-wpos-event-query-before.php`**
```php
class WPOS_Event_Query_Before implements WPOS_Event {
    public function __construct(
        public string             $entity_set,
        public \WP_User           $user,
        public WPOS_Query_Context $query_context,   // mutable
    ) {}
}
```

**`class-wpos-event-query-after.php`**
```php
class WPOS_Event_Query_After implements WPOS_Event {
    public function __construct(
        public string             $entity_set,
        public \WP_User           $user,
        public WPOS_Query_Context $query_context,
        public array              $results,          // mutable
    ) {}
}
```

---

### Standard Write Events

**`class-wpos-event-write-before.php`**
```php
class WPOS_Event_Write_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $entity_set,
        public string   $operation,   // 'insert' | 'update' | 'delete'
        public \WP_User $user,
        public array    $payload,     // mutable
        public mixed    $key = null,
    ) {}
}
```

**`class-wpos-event-write-after.php`**
```php
class WPOS_Event_Write_After implements WPOS_Event {
    public function __construct(
        public string   $entity_set,
        public string   $operation,
        public \WP_User $user,
        public mixed    $key,
        public array    $result,
    ) {}
}
```

---

### Deep Insert Events

**`class-wpos-event-deep-insert-before.php`**
```php
class WPOS_Event_Deep_Insert_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $entity_set,
        public \WP_User $user,
        public array    $payload,        // full nested payload, mutable
    ) {}
}
```

**`class-wpos-event-deep-insert-nested-before.php`**
```php
class WPOS_Event_Deep_Insert_Nested_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $parent_entity_set,
        public string   $nested_entity_set,
        public string   $nav_property,
        public \WP_User $user,
        public array    $nested_payload,  // mutable
    ) {}
}
```

**`class-wpos-event-deep-insert-after.php`**
```php
class WPOS_Event_Deep_Insert_After implements WPOS_Event {
    public function __construct(
        public string   $entity_set,
        public \WP_User $user,
        public mixed    $key,
        public array    $result,
    ) {}
}
```

---

### Deep Update Events

**`class-wpos-event-deep-update-before.php`**
```php
class WPOS_Event_Deep_Update_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $entity_set,
        public mixed    $key,
        public \WP_User $user,
        public array    $payload,        // full delta payload, mutable
    ) {}
}
```

**`class-wpos-event-deep-update-nested-before.php`**
```php
class WPOS_Event_Deep_Update_Nested_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $parent_entity_set,
        public string   $nested_entity_set,
        public string   $operation,        // 'insert' | 'update' | 'delete'
        public \WP_User $user,
        public array    $nested_payload,   // mutable
        public mixed    $nested_key = null,
    ) {}
}
```

**`class-wpos-event-deep-update-after.php`**
```php
class WPOS_Event_Deep_Update_After implements WPOS_Event {
    public function __construct(
        public string   $entity_set,
        public mixed    $key,
        public \WP_User $user,
        public array    $result,
    ) {}
}
```

---

### Set-Based Operation Events

**`class-wpos-event-set-operation-before.php`**
```php
class WPOS_Event_Set_Operation_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string             $entity_set,
        public string             $operation,     // 'patch' | 'delete' | 'action'
        public \WP_User           $user,
        public WPOS_Query_Context $filter_ctx,    // mutable
        public array              $payload,       // mutable
    ) {}
}
```

**`class-wpos-event-set-operation-after.php`**
```php
class WPOS_Event_Set_Operation_After implements WPOS_Event {
    public function __construct(
        public string   $entity_set,
        public string   $operation,
        public \WP_User $user,
        public int      $affected_count,
    ) {}
}
```

---

### Permission Check Event

**`class-wpos-event-permission-check.php`**
```php
class WPOS_Event_Permission_Check implements WPOS_Event {
    public function __construct(
        public string   $entity_set,
        public string   $operation,   // 'read' | 'insert' | 'update' | 'delete'
        public \WP_User $user,
        public bool     $granted,     // initial result from capability map, mutable
        public mixed    $key = null,
    ) {}
}
```

---

### Admin Events

**`class-wpos-event-admin-entity-config-saved.php`**
```php
class WPOS_Event_Admin_Entity_Config_Saved implements WPOS_Event {
    public function __construct(
        public string $entity_set,
        public array  $config,
    ) {}
}
```

**`class-wpos-event-admin-permission-saved.php`**
```php
class WPOS_Event_Admin_Permission_Saved implements WPOS_Event {
    public function __construct(
        public string $entity_set,
        public array  $permissions,
    ) {}
}
```

---

## Architecture Rules to Enforce

- Event classes are **value objects**: no methods other than a constructor.
- No WordPress function calls inside any event class.
- `WPOS_Query_Context` referenced in events is built in Phase 3 (Task 3.5).
  Use a forward-declared stub class for now if needed, or just use `array` typed
  properties until Phase 3 lands. Prefer the typed class — it will be created then.
- The event bus dispatches by `get_class($event)` — listener must return the exact
  FQCN from `get_event()`.

---

## Acceptance Criteria

- All event files load without errors.
- `WPOS_Event_Bus::dispatch()` calls every registered listener for the event class.
- `WPOS_Event_Bus` stops dispatch when a `WPOS_Stoppable_Event` returns `is_stopped() === true`.
- No WordPress API calls in any of these files.
