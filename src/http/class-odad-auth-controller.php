<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST controller for JWT authentication endpoints.
 *
 *   POST /wp-json/odad/v1/auth/login
 *   POST /wp-json/odad/v1/auth/refresh
 *   POST /wp-json/odad/v1/auth/logout
 */
class ODAD_Auth_Controller {

    private const NS = 'odad/v1';

    public function __construct(
        private ODAD_JWT         $jwt,
        private ODAD_Token_Store $store,
    ) {}

    // ── Route registration ────────────────────────────────────────────────

    public function register_routes(): void {
        register_rest_route( self::NS, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'login' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'username' => [ 'required' => true,  'type' => 'string' ],
                'password' => [ 'required' => true,  'type' => 'string' ],
                'device'   => [ 'required' => false, 'type' => 'string', 'default' => '' ],
            ],
        ] );

        register_rest_route( self::NS, '/auth/refresh', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'refresh' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'refresh_token' => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        register_rest_route( self::NS, '/auth/logout', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'logout' ],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'refresh_token' => [ 'required' => false, 'type' => 'string', 'default' => '' ],
                'all_devices'   => [ 'required' => false, 'type' => 'boolean', 'default' => false ],
            ],
        ] );
    }

    // ── Endpoints ─────────────────────────────────────────────────────────

    /**
     * POST /odad/v1/auth/login
     *
     * 200: { access_token, refresh_token, expires_in, user }
     * 401: { code: "invalid_credentials" }
     * 429: { code: "too_many_attempts" }
     */
    public function login( \WP_REST_Request $request ): \WP_REST_Response {
        // ── Rate limiting ─────────────────────────────────────────────────
        $ip_key = 'odad_login_fails_' . md5( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
        $fails  = (int) get_transient( $ip_key );

        if ( $fails >= 5 ) {
            return new \WP_REST_Response(
                [ 'code' => 'too_many_attempts', 'message' => __( 'Too many login attempts. Try again later.', 'wp-odata-suite' ) ],
                429
            );
        }

        // ── Authenticate ──────────────────────────────────────────────────
        $user = wp_authenticate(
            $request->get_param( 'username' ),
            $request->get_param( 'password' )
        );

        if ( is_wp_error( $user ) ) {
            set_transient( $ip_key, $fails + 1, 15 * MINUTE_IN_SECONDS );
            return new \WP_REST_Response(
                [ 'code' => 'invalid_credentials', 'message' => __( 'Invalid username or password.', 'wp-odata-suite' ) ],
                401
            );
        }

        delete_transient( $ip_key );

        // ── Issue tokens ──────────────────────────────────────────────────
        $access_token  = $this->jwt->issue_access_token( $user->ID );
        $refresh_token = $this->jwt->generate_refresh_token();

        $this->store->store(
            $user->ID,
            $refresh_token,
            $this->jwt->get_refresh_ttl(),
            (string) $request->get_param( 'device' )
        );

        return new \WP_REST_Response( [
            'access_token'  => $access_token,
            'refresh_token' => $refresh_token,
            'expires_in'    => $this->jwt->get_access_ttl(),
            'user'          => $this->build_user_payload( $user ),
        ], 200 );
    }

    /**
     * POST /odad/v1/auth/refresh
     *
     * 200: { access_token, expires_in }
     * 401: { code: "invalid_refresh_token" }
     */
    public function refresh( \WP_REST_Request $request ): \WP_REST_Response {
        try {
            $user_id = $this->store->consume( (string) $request->get_param( 'refresh_token' ) );
        } catch ( ODAD_Token_Exception $e ) {
            return new \WP_REST_Response(
                [ 'code' => 'invalid_refresh_token', 'message' => $e->getMessage() ],
                401
            );
        }

        $access_token = $this->jwt->issue_access_token( $user_id );

        return new \WP_REST_Response( [
            'access_token' => $access_token,
            'expires_in'   => $this->jwt->get_access_ttl(),
        ], 200 );
    }

    /**
     * POST /odad/v1/auth/logout
     *
     * Requires Authorization: Bearer <access_token>
     * 204: No Content
     */
    public function logout( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id   = get_current_user_id();
        $raw_token = (string) $request->get_param( 'refresh_token' );
        $all       = (bool)   $request->get_param( 'all_devices' );

        if ( $all || '' === $raw_token ) {
            $this->store->revoke_all( $user_id );
        } else {
            $this->store->revoke( $raw_token );
        }

        return new \WP_REST_Response( null, 204 );
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function build_user_payload( \WP_User $user ): array {
        return [
            'id'           => $user->ID,
            'login'        => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'roles'        => $user->roles,
        ];
    }
}
