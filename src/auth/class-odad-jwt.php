<?php
defined( 'ABSPATH' ) || exit;

/**
 * Issues and verifies HS256 JWT access tokens.
 * No Composer dependency — uses hash_hmac() only.
 */
class ODAD_JWT {

    private string $secret;
    private int    $access_ttl;
    private int    $refresh_ttl;

    /** Static base64url-encoded header: {"alg":"HS256","typ":"JWT"} */
    private const HEADER = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';

    public function __construct() {
        $this->secret      = (string) get_option( 'odad_jwt_secret', '' );
        $this->access_ttl  = (int)    get_option( 'odad_access_token_ttl',  900 );
        $this->refresh_ttl = (int)    get_option( 'odad_refresh_token_ttl', 2592000 );
    }

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Issue a signed HS256 access token for a WP user ID.
     */
    public function issue_access_token( int $user_id ): string {
        $payload = $this->base64url_encode( (string) json_encode( [
            'sub'  => $user_id,
            'iat'  => time(),
            'exp'  => time() + $this->access_ttl,
            'type' => 'access',
        ] ) );

        $sig = $this->sign( self::HEADER . '.' . $payload );

        return self::HEADER . '.' . $payload . '.' . $sig;
    }

    /**
     * Verify and decode an access token.
     *
     * @throws ODAD_Token_Exception on invalid signature, expired token, or wrong type.
     * @return object decoded payload
     */
    public function verify_access_token( string $token ): object {
        $parts = explode( '.', $token );
        if ( count( $parts ) !== 3 ) {
            throw new ODAD_Token_Exception( 'Malformed token.', 'token_invalid' );
        }

        [ $header, $payload_b64, $sig ] = $parts;

        // Verify signature.
        $expected = $this->sign( $header . '.' . $payload_b64 );
        if ( ! hash_equals( $expected, $sig ) ) {
            throw new ODAD_Token_Exception( 'Invalid signature.', 'token_invalid' );
        }

        // Decode payload.
        $payload = json_decode( $this->base64url_decode( $payload_b64 ) );
        if ( ! is_object( $payload ) ) {
            throw new ODAD_Token_Exception( 'Malformed token.', 'token_invalid' );
        }

        // Check expiry.
        if ( ! isset( $payload->exp ) || time() >= $payload->exp ) {
            throw new ODAD_Token_Exception( 'Token has expired.', 'token_expired' );
        }

        // Enforce token type.
        if ( ( $payload->type ?? '' ) !== 'access' ) {
            throw new ODAD_Token_Exception( 'Wrong token type.', 'token_invalid' );
        }

        return $payload;
    }

    /**
     * Generate a cryptographically random refresh token (64 hex chars).
     */
    public function generate_refresh_token(): string {
        return bin2hex( random_bytes( 32 ) );
    }

    public function get_access_ttl(): int {
        return $this->access_ttl;
    }

    public function get_refresh_ttl(): int {
        return $this->refresh_ttl;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function sign( string $header_payload ): string {
        return $this->base64url_encode(
            hash_hmac( 'sha256', $header_payload, $this->secret, true )
        );
    }

    private function base64url_encode( string $data ): string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    private function base64url_decode( string $data ): string {
        return base64_decode( strtr( $data, '-_', '+/' ) );
    }
}
