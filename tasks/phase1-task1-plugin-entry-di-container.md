# Task 1.1 — Plugin Entry Point + DI Container + Bootstrapper Scaffold

## Dependencies
None. This is the first task. Build this before anything else.

## Goal
Create the plugin root file, the DI container, and a bootstrapper scaffold that wires
the container together. The bootstrapper only registers the bindings that exist at this
stage (Phase 1). It will be extended in later tasks.

---

## Files to Create

### `wp-odata-suite/wp-odata-suite.php`
The WordPress plugin entry point.

```php
<?php
/**
 * Plugin Name: WP-OData Suite
 * Plugin URI:  https://github.com/your-org/wp-odata-suite
 * Description: Exposes WordPress data as a fully compliant OData v4.01 REST API.
 * Version:     0.1.0
 * Requires at least: 6.3
 * Requires PHP: 8.1
 * Author:      Your Name
 * License:     GPL-2.0-or-later
 * Text Domain: wp-odata-suite
 */

defined( 'ABSPATH' ) || exit;

define( 'WPOS_VERSION',   '0.1.0' );
define( 'WPOS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPOS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader (PSR-4: WPOS_ prefix → src/)
spl_autoload_register( function ( string $class ): void {
    if ( ! str_starts_with( $class, 'WPOS_' ) ) {
        return;
    }
    // Convert WPOS_Foo_Bar → foo-bar, WPOS_Interface_Foo → interface-foo
    $suffix   = strtolower( substr( $class, 5 ) );        // strip WPOS_
    $filename = str_replace( '_', '-', $suffix );
    // Try class file first, then interface file
    $paths = [
        WPOS_PLUGIN_DIR . "src/bootstrap/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/http/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/hooks/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/events/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/events/events/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/query/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/write/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/permissions/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/metadata/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/adapters/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/admin/class-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/hooks/subscribers/class-wpos-{$filename}.php",
        // interfaces
        WPOS_PLUGIN_DIR . "src/events/interface-wpos-{$filename}.php",
        WPOS_PLUGIN_DIR . "src/adapters/interface-wpos-{$filename}.php",
    ];
    foreach ( $paths as $path ) {
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
} );

add_action( 'plugins_loaded', function (): void {
    $container = WPOS_Bootstrapper::build();
    $container->get( WPOS_Hook_Bridge::class )->register();
    $GLOBALS['wpos_container'] = $container;
}, 5 );

function wpos_container(): WPOS_Container {
    return $GLOBALS['wpos_container'];
}
```

---

### `wp-odata-suite/src/bootstrap/class-wpos-container.php`

```php
<?php
defined( 'ABSPATH' ) || exit;

class WPOS_Container {

    private array $factories  = [];
    private array $singletons = [];

    public function singleton( string $id, callable $factory ): void {
        $this->factories[ $id ] = $factory;
    }

    public function get( string $id ): mixed {
        if ( ! isset( $this->singletons[ $id ] ) ) {
            if ( ! isset( $this->factories[ $id ] ) ) {
                throw new \RuntimeException( "No binding for: {$id}" );
            }
            $this->singletons[ $id ] = ( $this->factories[ $id ] )( $this );
        }
        return $this->singletons[ $id ];
    }

    public function has( string $id ): bool {
        return isset( $this->factories[ $id ] ) || isset( $this->singletons[ $id ] );
    }
}
```

---

### `wp-odata-suite/src/bootstrap/class-wpos-bootstrapper.php`

This is a scaffold. It only registers what exists in Phase 1.
Later tasks will add more `singleton()` bindings here.

The bootstrapper must register:
- `WPOS_Event_Bus`
- `WPOS_Hook_Bridge`
- `WPOS_Schema_Registry`
- `WPOS_Metadata_Cache`

(Other bindings — adapters, query engine, permissions, etc. — are added in their respective tasks.)

Implement `register_subscribers()` as empty for now; it will be filled in as subscribers are built.

---

## Architecture Rules to Enforce

- `WPOS_Container` has NO knowledge of WordPress. It is pure PHP.
- The autoloader must NOT use Composer (plugin must work without it).
- `$GLOBALS['wpos_container']` is for external access only; internal wiring always uses constructor injection.
- `plugins_loaded` at priority 5 ensures external plugins at priority 10 can hook in after bootstrap.

---

## Acceptance Criteria

- Plugin activates in WordPress without fatal errors.
- `wpos_container()` returns the container instance.
- Container throws `\RuntimeException` with a clear message when an unregistered service is requested.
- Autoloader resolves all `WPOS_*` classes from the `src/` directory.
- No direct `require`/`include` calls outside the autoloader and entry point.
