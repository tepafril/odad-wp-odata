<?php
/**
 * Admin Entity Config — per-entity-set configuration UI and save flow.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Admin_Entity_Config {

    /** Default configuration values. */
    private const DEFAULTS = [
        'enabled'            => true,
        'label'              => '',
        'exposed_properties' => [],
        'allow_insert'       => true,
        'allow_update'       => true,
        'allow_delete'       => true,
        'max_top'            => 1000,
        'require_auth'       => true,
    ];

    public function __construct(
        private ODAD_Schema_Registry $registry,
        private ODAD_Event_Bus       $event_bus,
    ) {}

    /**
     * Render the entity configuration page.
     */
    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-odata-suite' ) );
        }

        $entity_sets = $this->registry->all();
        $updated     = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
        ?>
        <div class="wrap odad-wrap">
            <h1><?php esc_html_e( 'Entity Settings', 'wp-odata-suite' ); ?></h1>

            <?php if ( $updated ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings saved.', 'wp-odata-suite' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( empty( $entity_sets ) ) : ?>
                <p><?php esc_html_e( 'No entity sets registered yet.', 'wp-odata-suite' ); ?></p>
            <?php else : ?>
                <?php foreach ( $entity_sets as $entity_set_name => $definition ) :
                    $config     = $this->get_config( $entity_set_name );
                    $properties = array_keys( $definition['properties'] ?? [] );
                    ?>
                    <div class="odad-card">
                        <h2><?php echo esc_html( $entity_set_name ); ?></h2>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'ODAD_entity_config_save' ); ?>
                            <input type="hidden" name="action"     value="ODAD_save_entity_config">
                            <input type="hidden" name="entity_set" value="<?php echo esc_attr( $entity_set_name ); ?>">

                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e( 'Enabled', 'wp-odata-suite' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="config[enabled]" value="1"
                                                <?php checked( $config['enabled'] ); ?>>
                                            <?php esc_html_e( 'Expose this entity set via OData', 'wp-odata-suite' ); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Label', 'wp-odata-suite' ); ?></th>
                                    <td>
                                        <input type="text" class="regular-text"
                                               name="config[label]"
                                               value="<?php echo esc_attr( $config['label'] ); ?>"
                                               placeholder="<?php echo esc_attr( $entity_set_name ); ?>">
                                        <p class="description"><?php esc_html_e( 'Human-readable label shown in $metadata.', 'wp-odata-suite' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Exposed Properties', 'wp-odata-suite' ); ?></th>
                                    <td>
                                        <p class="description">
                                            <?php esc_html_e( 'Leave all unchecked to expose every property.', 'wp-odata-suite' ); ?>
                                        </p>
                                        <?php foreach ( $properties as $prop ) :
                                            $checked = empty( $config['exposed_properties'] )
                                                || in_array( $prop, $config['exposed_properties'], true );
                                            ?>
                                            <label style="display:block; margin-bottom:4px;">
                                                <input type="checkbox"
                                                       name="config[exposed_properties][]"
                                                       value="<?php echo esc_attr( $prop ); ?>"
                                                       <?php checked( $checked ); ?>>
                                                <?php echo esc_html( $prop ); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Allow Operations', 'wp-odata-suite' ); ?></th>
                                    <td>
                                        <label style="margin-right:12px;">
                                            <input type="checkbox" name="config[allow_insert]" value="1"
                                                <?php checked( $config['allow_insert'] ); ?>>
                                            <?php esc_html_e( 'Insert (POST)', 'wp-odata-suite' ); ?>
                                        </label>
                                        <label style="margin-right:12px;">
                                            <input type="checkbox" name="config[allow_update]" value="1"
                                                <?php checked( $config['allow_update'] ); ?>>
                                            <?php esc_html_e( 'Update (PATCH/PUT)', 'wp-odata-suite' ); ?>
                                        </label>
                                        <label>
                                            <input type="checkbox" name="config[allow_delete]" value="1"
                                                <?php checked( $config['allow_delete'] ); ?>>
                                            <?php esc_html_e( 'Delete (DELETE)', 'wp-odata-suite' ); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Max $top', 'wp-odata-suite' ); ?></th>
                                    <td>
                                        <input type="number" class="small-text"
                                               name="config[max_top]"
                                               value="<?php echo (int) $config['max_top']; ?>"
                                               min="1" max="10000">
                                        <p class="description"><?php esc_html_e( 'Maximum number of rows returned per request.', 'wp-odata-suite' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Require Authentication', 'wp-odata-suite' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="config[require_auth]" value="1"
                                                <?php checked( $config['require_auth'] ); ?>>
                                            <?php esc_html_e( 'Require a logged-in user to access this entity set', 'wp-odata-suite' ); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>

                            <?php submit_button( __( 'Save Settings', 'wp-odata-suite' ) ); ?>
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
        check_admin_referer( 'ODAD_entity_config_save' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'wp-odata-suite' ) );
        }

        $entity_set = sanitize_text_field( wp_unslash( $_POST['entity_set'] ?? '' ) );

        if ( empty( $entity_set ) ) {
            wp_die( esc_html__( 'Invalid entity set.', 'wp-odata-suite' ) );
        }

        $raw_config = $_POST['config'] ?? [];
        $config     = $this->sanitize_config( is_array( $raw_config ) ? $raw_config : [] );

        update_option( "ODAD_entity_config_{$entity_set}", $config );

        $this->event_bus->dispatch( new ODAD_Event_Admin_Entity_Config_Saved(
            entity_set: $entity_set,
            config:     $config,
        ) );

        $referer = wp_get_referer();
        wp_redirect( add_query_arg( 'updated', '1', $referer ?: admin_url( 'admin.php?page=odad-entity-config' ) ) );
        exit;
    }

    /**
     * Get configuration for an entity set.
     * Config stored in WP option 'ODAD_entity_config_{entity_set}'.
     */
    public function get_config( string $entity_set ): array {
        $saved = get_option( "ODAD_entity_config_{$entity_set}", [] );
        return array_merge( self::DEFAULTS, is_array( $saved ) ? $saved : [] );
    }

    // -------------------------------------------------------------------------

    private function sanitize_config( array $raw ): array {
        $exposed = [];
        if ( isset( $raw['exposed_properties'] ) && is_array( $raw['exposed_properties'] ) ) {
            foreach ( $raw['exposed_properties'] as $prop ) {
                $exposed[] = sanitize_text_field( (string) $prop );
            }
        }

        return [
            'enabled'            => ! empty( $raw['enabled'] ),
            'label'              => sanitize_text_field( $raw['label'] ?? '' ),
            'exposed_properties' => $exposed,
            'allow_insert'       => ! empty( $raw['allow_insert'] ),
            'allow_update'       => ! empty( $raw['allow_update'] ),
            'allow_delete'       => ! empty( $raw['allow_delete'] ),
            'max_top'            => max( 1, min( 10000, (int) ( $raw['max_top'] ?? 1000 ) ) ),
            'require_auth'       => ! empty( $raw['require_auth'] ),
        ];
    }
}
