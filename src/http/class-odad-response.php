<?php
/**
 * ODAD_Response — factory for OData-compliant WP_REST_Response objects.
 *
 * All JSON responses carry:
 *   Content-Type : application/json;odata.metadata=minimal;odata.streaming=true
 *   OData-Version: 4.01
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Response {

    // -------------------------------------------------------------------------
    // Header constants
    // -------------------------------------------------------------------------

    public const ODATA_VERSION       = '4.01';
    public const CT_JSON_ODATA       = 'application/json;odata.metadata=minimal;odata.streaming=true';
    public const CT_XML              = 'application/xml';
    public const CT_JSON             = 'application/json';
    public const HEADER_ODATA_VER    = 'OData-Version';
    public const HEADER_CONTENT_TYPE = 'Content-Type';

    // -------------------------------------------------------------------------
    // Collection / entity responses
    // -------------------------------------------------------------------------

    /**
     * OData collection response.
     *
     * Produces:
     * {
     *   "@odata.context": "<context_url>",
     *   "@odata.count":   <total_count>,   // only when provided
     *   "@odata.nextLink": "<url>",         // only when provided
     *   "value": [ ... ]
     * }
     *
     * @param array       $rows        Array of entity arrays.
     * @param string      $context_url The @odata.context URL.
     * @param int|null    $total_count Total row count for $count=true; omitted when null.
     * @param string|null $next_link   Next-page URL for server-side pagination; omitted when null.
     * @return WP_REST_Response
     */
    public static function collection(
        array   $rows,
        string  $context_url,
        ?int    $total_count = null,
        ?string $next_link   = null,
    ): WP_REST_Response {
        $body = [ '@odata.context' => $context_url ];

        if ( null !== $total_count ) {
            $body['@odata.count'] = $total_count;
        }

        if ( null !== $next_link ) {
            $body['@odata.nextLink'] = $next_link;
        }

        $body['value'] = $rows;

        return self::json_response( $body, 200 );
    }

    /**
     * Single-entity response.
     *
     * @param array  $row         Entity data as key → value array.
     * @param string $context_url The @odata.context URL.
     * @return WP_REST_Response
     */
    public static function entity( array $row, string $context_url ): WP_REST_Response {
        $body = array_merge( [ '@odata.context' => $context_url ], $row );
        return self::json_response( $body, 200 );
    }

    /**
     * 201 Created response with a Location header.
     *
     * @param array  $row        The created entity.
     * @param string $entity_url Absolute URL to the newly created entity.
     * @return WP_REST_Response
     */
    public static function created( array $row, string $entity_url ): WP_REST_Response {
        $response = self::json_response( $row, 201 );
        $response->header( 'Location', $entity_url );
        return $response;
    }

    /**
     * 204 No Content (used for successful PATCH/PUT/DELETE with no response body).
     *
     * @return WP_REST_Response
     */
    public static function no_content(): WP_REST_Response {
        $response = new WP_REST_Response( null, 204 );
        $response->header( self::HEADER_ODATA_VER,    self::ODATA_VERSION );
        $response->header( self::HEADER_CONTENT_TYPE, self::CT_JSON_ODATA );
        return $response;
    }

    // -------------------------------------------------------------------------
    // Metadata responses
    // -------------------------------------------------------------------------

    /**
     * $metadata XML response (CSDL).
     *
     * @param string $csdl Raw CSDL XML string.
     * @return WP_REST_Response
     */
    public static function metadata_xml( string $csdl ): WP_REST_Response {
        $response = new WP_REST_Response( $csdl, 200 );
        $response->header( self::HEADER_ODATA_VER,    self::ODATA_VERSION );
        $response->header( self::HEADER_CONTENT_TYPE, self::CT_XML );
        return $response;
    }

    /**
     * $metadata JSON response.
     *
     * @param string $csdl Raw CSDL JSON string (may be minimal stub).
     * @return WP_REST_Response
     */
    public static function metadata_json( string $csdl ): WP_REST_Response {
        // $csdl is already a JSON string; decode it so WP_REST_Response re-encodes it.
        $decoded = json_decode( $csdl, true );
        $body    = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) )
                    ? $decoded
                    : [ 'value' => $csdl ];

        return self::json_response( $body, 200 );
    }

    /**
     * OData service document.
     *
     * @param array  $entity_sets Flat list of entity-set names, e.g. ['Posts', 'Users'].
     * @param string $base_url    Base service URL, e.g. https://example.com/wp-json/odata/v4/.
     * @return WP_REST_Response
     */
    public static function service_document( array $entity_sets, string $base_url ): WP_REST_Response {
        $base_url = rtrim( $base_url, '/' ) . '/';

        $value = [];
        foreach ( $entity_sets as $name ) {
            $value[] = [
                'name' => $name,
                'kind' => 'EntitySet',
                'url'  => $name,
            ];
        }

        $body = [
            '@odata.context' => $base_url . '$metadata',
            'value'          => $value,
        ];

        return self::json_response( $body, 200 );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build a JSON WP_REST_Response with standard OData headers.
     *
     * @param mixed $body        Response body (will be JSON-encoded by WP).
     * @param int   $status_code HTTP status code.
     * @return WP_REST_Response
     */
    private static function json_response( mixed $body, int $status_code ): WP_REST_Response {
        $response = new WP_REST_Response( $body, $status_code );
        $response->header( self::HEADER_ODATA_VER,    self::ODATA_VERSION );
        $response->header( self::HEADER_CONTENT_TYPE, self::CT_JSON_ODATA );
        return $response;
    }
}
