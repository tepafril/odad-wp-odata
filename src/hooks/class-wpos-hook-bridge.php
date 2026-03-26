<?php
/**
 * Hook Bridge — the ONLY class permitted to call add_action, add_filter,
 * apply_filters, and do_action. All WordPress hook interactions are
 * centralised here; domain services communicate via the event bus instead.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

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

        // Admin UI hooks
        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'on_admin_menu' ] );
            add_action( 'admin_post_wpos_save_entity_config',
                fn() => wpos_container()->get( WPOS_Admin_Entity_Config::class )->save()
            );
            add_action( 'admin_post_wpos_save_permission_config',
                fn() => wpos_container()->get( WPOS_Admin_Permission_Config::class )->save()
            );
        }
    }

    public function on_admin_menu(): void {
        wpos_container()->get( WPOS_Admin::class )->register_menu();
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
