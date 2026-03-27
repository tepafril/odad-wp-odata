<?php
/**
 * Admin Permission Config — role × entity × operation grid UI.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Admin_Permission_Config {

    /** Operations shown in the grid. */
    private const OPERATIONS = [ 'read', 'insert', 'update', 'delete' ];

    public function __construct(
        private ODAD_Schema_Registry $registry,
        private ODAD_Capability_Map  $capability_map,
        private ODAD_Event_Bus       $event_bus,
    ) {}

    /**
     * Render the permission configuration page.
     */
    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-odata-suite' ) );
        }

        $entity_sets = array_keys( $this->registry->all() );
        $roles       = $this->get_wp_roles();
        $updated     = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
        ?>
        <div class="wrap odad-wrap">
            <h1><?php esc_html_e( 'Permission Settings', 'wp-odata-suite' ); ?></h1>

            <?php if ( $updated ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Permissions saved.', 'wp-odata-suite' ); ?></p>
                </div>
            <?php endif; ?>

            <p class="description">
                <?php esc_html_e( 'Configure which roles may perform each operation on each entity set. Unchecked = denied. Administrators always retain read access.', 'wp-odata-suite' ); ?>
            </p>

            <?php if ( empty( $entity_sets ) ) : ?>
                <p><?php esc_html_e( 'No entity sets registered yet.', 'wp-odata-suite' ); ?></p>
            <?php else : ?>
                <?php foreach ( $entity_sets as $entity_set_name ) :
                    $permissions = $this->get_permissions( $entity_set_name );
                    ?>
                    <div class="odad-card">
                        <h2><?php echo esc_html( $entity_set_name ); ?></h2>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'ODAD_permission_config_save' ); ?>
                            <input type="hidden" name="action"     value="ODAD_save_permission_config">
                            <input type="hidden" name="entity_set" value="<?php echo esc_attr( $entity_set_name ); ?>">

                            <table class="widefat fixed striped odad-perm-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Role', 'wp-odata-suite' ); ?></th>
                                        <?php foreach ( self::OPERATIONS as $op ) : ?>
                                            <th><?php echo esc_html( ucfirst( $op ) ); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $roles as $role_slug => $role_name ) :
                                        $role_perms = $permissions[ $role_slug ] ?? [];
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $role_name ); ?></strong></td>
                                        <?php foreach ( self::OPERATIONS as $op ) :
                                            // Administrator read cannot be unchecked.
                                            $locked = ( 'administrator' === $role_slug && 'read' === $op );
                                            $checked = $locked || ! empty( $role_perms[ $op ] );
                                        ?>
                                        <td>
                                            <input type="checkbox"
                                                   name="permissions[<?php echo esc_attr( $role_slug ); ?>][<?php echo esc_attr( $op ); ?>]"
                                                   value="1"
                                                   <?php checked( $checked ); ?>
                                                   <?php disabled( $locked ); ?>>
                                            <?php if ( $locked ) : ?>
                                                <input type="hidden"
                                                       name="permissions[<?php echo esc_attr( $role_slug ); ?>][<?php echo esc_attr( $op ); ?>]"
                                                       value="1">
                                            <?php endif; ?>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php submit_button( __( 'Save Permissions', 'wp-odata-suite' ) ); ?>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle form submission (admin-post hook).
     */
    public function save(): void {
        check_admin_referer( 'ODAD_permission_config_save' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'wp-odata-suite' ) );
        }

        $entity_set = sanitize_text_field( wp_unslash( $_POST['entity_set'] ?? '' ) );

        if ( empty( $entity_set ) ) {
            wp_die( esc_html__( 'Invalid entity set.', 'wp-odata-suite' ) );
        }

        $raw         = $_POST['permissions'] ?? [];
        $permissions = $this->sanitize_permissions( is_array( $raw ) ? $raw : [] );

        // Enforce: administrator always retains read access.
        $permissions['administrator']['read'] = true;

        update_option( "ODAD_permissions_{$entity_set}", $permissions );

        // Update the in-memory capability map so runtime checks are immediate.
        $this->capability_map->register_role_overrides( $entity_set, $permissions );

        $this->event_bus->dispatch( new ODAD_Event_Admin_Permission_Saved(
            entity_set:  $entity_set,
            permissions: $permissions,
        ) );

        $referer = wp_get_referer();
        wp_redirect( add_query_arg( 'updated', '1', $referer ?: admin_url( 'admin.php?page=odad-permission-config' ) ) );
        exit;
    }

    /**
     * Get saved permission config for an entity set.
     * Returns [ role_slug => [ operation => bool ] ]
     */
    public function get_permissions( string $entity_set ): array {
        $saved = get_option( "ODAD_permissions_{$entity_set}", [] );
        return is_array( $saved ) ? $saved : [];
    }

    // -------------------------------------------------------------------------

    private function sanitize_permissions( array $raw ): array {
        $valid_ops = array_fill_keys( self::OPERATIONS, false );
        $result    = [];

        foreach ( $raw as $role_slug => $ops ) {
            $role_slug = sanitize_key( (string) $role_slug );
            if ( empty( $role_slug ) ) {
                continue;
            }
            $sanitized = $valid_ops;
            if ( is_array( $ops ) ) {
                foreach ( self::OPERATIONS as $op ) {
                    if ( isset( $ops[ $op ] ) ) {
                        $sanitized[ $op ] = (bool) $ops[ $op ];
                    }
                }
            }
            $result[ $role_slug ] = $sanitized;
        }

        return $result;
    }

    private function get_wp_roles(): array {
        $wp_roles = wp_roles();
        $roles    = [];
        foreach ( $wp_roles->roles as $slug => $data ) {
            $roles[ $slug ] = translate_user_role( $data['name'] );
        }
        return $roles;
    }
}
