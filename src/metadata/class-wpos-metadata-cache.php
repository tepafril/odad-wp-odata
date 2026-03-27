<?php
/**
 * ODAD_Metadata_Cache — WP Transients-based cache for the compiled $metadata document.
 *
 * Both XML (CSDL) and JSON representations are stored under separate transient
 * keys with a TTL of DAY_IN_SECONDS. The bust() method deletes both transients
 * so they are rebuilt on the next request.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Metadata_Cache {

    private const TRANSIENT_XML  = 'ODAD_metadata_xml';
    private const TRANSIENT_JSON = 'ODAD_metadata_json';
    private const TTL            = DAY_IN_SECONDS;

    // -------------------------------------------------------------------------
    // XML
    // -------------------------------------------------------------------------

    /**
     * Retrieve the cached CSDL XML document.
     *
     * @return string|null Cached XML string, or null on cache miss.
     */
    public function get_xml(): ?string {
        $value = get_transient( self::TRANSIENT_XML );
        return ( false === $value ) ? null : (string) $value;
    }

    /**
     * Store a CSDL XML document in the cache.
     *
     * @param string $csdl The serialised CSDL XML document.
     */
    public function set_xml( string $csdl ): void {
        set_transient( self::TRANSIENT_XML, $csdl, self::TTL );
    }

    // -------------------------------------------------------------------------
    // JSON
    // -------------------------------------------------------------------------

    /**
     * Retrieve the cached CSDL JSON document.
     *
     * @return string|null Cached JSON string, or null on cache miss.
     */
    public function get_json(): ?string {
        $value = get_transient( self::TRANSIENT_JSON );
        return ( false === $value ) ? null : (string) $value;
    }

    /**
     * Store a CSDL JSON document in the cache.
     *
     * @param string $csdl The serialised CSDL JSON document.
     */
    public function set_json( string $csdl ): void {
        set_transient( self::TRANSIENT_JSON, $csdl, self::TTL );
    }

    // -------------------------------------------------------------------------
    // Invalidation
    // -------------------------------------------------------------------------

    /**
     * Delete both cached metadata documents.
     *
     * Called by ODAD_Subscriber_Schema_Changed whenever the schema changes so
     * that the next request triggers a full rebuild.
     */
    public function bust(): void {
        delete_transient( self::TRANSIENT_XML );
        delete_transient( self::TRANSIENT_JSON );
    }
}
