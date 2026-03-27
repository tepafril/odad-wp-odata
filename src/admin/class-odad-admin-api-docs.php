<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin page: renders an embedded Swagger UI pointing at the live
 * /odata/v4/openapi.json spec endpoint.
 *
 * The WP nonce is injected into the Swagger UI request interceptor so
 * "Try it out" requests are automatically authenticated via the admin
 * cookie session — no manual token entry required.
 */
class ODAD_Admin_API_Docs {

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-odata-suite' ) );
        }

        $spec_url = rest_url( 'odata/v4/openapi.json' );

        wp_enqueue_style(
            'odad-swagger-ui',
            ODAD_PLUGIN_URL . 'assets/swagger-ui/swagger-ui.css',
            [],
            ODAD_VERSION
        );
        wp_enqueue_script(
            'odad-swagger-ui',
            ODAD_PLUGIN_URL . 'assets/swagger-ui/swagger-ui-bundle.js',
            [],
            ODAD_VERSION,
            true  // load in footer
        );

        // wp_add_inline_script appends this after the library tag in the footer,
        // guaranteeing SwaggerUIBundle is defined when the init code runs.
        wp_add_inline_script(
            'odad-swagger-ui',
            'SwaggerUIBundle({' .
                'url:'    . wp_json_encode( $spec_url ) . ',' .
                'dom_id:"#odad-swagger-ui-container",' .
                'presets:[SwaggerUIBundle.presets.apis],' .
                'layout:"BaseLayout",' .
                'deepLinking:true,' .
                'displayRequestDuration:true,' .
                'tryItOutEnabled:true,' .
                'requestInterceptor:function(req){' .
                    'req.headers["X-WP-Nonce"]=' . wp_json_encode( wp_create_nonce( 'wp_rest' ) ) . ';' .
                    'return req;' .
                '}' .
            '});'
        );
        ?>
        <div class="wrap" style="background-color: #fff; padding: 8px 16px; border: 1px solid #c1c1c1; border-radius: 12px;">
            <h1><?php esc_html_e( 'API Documentation', 'wp-odata-suite' ); ?></h1>
            <p>
                <?php esc_html_e( 'Live OpenAPI spec:', 'wp-odata-suite' ); ?>
                <a href="<?php echo esc_url( $spec_url ); ?>" target="_blank">
                    <?php echo esc_html( $spec_url ); ?>
                </a>
            </p>
            <div id="odad-swagger-ui-container" style="margin-top:16px"></div>
        </div>
        <?php
    }
}
