# Task 6.1 — Admin Dashboard + WP Admin Menu

## Dependencies
- Task 1.1 (plugin entry point)
- Task 1.3 (hook bridge — for admin hooks)
- Task 2.6 (schema registry populated with entity sets)

## Goal
Build the WordPress admin UI entry point: the dashboard page and admin menu.
The admin UI must route through the Hook Bridge — no `apply_filters()` calls
directly in admin classes.

---

## File

### `src/admin/class-odad-admin.php`

```php
class ODAD_Admin {

    public function __construct(
        private ODAD_Schema_Registry $registry,
        private ODAD_Event_Bus       $event_bus,
    ) {}

    /**
     * Register admin menu pages.
     * Called via WP 'admin_menu' action (registered in ODAD_Hook_Bridge).
     */
    public function register_menu(): void;

    /** Render the main dashboard page. */
    public function render_dashboard(): void;
}
```

---

## Menu Structure

```
WP-OData Suite (main menu item)
  ├── Dashboard          (overview, endpoint list, health check)
  ├── Entity Settings    (per-entity configuration)
  └── Permissions        (role × entity × operation grid)
```

---

## Dashboard Page Content

The dashboard should display:
1. **Plugin status** — version, active endpoint URL
2. **Entity sets list** — all registered entity sets with:
   - Entity set name
   - Adapter class
   - Number of properties
   - OData endpoint URL (e.g. `/wp-json/odata/v4/Posts`)
3. **Quick links** — link to `$metadata`, documentation
4. **Health check** — verify the REST API is accessible

---

## Hook Bridge Update

Add admin hooks to `ODAD_Hook_Bridge::register()`:

```php
if ( is_admin() ) {
    add_action( 'admin_menu', [ $this, 'on_admin_menu' ] );
}
```

```php
public function on_admin_menu(): void {
    $admin = ODAD_container()->get( ODAD_Admin::class );
    $admin->register_menu();
}
```

---

## Assets

Create minimal CSS/JS in `assets/css/odad-admin.css` and `assets/js/odad-admin.js`.
The dashboard is server-rendered HTML (WP Settings API). React is optional.

---

## Bootstrapper Update

```php
$c->singleton( ODAD_Admin::class, fn($c) => new ODAD_Admin(
    $c->get(ODAD_Schema_Registry::class),
    $c->get(ODAD_Event_Bus::class),
));
```

---

## Acceptance Criteria

- "WP-OData Suite" menu item appears in WP admin for users with `manage_options`.
- Dashboard page lists all registered entity sets.
- Each entity set row shows its OData URL.
- No `apply_filters()` / `do_action()` calls inside `ODAD_Admin` — all WP hooks go through `ODAD_Hook_Bridge`.
- Admin pages are only accessible to users with `manage_options` capability.
