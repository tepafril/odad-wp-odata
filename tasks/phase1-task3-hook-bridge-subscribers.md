# Task 1.3 — Hook Bridge + Subscriber Interface + Empty Subscriber Stubs

## Dependencies
- Task 1.1 (plugin entry + DI container)
- Task 1.2 (event bus + interfaces)

## Goal
Implement `ODAD_Hook_Bridge` — the **only** class in the entire codebase allowed to call
`add_action`, `add_filter`, `apply_filters`, and `do_action`. Then create empty stub
implementations for every subscriber that will be fleshed out in later tasks.

---

## Key Architecture Rule

```
WordPress (outer) → Hook Bridge → Event Bus → Domain Services (inner)
```

- `apply_filters()` and `add_filter()` exist **ONLY** in `ODAD_Hook_Bridge`.
- Domain services dispatch `ODAD_Event` objects — never call WP hook functions directly.
- Subscribers are thin bridges: one internal event → one domain call → one WP filter exposure.

---

## Files to Create

### `src/hooks/class-odad-hook-bridge.php`

```php
class ODAD_Hook_Bridge {

    public function __construct( private ODAD_Event_Bus $event_bus ) {}

    /**
     * Called once at plugins_loaded (priority 5).
     * COMPLETE list of WP hooks this plugin registers.
     */
    public function register(): void {
        // WordPress lifecycle
        add_action( 'init',          [ $this, 'on_wp_init' ] );
        add_action( 'rest_api_init', [ $this, 'on_rest_api_init' ] );

        // Plugin registration extension points.
        // Priority 1: external plugins at default priority 10 arrive after.
        add_action( 'ODAD_register_entity_sets', '__return_null', 1 );
        add_action( 'ODAD_register_permissions', '__return_null', 1 );
        add_action( 'ODAD_register_functions',   '__return_null', 1 );
        add_action( 'ODAD_register_actions',     '__return_null', 1 );

        // Schema change listeners for cache invalidation
        add_action( 'activated_plugin',   [ $this, 'on_plugin_changed' ] );
        add_action( 'deactivated_plugin', [ $this, 'on_plugin_changed' ] );
    }

    public function on_wp_init(): void {
        $this->event_bus->dispatch( new ODAD_Event_WP_Init() );
    }

    public function on_rest_api_init(): void {
        $this->event_bus->dispatch( new ODAD_Event_REST_Init() );
    }

    public function on_plugin_changed(): void {
        $this->event_bus->dispatch( new ODAD_Event_Schema_Changed(
            reason:     'entity_registered',
            entity_set: '*',
        ) );
    }

    /** Expose a WP filter as a public extension point. */
    public function filter( string $hook, mixed $value, array $context = [] ): mixed {
        return apply_filters( $hook, $value, ...$context );
    }

    /** Fire a WP action as a public notification. */
    public function action( string $hook, array $context = [] ): void {
        do_action( $hook, ...$context );
    }
}
```

---

## Subscriber Stubs

Create one file per subscriber in `src/hooks/subscribers/`.
Each stub must implement `ODAD_Event_Listener` but `handle()` can be an empty method
body for now. The correct `get_event()` return value must be set.

### Stubs to create

| File | get_event() return |
|---|---|
| `class-odad-subscriber-schema-init.php` | `ODAD_Event_WP_Init::class` |
| `class-odad-subscriber-schema-changed.php` | `ODAD_Event_Schema_Changed::class` |
| `class-odad-subscriber-permission-check.php` | `ODAD_Event_Permission_Check::class` |
| `class-odad-subscriber-query-before.php` | `ODAD_Event_Query_Before::class` |
| `class-odad-subscriber-query-after.php` | `ODAD_Event_Query_After::class` |
| `class-odad-subscriber-write-before.php` | `ODAD_Event_Write_Before::class` |
| `class-odad-subscriber-write-after.php` | `ODAD_Event_Write_After::class` |
| `class-odad-subscriber-deep-insert.php` | `ODAD_Event_Deep_Insert_Before::class` |
| `class-odad-subscriber-deep-update.php` | `ODAD_Event_Deep_Update_Before::class` |
| `class-odad-subscriber-set-operation.php` | `ODAD_Event_Set_Operation_Before::class` |
| `class-odad-subscriber-metadata-build.php` | `ODAD_Event_Metadata_Build::class` |
| `class-odad-subscriber-admin-config-saved.php` | `ODAD_Event_Admin_Entity_Config_Saved::class` |

### Pattern for each stub

```php
class ODAD_Subscriber_Schema_Init implements ODAD_Event_Listener {

    public function __construct(
        // Constructor arguments vary per subscriber — declare them now as stubs
        // but populate in the implementing task.
    ) {}

    public function get_event(): string {
        return ODAD_Event_WP_Init::class;
    }

    public function handle( ODAD_Event $event ): void {
        // Implemented in Phase 2 Task 6
    }
}
```

---

## Bootstrapper Update

In `class-odad-bootstrapper.php` (from Task 1.1), add all subscriber registrations
to `register_subscribers()`. Each subscriber must be constructed and passed to
`$bus->subscribe()`. Use empty constructors for now (no dependencies required yet).

---

## Canonical Hook Names (for reference, used in later subscriber implementations)

### Actions
- `ODAD_register_entity_sets` — register custom entity sets
- `ODAD_register_permissions` — register permission rules
- `ODAD_register_functions` — register OData functions
- `ODAD_register_actions` — register OData actions
- `ODAD_inserted` / `ODAD_updated` / `ODAD_deleted`
- `ODAD_deep_inserted` / `ODAD_deep_updated`
- `ODAD_set_operation_completed`
- `ODAD_admin_entity_config_saved` / `ODAD_admin_permission_saved`

### Filters
- `ODAD_can_read` / `ODAD_can_insert` / `ODAD_can_update` / `ODAD_can_delete`
- `ODAD_allowed_properties`
- `ODAD_allow_public_access`
- `ODAD_query_context` / `ODAD_query_results` / `ODAD_filter_sql`
- `ODAD_before_insert` / `ODAD_before_update`
- `ODAD_before_deep_insert` / `ODAD_before_deep_update`
- `ODAD_before_set_operation`
- `ODAD_nested_entity_payload`
- `ODAD_entity_type_definition`
- `ODAD_metadata_entity_types` / `ODAD_metadata_entity_sets`
- `ODAD_response_payload`

---

## Acceptance Criteria

- `ODAD_Hook_Bridge::register()` is the **only** place in the codebase with `add_action` / `add_filter`.
- `ODAD_Hook_Bridge::filter()` and `::action()` are the only methods calling `apply_filters` / `do_action`.
- All 12 subscriber stub files exist and implement `ODAD_Event_Listener`.
- Each stub's `get_event()` returns the correct event class name.
- Bootstrapper registers all subscribers into the event bus.
- No WP hook calls outside `ODAD_Hook_Bridge`.
