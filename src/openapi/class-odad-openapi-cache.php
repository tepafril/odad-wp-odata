<?php
defined( 'ABSPATH' ) || exit;

/**
 * Transient-based cache for the generated OpenAPI JSON string.
 * Busted by ODAD_Subscriber_Schema_Changed whenever the schema changes —
 * the same event that busts the CSDL metadata cache.
 */
class ODAD_OpenAPI_Cache {

    private const TRANSIENT = 'ODAD_openapi_json';
    private const TTL       = DAY_IN_SECONDS;

    public function get(): ?string {
        $cached = get_transient( self::TRANSIENT );
        return ( false !== $cached ) ? (string) $cached : null;
    }

    public function set( string $json ): void {
        set_transient( self::TRANSIENT, $json, self::TTL );
    }

    public function bust(): void {
        delete_transient( self::TRANSIENT );
    }
}
