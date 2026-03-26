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
