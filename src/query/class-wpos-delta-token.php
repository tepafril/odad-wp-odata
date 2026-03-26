<?php
/**
 * WPOS_Delta_Token — encode and decode OData delta tokens.
 *
 * Delta tokens are base64url-encoded JSON objects:
 *   { "since": "2024-01-15T10:30:00Z" }
 *
 * They allow OData clients to fetch only entities changed (or deleted)
 * since the previous request by supplying the token in a subsequent
 * $deltatoken query parameter.
 *
 * Security: decode() validates the payload structure to prevent injection.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Delta_Token {

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Encode a DateTimeInterface into a delta token string.
     *
     * The datetime is normalised to UTC before serialisation.
     *
     * @param \DateTimeInterface $since Timestamp to encode.
     * @return string Opaque base64url-encoded token.
     */
    public static function encode( \DateTimeInterface $since ): string {
        // Normalise to UTC / immutable.
        $utc = \DateTimeImmutable::createFromInterface( $since )
            ->setTimezone( new \DateTimeZone( 'UTC' ) );

        $payload = wp_json_encode( [ 'since' => $utc->format( \DateTimeInterface::ATOM ) ] );

        // Use base64url (URL-safe, no padding) so the token is safe in query strings.
        return rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
    }

    /**
     * Decode a delta token back to a DateTimeImmutable (UTC).
     *
     * Returns null when the token is malformed, tampered-with, or contains an
     * invalid timestamp — callers should treat null as a 400 Bad Request.
     *
     * @param string $token The encoded delta token.
     * @return \DateTimeImmutable|null Decoded UTC timestamp, or null on failure.
     */
    public static function decode( string $token ): ?\DateTimeImmutable {
        // Sanitise: only allow base64url characters.
        if ( ! preg_match( '/^[A-Za-z0-9_\-]+$/', $token ) ) {
            return null;
        }

        // Restore standard base64 padding.
        $padded  = str_pad( strtr( $token, '-_', '+/' ), strlen( $token ) % 4 === 0 ? strlen( $token ) : strlen( $token ) + ( 4 - strlen( $token ) % 4 ), '=' );
        $decoded = base64_decode( $padded, true );

        if ( false === $decoded ) {
            return null;
        }

        $data = json_decode( $decoded, true );

        if ( ! is_array( $data ) || ! isset( $data['since'] ) || ! is_string( $data['since'] ) ) {
            return null;
        }

        // Strict ISO 8601 parse; returns false on invalid input.
        $dt = \DateTimeImmutable::createFromFormat(
            \DateTimeInterface::ATOM,
            $data['since'],
            new \DateTimeZone( 'UTC' )
        );

        if ( false === $dt ) {
            return null;
        }

        return $dt;
    }

    /**
     * Generate a new delta token representing the current moment (UTC).
     *
     * Convenience wrapper — use the returned token as the @odata.deltaLink
     * value in a response so the client can use it in the next request.
     *
     * @return string Opaque delta token for "now".
     */
    public static function now(): string {
        return self::encode( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
    }
}
