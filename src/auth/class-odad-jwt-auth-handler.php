<?php
defined( 'ABSPATH' ) || exit;

/**
 * Hooks into WordPress's determine_current_user filter to authenticate
 * REST requests carrying a Bearer JWT access token.
 */
class ODAD_JWT_Auth_Handler {

    /**
     * Hooked onto 'determine_current_user' at priority 20.
     *
     * If a valid Bearer token is present, returns the WP user ID it encodes.
     * If no Bearer header is present, passes $user_id through unchanged.
     * If a Bearer header is present but invalid, returns false (forces 401).
     *
     * @param int|false $user_id Current user ID resolved by WordPress so far.
     * @return int|false
     */
    public static function resolve_user( int|false $user_id ): int|false {
        $token = self::extract_bearer_token();

        if ( null === $token ) {
            return $user_id; // no Bearer header — leave WP auth untouched
        }

        try {
            $payload = ODAD_container()->get( ODAD_JWT::class )->verify_access_token( $token );
            return (int) $payload->sub;
        } catch ( ODAD_Token_Exception ) {
            return false; // invalid/expired token → unauthenticated
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private static function extract_bearer_token(): ?string {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if ( $header && str_starts_with( $header, 'Bearer ' ) ) {
            return substr( $header, 7 );
        }

        return null;
    }
}
