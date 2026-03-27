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

define( 'ODAD_VERSION',   '0.1.0' );
define( 'ODAD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ODAD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader (PSR-4: ODAD_ prefix → src/)
spl_autoload_register( function ( string $class ): void {
    if ( ! str_starts_with( $class, 'ODAD_' ) ) {
        return;
    }
    // Convert ODAD_Foo_Bar → foo-bar, ODAD_Interface_Foo → interface-foo
    $suffix   = strtolower( substr( $class, 5 ) );        // strip ODAD_
    $filename = str_replace( '_', '-', $suffix );
    // Try class file first, then interface file
    $paths = [
        ODAD_PLUGIN_DIR . "src/bootstrap/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/http/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/hooks/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/events/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/events/events/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/query/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/write/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/permissions/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/metadata/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/adapters/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/admin/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/hooks/subscribers/class-odad-{$filename}.php",
        // interfaces
        ODAD_PLUGIN_DIR . "src/events/interface-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/adapters/interface-odad-{$filename}.php",
    ];
    foreach ( $paths as $path ) {
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
} );

add_action( 'plugins_loaded', function (): void {
    $container = ODAD_Bootstrapper::build();
    $container->get( ODAD_Hook_Bridge::class )->register();
    $GLOBALS['ODAD_container'] = $container;
}, 5 );

function ODAD_container(): ODAD_Container {
    return $GLOBALS['ODAD_container'];
}
```

---

### `wp-odata-suite/src/bootstrap/class-odad-container.php`

```php
<?php
defined( 'ABSPATH' ) || exit;

class ODAD_Container {

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

### `wp-odata-suite/src/bootstrap/class-odad-bootstrapper.php`

This is a scaffold. It only registers what exists in Phase 1.
Later tasks will add more `singleton()` bindings here.

The bootstrapper must register:
- `ODAD_Event_Bus`
- `ODAD_Hook_Bridge`
- `ODAD_Schema_Registry`
- `ODAD_Metadata_Cache`

(Other bindings — adapters, query engine, permissions, etc. — are added in their respective tasks.)

Implement `register_subscribers()` as empty for now; it will be filled in as subscribers are built.

---

## Architecture Rules to Enforce

- `ODAD_Container` has NO knowledge of WordPress. It is pure PHP.
- The autoloader must NOT use Composer (plugin must work without it).
- `$GLOBALS['ODAD_container']` is for external access only; internal wiring always uses constructor injection.
- `plugins_loaded` at priority 5 ensures external plugins at priority 10 can hook in after bootstrap.

---

## Acceptance Criteria

- Plugin activates in WordPress without fatal errors.
- `ODAD_container()` returns the container instance.
- Container throws `\RuntimeException` with a clear message when an unregistered service is requested.
- Autoloader resolves all `ODAD_*` classes from the `src/` directory.
- No direct `require`/`include` calls outside the autoloader and entry point.
