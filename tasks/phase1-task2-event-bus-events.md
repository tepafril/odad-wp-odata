# Task 1.2 — Event Bus + Interfaces + All Event Value Objects

## Dependencies
- Task 1.1 must be complete (plugin entry + autoloader + DI container).

## Goal
Build the internal event system: interfaces, the event bus dispatcher, and every
event value object used by the entire plugin. Events are pure PHP value objects —
no WordPress dependencies.

---

## Files to Create

### `src/events/interface-odad-event.php`
Marker interface. All event value objects implement this.

```php
interface ODAD_Event {}
```

### `src/events/interface-odad-stoppable-event.php`
```php
interface ODAD_Stoppable_Event extends ODAD_Event {
    public function is_stopped(): bool;
    public function stop_propagation(): void;
}
```

### `src/events/interface-odad-event-listener.php`
```php
interface ODAD_Event_Listener {
    /** Return the fully-qualified class name of the event this listener handles. */
    public function get_event(): string;
    public function handle( ODAD_Event $event ): void;
}
```

### `src/events/class-odad-event-bus.php`
```php
class ODAD_Event_Bus {
    /** @var array<string, ODAD_Event_Listener[]> */
    private array $listeners = [];

    public function subscribe( ODAD_Event_Listener $listener ): void {
        $this->listeners[ $listener->get_event() ][] = $listener;
    }

    public function dispatch( ODAD_Event $event ): ODAD_Event {
        foreach ( $this->listeners[ get_class( $event ) ] ?? [] as $listener ) {
            $listener->handle( $event );
            if ( $event instanceof ODAD_Stoppable_Event && $event->is_stopped() ) {
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

**`class-odad-event-wp-init.php`**
```php
class ODAD_Event_WP_Init implements ODAD_Event {}
```

**`class-odad-event-rest-init.php`**
```php
class ODAD_Event_REST_Init implements ODAD_Event {}
```

**`class-odad-event-schema-register.php`**
```php
class ODAD_Event_Schema_Register implements ODAD_Event {
    public function __construct(
        public ODAD_Schema_Registry $registry,
    ) {}
}
```

**`class-odad-event-schema-changed.php`**
```php
class ODAD_Event_Schema_Changed implements ODAD_Event {
    // $reason: 'entity_registered' | 'config_updated' | 'entity_removed'
    public function __construct(
        public string $reason,
        public string $entity_set,
    ) {}
}
```

**`class-odad-event-metadata-build.php`**
```php
class ODAD_Event_Metadata_Build implements ODAD_Event {
    public function __construct(
        public array $entity_types,   // mutable
        public array $entity_sets,    // mutable
    ) {}
}
```

---

### Query Events

**`class-odad-event-query-before.php`**
```php
class ODAD_Event_Query_Before implements ODAD_Event {
    public function __construct(
        public string             $entity_set,
        public \WP_User           $user,
        public ODAD_Query_Context $query_context,   // mutable
    ) {}
}
```

**`class-odad-event-query-after.php`**
```php
class ODAD_Event_Query_After implements ODAD_Event {
    public function __construct(
        public string             $entity_set,
        public \WP_User           $user,
        public ODAD_Query_Context $query_context,
        public array              $results,          // mutable
    ) {}
}
```

---

### Standard Write Events

**`class-odad-event-write-before.php`**
```php
class ODAD_Event_Write_Before implements ODAD_Event {
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

**`class-odad-event-write-after.php`**
```php
class ODAD_Event_Write_After implements ODAD_Event {
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

**`class-odad-event-deep-insert-before.php`**
```php
class ODAD_Event_Deep_Insert_Before implements ODAD_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $entity_set,
        public \WP_User $user,
        public array    $payload,        // full nested payload, mutable
    ) {}
}
```

**`class-odad-event-deep-insert-nested-before.php`**
```php
class ODAD_Event_Deep_Insert_Nested_Before implements ODAD_Event {
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

**`class-odad-event-deep-insert-after.php`**
```php
class ODAD_Event_Deep_Insert_After implements ODAD_Event {
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

**`class-odad-event-deep-update-before.php`**
```php
class ODAD_Event_Deep_Update_Before implements ODAD_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $entity_set,
        public mixed    $key,
        public \WP_User $user,
        public array    $payload,        // full delta payload, mutable
    ) {}
}
```

**`class-odad-event-deep-update-nested-before.php`**
```php
class ODAD_Event_Deep_Update_Nested_Before implements ODAD_Event {
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

**`class-odad-event-deep-update-after.php`**
```php
class ODAD_Event_Deep_Update_After implements ODAD_Event {
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

**`class-odad-event-set-operation-before.php`**
```php
class ODAD_Event_Set_Operation_Before implements ODAD_Event {
    public bool $cancelled = false;

    public function __construct(
        public string             $entity_set,
        public string             $operation,     // 'patch' | 'delete' | 'action'
        public \WP_User           $user,
        public ODAD_Query_Context $filter_ctx,    // mutable
        public array              $payload,       // mutable
    ) {}
}
```

**`class-odad-event-set-operation-after.php`**
```php
class ODAD_Event_Set_Operation_After implements ODAD_Event {
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

**`class-odad-event-permission-check.php`**
```php
class ODAD_Event_Permission_Check implements ODAD_Event {
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

**`class-odad-event-admin-entity-config-saved.php`**
```php
class ODAD_Event_Admin_Entity_Config_Saved implements ODAD_Event {
    public function __construct(
        public string $entity_set,
        public array  $config,
    ) {}
}
```

**`class-odad-event-admin-permission-saved.php`**
```php
class ODAD_Event_Admin_Permission_Saved implements ODAD_Event {
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
- `ODAD_Query_Context` referenced in events is built in Phase 3 (Task 3.5).
  Use a forward-declared stub class for now if needed, or just use `array` typed
  properties until Phase 3 lands. Prefer the typed class — it will be created then.
- The event bus dispatches by `get_class($event)` — listener must return the exact
  FQCN from `get_event()`.

---

## Acceptance Criteria

- All event files load without errors.
- `ODAD_Event_Bus::dispatch()` calls every registered listener for the event class.
- `ODAD_Event_Bus` stops dispatch when a `ODAD_Stoppable_Event` returns `is_stopped() === true`.
- No WordPress API calls in any of these files.
