<?php
/**
 * Admin — main admin class: registers the WP admin menu and renders the dashboard.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Admin {

    public function __construct(
        private ODAD_Schema_Registry $registry,
        private ODAD_Event_Bus       $event_bus,
    ) {}

    /**
     * Register admin menu pages.
     * Called via WP 'admin_menu' action (registered in ODAD_Hook_Bridge).
     */
    public function register_menu(): void {
        add_menu_page(
            __( 'WP-OData Suite', 'wp-odata-suite' ),
            __( 'WP-OData Suite', 'wp-odata-suite' ),
            'manage_options',
            'wpos-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-rest-api',
            80
        );

        add_submenu_page(
            'wpos-dashboard',
            __( 'Dashboard', 'wp-odata-suite' ),
            __( 'Dashboard', 'wp-odata-suite' ),
            'manage_options',
            'wpos-dashboard',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'wpos-dashboard',
            __( 'Entity Settings', 'wp-odata-suite' ),
            __( 'Entity Settings', 'wp-odata-suite' ),
            'manage_options',
            'wpos-entity-config',
            fn() => ODAD_container()->get( ODAD_Admin_Entity_Config::class )->render()
        );

        add_submenu_page(
            'wpos-dashboard',
            __( 'Permissions', 'wp-odata-suite' ),
            __( 'Permissions', 'wp-odata-suite' ),
            'manage_options',
            'wpos-permission-config',
            fn() => ODAD_container()->get( ODAD_Admin_Permission_Config::class )->render()
        );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Enqueue admin CSS/JS on WPOS pages only.
     */
    public function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, 'wpos' ) ) {
            return;
        }
        wp_enqueue_style(
            'wpos-admin',
            ODAD_PLUGIN_URL . 'assets/css/wpos-admin.css',
            [],
            ODAD_VERSION
        );
        wp_enqueue_script(
            'wpos-admin',
            ODAD_PLUGIN_URL . 'assets/js/wpos-admin.js',
            [ 'jquery' ],
            ODAD_VERSION,
            true
        );
    }

    /**
     * Render the main dashboard page.
     */
    public function render_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-odata-suite' ) );
        }

        $base_url   = rest_url( 'odata/v4/' );
        $entity_sets = $this->registry->all();
        ?>
        <div class="wrap wpos-wrap">
            <h1><?php esc_html_e( 'WP-OData Suite', 'wp-odata-suite' ); ?></h1>

            <?php $this->render_status_card( $base_url ); ?>
            <?php $this->render_entity_sets_table( $entity_sets, $base_url ); ?>
            <?php $this->render_quick_links( $base_url ); ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------

    private function render_status_card( string $base_url ): void {
        ?>
        <div class="wpos-card">
            <h2><?php esc_html_e( 'Plugin Status', 'wp-odata-suite' ); ?></h2>
            <table class="widefat fixed striped">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e( 'Version', 'wp-odata-suite' ); ?></strong></td>
                        <td><?php echo esc_html( ODAD_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'OData Endpoint', 'wp-odata-suite' ); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url( $base_url ); ?>" target="_blank">
                                <?php echo esc_html( $base_url ); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'REST API', 'wp-odata-suite' ); ?></strong></td>
                        <td id="wpos-health-status">
                            <span class="wpos-checking"><?php esc_html_e( 'Checking…', 'wp-odata-suite' ); ?></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <script>
        (function() {
            var el = document.getElementById('wpos-health-status');
            if (!el) return;
            fetch(<?php echo wp_json_encode( $base_url ); ?>, { method: 'HEAD', credentials: 'same-origin' })
                .then(function(r) {
                    el.innerHTML = r.ok
                        ? '<span class="wpos-ok"><?php echo esc_js( __( 'Accessible', 'wp-odata-suite' ) ); ?></span>'
                        : '<span class="wpos-err"><?php echo esc_js( __( 'Returned HTTP ', 'wp-odata-suite' ) ); ?>' + r.status + '</span>';
                })
                .catch(function() {
                    el.innerHTML = '<span class="wpos-err"><?php echo esc_js( __( 'Unreachable', 'wp-odata-suite' ) ); ?></span>';
                });
        }());
        </script>
        <?php
    }

    private function render_entity_sets_table( array $entity_sets, string $base_url ): void {
        ?>
        <div class="wpos-card">
            <h2><?php esc_html_e( 'Registered Entity Sets', 'wp-odata-suite' ); ?></h2>
            <?php if ( empty( $entity_sets ) ) : ?>
                <p><?php esc_html_e( 'No entity sets registered yet.', 'wp-odata-suite' ); ?></p>
            <?php else : ?>
                <table class="widefat fixed striped wpos-entity-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Entity Set', 'wp-odata-suite' ); ?></th>
                            <th><?php esc_html_e( 'Properties', 'wp-odata-suite' ); ?></th>
                            <th><?php esc_html_e( 'OData Endpoint', 'wp-odata-suite' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $entity_sets as $entity_set_name => $definition ) :
                            $prop_count  = count( $definition['properties'] ?? [] );
                            $endpoint    = $base_url . $entity_set_name;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $entity_set_name ); ?></strong></td>
                            <td><?php echo (int) $prop_count; ?></td>
                            <td>
                                <a href="<?php echo esc_url( $endpoint ); ?>" target="_blank">
                                    <?php echo esc_html( $endpoint ); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_quick_links( string $base_url ): void {
        ?>
        <div class="wpos-card">
            <h2><?php esc_html_e( 'Quick Links', 'wp-odata-suite' ); ?></h2>
            <ul class="wpos-quick-links">
                <li>
                    <a href="<?php echo esc_url( $base_url . '$metadata' ); ?>" target="_blank">
                        <?php esc_html_e( 'CSDL Metadata ($metadata)', 'wp-odata-suite' ); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url( $base_url . '$metadata?$format=json' ); ?>" target="_blank">
                        <?php esc_html_e( 'JSON CSDL Metadata', 'wp-odata-suite' ); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpos-entity-config' ) ); ?>">
                        <?php esc_html_e( 'Entity Settings', 'wp-odata-suite' ); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpos-permission-config' ) ); ?>">
                        <?php esc_html_e( 'Permission Settings', 'wp-odata-suite' ); ?>
                    </a>
                </li>
            </ul>
        </div>
        <?php
    }
}
