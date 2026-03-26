# Task 1.3 — Hook Bridge + Subscriber Interface + Empty Subscriber Stubs

## Dependencies
- Task 1.1 (plugin entry + DI container)
- Task 1.2 (event bus + interfaces)

## Goal
Implement `WPOS_Hook_Bridge` — the **only** class in the entire codebase allowed to call
`add_action`, `add_filter`, `apply_filters`, and `do_action`. Then create empty stub
implementations for every subscriber that will be fleshed out in later tasks.

---

## Key Architecture Rule

```
WordPress (outer) → Hook Bridge → Event Bus → Domain Services (inner)
```

- `apply_filters()` and `add_filter()` exist **ONLY** in `WPOS_Hook_Bridge`.
- Domain services dispatch `WPOS_Event` objects — never call WP hook functions directly.
- Subscribers are thin bridges: one internal event → one domain call → one WP filter exposure.

---

## Files to Create

### `src/hooks/class-wpos-hook-bridge.php`

```php
class WPOS_Hook_Bridge {

    public function __construct( private WPOS_Event_Bus $event_bus ) {}

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
        add_action( 'wpos_register_entity_sets', '__return_null', 1 );
        add_action( 'wpos_register_permissions', '__return_null', 1 );
        add_action( 'wpos_register_functions',   '__return_null', 1 );
        add_action( 'wpos_register_actions',     '__return_null', 1 );

        // Schema change listeners for cache invalidation
        add_action( 'activated_plugin',   [ $this, 'on_plugin_changed' ] );
        add_action( 'deactivated_plugin', [ $this, 'on_plugin_changed' ] );
    }

    public function on_wp_init(): void {
        $this->event_bus->dispatch( new WPOS_Event_WP_Init() );
    }

    public function on_rest_api_init(): void {
        $this->event_bus->dispatch( new WPOS_Event_REST_Init() );
    }

    public function on_plugin_changed(): void {
        $this->event_bus->dispatch( new WPOS_Event_Schema_Changed(
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
Each stub must implement `WPOS_Event_Listener` but `handle()` can be an empty method
body for now. The correct `get_event()` return value must be set.

### Stubs to create

| File | get_event() return |
|---|---|
| `class-wpos-subscriber-schema-init.php` | `WPOS_Event_WP_Init::class` |
| `class-wpos-subscriber-schema-changed.php` | `WPOS_Event_Schema_Changed::class` |
| `class-wpos-subscriber-permission-check.php` | `WPOS_Event_Permission_Check::class` |
| `class-wpos-subscriber-query-before.php` | `WPOS_Event_Query_Before::class` |
| `class-wpos-subscriber-query-after.php` | `WPOS_Event_Query_After::class` |
| `class-wpos-subscriber-write-before.php` | `WPOS_Event_Write_Before::class` |
| `class-wpos-subscriber-write-after.php` | `WPOS_Event_Write_After::class` |
| `class-wpos-subscriber-deep-insert.php` | `WPOS_Event_Deep_Insert_Before::class` |
| `class-wpos-subscriber-deep-update.php` | `WPOS_Event_Deep_Update_Before::class` |
| `class-wpos-subscriber-set-operation.php` | `WPOS_Event_Set_Operation_Before::class` |
| `class-wpos-subscriber-metadata-build.php` | `WPOS_Event_Metadata_Build::class` |
| `class-wpos-subscriber-admin-config-saved.php` | `WPOS_Event_Admin_Entity_Config_Saved::class` |

### Pattern for each stub

```php
class WPOS_Subscriber_Schema_Init implements WPOS_Event_Listener {

    public function __construct(
        // Constructor arguments vary per subscriber — declare them now as stubs
        // but populate in the implementing task.
    ) {}

    public function get_event(): string {
        return WPOS_Event_WP_Init::class;
    }

    public function handle( WPOS_Event $event ): void {
        // Implemented in Phase 2 Task 6
    }
}
```

---

## Bootstrapper Update

In `class-wpos-bootstrapper.php` (from Task 1.1), add all subscriber registrations
to `register_subscribers()`. Each subscriber must be constructed and passed to
`$bus->subscribe()`. Use empty constructors for now (no dependencies required yet).

---

## Canonical Hook Names (for reference, used in later subscriber implementations)

### Actions
- `wpos_register_entity_sets` — register custom entity sets
- `wpos_register_permissions` — register permission rules
- `wpos_register_functions` — register OData functions
- `wpos_register_actions` — register OData actions
- `wpos_inserted` / `wpos_updated` / `wpos_deleted`
- `wpos_deep_inserted` / `wpos_deep_updated`
- `wpos_set_operation_completed`
- `wpos_admin_entity_config_saved` / `wpos_admin_permission_saved`

### Filters
- `wpos_can_read` / `wpos_can_insert` / `wpos_can_update` / `wpos_can_delete`
- `wpos_allowed_properties`
- `wpos_allow_public_access`
- `wpos_query_context` / `wpos_query_results` / `wpos_filter_sql`
- `wpos_before_insert` / `wpos_before_update`
- `wpos_before_deep_insert` / `wpos_before_deep_update`
- `wpos_before_set_operation`
- `wpos_nested_entity_payload`
- `wpos_entity_type_definition`
- `wpos_metadata_entity_types` / `wpos_metadata_entity_sets`
- `wpos_response_payload`

---

## Acceptance Criteria

- `WPOS_Hook_Bridge::register()` is the **only** place in the codebase with `add_action` / `add_filter`.
- `WPOS_Hook_Bridge::filter()` and `::action()` are the only methods calling `apply_filters` / `do_action`.
- All 12 subscriber stub files exist and implement `WPOS_Event_Listener`.
- Each stub's `get_event()` returns the correct event class name.
- Bootstrapper registers all subscribers into the event bus.
- No WP hook calls outside `WPOS_Hook_Bridge`.
