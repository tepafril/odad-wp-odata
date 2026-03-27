# Task 6.2 — Admin Entity Config UI + Save Flow

## Dependencies
- Task 6.1 (admin dashboard + menu structure)
- Task 1.2 (ODAD_Event_Admin_Entity_Config_Saved + ODAD_Event_Schema_Changed)
- Task 1.3 (ODAD_Subscriber_Admin_Config_Saved stub)

## Goal
Build the entity configuration admin page where administrators can configure
settings per entity set (e.g. enable/disable, custom labels, exposed properties).
The save flow must route through the event bus and trigger cache invalidation.

---

## File

### `src/admin/class-odad-admin-entity-config.php`

```php
class ODAD_Admin_Entity_Config {

    public function __construct(
        private ODAD_Schema_Registry $registry,
        private ODAD_Event_Bus       $event_bus,
    ) {}

    /** Render the entity configuration page. */
    public function render(): void;

    /**
     * Handle form submission.
     * Called via WP admin_post_{action} hook.
     */
    public function save(): void;

    /**
     * Get configuration for an entity set.
     * Config stored in WP option 'ODAD_entity_config_{entity_set}'.
     */
    public function get_config( string $entity_set ): array;
}
```

---

## Configuration Fields per Entity Set

| Field | Type | Default | Description |
|---|---|---|---|
| `enabled` | bool | true | Whether this entity set is exposed via OData |
| `label` | string | entity set name | Human-readable label in $metadata |
| `exposed_properties` | array | all | Which properties are exposed (empty = all) |
| `allow_insert` | bool | true | Whether POST is allowed |
| `allow_update` | bool | true | Whether PATCH/PUT is allowed |
| `allow_delete` | bool | true | Whether DELETE is allowed |
| `max_top` | int | 1000 | Maximum $top allowed for this entity set |
| `require_auth` | bool | true | Whether authentication is required |

---

## Configuration Storage

Store per-entity config as WP options:
```php
get_option( 'ODAD_entity_config_Posts' );
update_option( 'ODAD_entity_config_Posts', $config );
```

---

## Save Flow (Critical — must use event bus)

```php
public function save(): void {
    // 1. Verify nonce (CSRF protection)
    check_admin_referer( 'ODAD_entity_config_save' );

    // 2. Validate user capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    // 3. Sanitize and validate input
    $entity_set = sanitize_text_field( $_POST['entity_set'] );
    $config     = $this->sanitize_config( $_POST['config'] ?? [] );

    // 4. Persist
    update_option( "ODAD_entity_config_{$entity_set}", $config );

    // 5. Dispatch event (triggers cache invalidation + WP action)
    $this->event_bus->dispatch( new ODAD_Event_Admin_Entity_Config_Saved(
        entity_set: $entity_set,
        config:     $config,
    ));

    // 6. Redirect back with success message
    wp_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
    exit;
}
```

---

## Subscriber: `class-odad-subscriber-admin-config-saved.php`

Flesh out the stub from Task 1.3:

```php
class ODAD_Subscriber_Admin_Config_Saved implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Hook_Bridge $bridge,
        private ODAD_Event_Bus   $event_bus,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Admin_Entity_Config_Saved::class;
    }

    public function handle( ODAD_Event $event ): void {
        /** @var ODAD_Event_Admin_Entity_Config_Saved $event */

        // 1. Fire WP action so external plugins can react
        $this->bridge->action( 'ODAD_admin_entity_config_saved', [
            $event->entity_set,
            $event->config,
        ]);

        // 2. Trigger schema change → metadata cache is busted automatically
        $this->event_bus->dispatch( new ODAD_Event_Schema_Changed(
            reason:     'config_updated',
            entity_set: $event->entity_set,
        ));
    }
}
```

---

## Bootstrapper Update

```php
$c->singleton( ODAD_Admin_Entity_Config::class, fn($c) => new ODAD_Admin_Entity_Config(
    $c->get(ODAD_Schema_Registry::class),
    $c->get(ODAD_Event_Bus::class),
));
```

Add `admin_post_ODAD_save_entity_config` hook in `ODAD_Hook_Bridge`:
```php
add_action( 'admin_post_ODAD_save_entity_config',
    fn() => ODAD_container()->get(ODAD_Admin_Entity_Config::class)->save()
);
```

---

## Acceptance Criteria

- Entity config page lists all registered entity sets with their current settings.
- Saving config updates the WP option and dispatches `ODAD_Event_Admin_Entity_Config_Saved`.
- `ODAD_admin_entity_config_saved` WP action fires after save.
- Metadata cache transients are deleted after save.
- CSRF nonce is validated on save.
- `manage_options` capability is checked before rendering and saving.
- Disabled entity set (`enabled = false`) returns 404 for all OData requests.
