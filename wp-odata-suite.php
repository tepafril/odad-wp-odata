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

register_activation_hook( __FILE__,   [ 'ODAD_Auth_Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'ODAD_Auth_Installer', 'deactivate' ] );

define( 'ODAD_VERSION',   '0.1.0' );
define( 'ODAD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ODAD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// AST node classes are all defined in one file — load it before the autoloader.
require_once ODAD_PLUGIN_DIR . 'src/query/class-odad-ast-node.php';

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
        ODAD_PLUGIN_DIR . "src/auth/class-odad-{$filename}.php",
        ODAD_PLUGIN_DIR . "src/openapi/class-odad-{$filename}.php",
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
