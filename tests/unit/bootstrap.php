<?php
/**
 * Bootstrap for PHPUnit unit tests.
 *
 * Defines the WordPress stubs required for plugin classes to load without
 * a real WordPress environment, then registers the plugin autoloader.
 */

// ── WordPress constants ───────────────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'WPOS_VERSION' ) ) {
    define( 'WPOS_VERSION', '0.1.0' );
}
if ( ! defined( 'WPOS_PLUGIN_DIR' ) ) {
    define( 'WPOS_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}
if ( ! defined( 'WPOS_PLUGIN_URL' ) ) {
    define( 'WPOS_PLUGIN_URL', 'http://localhost/' );
}

// ── WordPress function stubs ──────────────────────────────────────────────────

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
        return $value;
    }
}

if ( ! function_exists( 'do_action' ) ) {
    function do_action( string $hook, mixed ...$args ): void {
        // no-op
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): void {
        // no-op
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): void {
        // no-op
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( string $key, mixed $default = false ): mixed {
        return $default;
    }
}

if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( string $key ): void {
        // no-op
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( string $key ): mixed {
        return false;
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( string $key, mixed $value, int $expiry = 0 ): void {
        // no-op
    }
}

if ( ! function_exists( 'is_admin' ) ) {
    function is_admin(): bool {
        return false;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( mixed $value, int $flags = 0 ): string|false {
        return json_encode( $value, $flags );
    }
}

// ── WP_User stub ─────────────────────────────────────────────────────────────

if ( ! class_exists( 'WP_User' ) ) {
    class WP_User {
        public int   $ID    = 0;
        public array $roles = [];

        /** @var array<string, bool> */
        private array $caps = [];

        public function __construct( int $id = 0, array $roles = [], array $caps = [] ) {
            $this->ID    = $id;
            $this->roles = $roles;
            $this->caps  = $caps;
        }

        public function has_cap( string $cap ): bool {
            return ! empty( $this->caps[ $cap ] );
        }
    }
}

// ── Plugin autoloader ─────────────────────────────────────────────────────────

spl_autoload_register( function ( string $class ): void {
    if ( ! str_starts_with( $class, 'WPOS_' ) ) {
        return;
    }

    $suffix   = strtolower( substr( $class, 5 ) ); // strip WPOS_
    $filename = str_replace( '_', '-', $suffix );

    $plugin_dir = WPOS_PLUGIN_DIR;

    $paths = [
        $plugin_dir . "src/bootstrap/class-wpos-{$filename}.php",
        $plugin_dir . "src/http/class-wpos-{$filename}.php",
        $plugin_dir . "src/hooks/class-wpos-{$filename}.php",
        $plugin_dir . "src/events/class-wpos-{$filename}.php",
        $plugin_dir . "src/events/events/class-wpos-{$filename}.php",
        $plugin_dir . "src/query/class-wpos-{$filename}.php",
        $plugin_dir . "src/write/class-wpos-{$filename}.php",
        $plugin_dir . "src/permissions/class-wpos-{$filename}.php",
        $plugin_dir . "src/metadata/class-wpos-{$filename}.php",
        $plugin_dir . "src/adapters/class-wpos-{$filename}.php",
        $plugin_dir . "src/admin/class-wpos-{$filename}.php",
        $plugin_dir . "src/hooks/subscribers/class-wpos-{$filename}.php",
        // interfaces
        $plugin_dir . "src/events/interface-wpos-{$filename}.php",
        $plugin_dir . "src/adapters/interface-wpos-{$filename}.php",
    ];

    foreach ( $paths as $path ) {
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
} );
