# Task 6.3 — Admin Permission Config UI (Role × Entity × Operation Grid)

## Dependencies
- Task 6.1 (admin menu)
- Task 4.1 (ODAD_Capability_Map — reads and writes capability rules)
- Task 1.2 (ODAD_Event_Admin_Permission_Saved)

## Goal
Build the permission configuration admin page — a matrix grid showing
WordPress roles × entity sets × operations (read/insert/update/delete),
allowing administrators to customize what each role can do.

---

## File

### `src/admin/class-wpos-admin-permission-config.php`

```php
class ODAD_Admin_Permission_Config {

    public function __construct(
        private ODAD_Schema_Registry $registry,
        private ODAD_Capability_Map  $capability_map,
        private ODAD_Event_Bus       $event_bus,
    ) {}

    public function render(): void;
    public function save(): void;

    /** Get saved permission config for an entity set. */
    public function get_permissions( string $entity_set ): array;
}
```

---

## UI Layout

The permission grid looks like:

```
Entity Set  | Role          | read | insert | update | delete
------------|---------------|------|--------|--------|-------
Posts       | Administrator |  ✓   |   ✓    |   ✓    |   ✓
Posts       | Editor        |  ✓   |   ✓    |   ✓    |   ✓
Posts       | Author        |  ✓   |   ✓    |        |
Posts       | Subscriber    |  ✓   |        |        |
Users       | Administrator |  ✓   |   ✓    |   ✓    |   ✓
Users       | Editor        |  ✓   |        |        |
...
```

Checkboxes: ticked = granted, unticked = denied.

The grid is rendered as an HTML table with WP Settings API form elements.

---

## Data Storage

Store as a WP option per entity set:
```php
// Stored as: option name = 'ODAD_permissions_{entity_set}'
[
    'administrator' => [ 'read' => true, 'insert' => true, 'update' => true, 'delete' => true ],
    'editor'        => [ 'read' => true, 'insert' => true, 'update' => true, 'delete' => false ],
    'author'        => [ 'read' => true, 'insert' => true, 'update' => false, 'delete' => false ],
    'subscriber'    => [ 'read' => true, 'insert' => false, 'update' => false, 'delete' => false ],
]
```

---

## Integration with ODAD_Capability_Map

After saving permissions, `ODAD_Capability_Map` must be updated so that runtime
permission checks reflect the admin-configured rules.

On plugin init (in `ODAD_Subscriber_Schema_Init`), load saved permissions from options
and call `$capability_map->register_role_overrides($entity_set, $role_permissions)`.

Add a method to `ODAD_Capability_Map`:
```php
public function register_role_overrides( string $entity_set, array $role_permissions ): void;
```

Runtime `can()` checks role overrides before falling back to WP capability checks.

---

## Save Flow

```php
public function save(): void {
    check_admin_referer( 'ODAD_permission_config_save' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    $entity_set  = sanitize_text_field( $_POST['entity_set'] );
    $permissions = $this->sanitize_permissions( $_POST['permissions'] ?? [] );

    update_option( "ODAD_permissions_{$entity_set}", $permissions );

    $this->event_bus->dispatch( new ODAD_Event_Admin_Permission_Saved(
        entity_set:  $entity_set,
        permissions: $permissions,
    ));

    wp_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
    exit;
}
```

Add a `ODAD_Subscriber_Admin_Config_Saved` listener for `ODAD_Event_Admin_Permission_Saved`
that fires `ODAD_admin_permission_saved` WP action and dispatches `ODAD_Event_Schema_Changed`.

---

## Acceptance Criteria

- Permission grid displays all entity sets × all WP roles.
- Saving updates the WP option and dispatches `ODAD_Event_Admin_Permission_Saved`.
- `ODAD_admin_permission_saved` WP action fires after save.
- Runtime permission checks respect admin-saved role overrides.
- Administrator role cannot be fully locked out of read access (enforce a minimum).
- CSRF nonce validated on save.
- `manage_options` capability required.
