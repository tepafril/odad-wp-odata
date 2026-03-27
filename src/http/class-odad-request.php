<?php
/**
 * ODAD_Request — typed OData request parsed from a WP_REST_Request.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Request {

    /** Default page size enforced when client omits $top. */
    public const DEFAULT_TOP = 100;

    /** Hard cap on page size; values above this are silently clamped. */
    public const MAX_TOP = 1000;

    /**
     * @param string      $entity_set    Entity-set name, e.g. 'Posts'.
     * @param string      $method        HTTP verb: GET | POST | PATCH | PUT | DELETE.
     * @param mixed|null  $key           Entity key value, null for a collection request.
     * @param string|null $nav_property  Navigation property segment, if present.
     * @param string|null $filter        Raw $filter query string value.
     * @param string|null $select        Raw $select query string value.
     * @param string|null $expand        Raw $expand query string value.
     * @param string|null $orderby       Raw $orderby query string value.
     * @param int|null    $top           $top value (default 100, max 1000).
     * @param int|null    $skip          $skip value.
     * @param bool        $count         Whether $count=true was requested.
     * @param string|null $search        Raw $search string.
     * @param string|null $compute       Raw $compute string.
     * @param array       $body          Parsed JSON request body.
     * @param string|null $format        $format override value.
     * @param string|null $prefer        Value of the Prefer request header.
     * @param bool        $is_batch      Whether this is a /$batch request.
     * @param bool        $is_query_post Whether this is a /$query POST request.
     */
    public function __construct(
        public readonly string  $entity_set,
        public readonly string  $method,
        public readonly mixed   $key,
        public readonly ?string $nav_property,
        public readonly ?string $filter,
        public readonly ?string $select,
        public readonly ?string $expand,
        public readonly ?string $orderby,
        public readonly ?int    $top,
        public readonly ?int    $skip,
        public readonly bool    $count,
        public readonly ?string $search,
        public readonly ?string $compute,
        public readonly array   $body,
        public readonly ?string $format,
        public readonly ?string $prefer,
        public readonly bool    $is_batch,
        public readonly bool    $is_query_post,
    ) {}

    /**
     * Build a ODAD_Request from an incoming WP_REST_Request and the matched
     * path parameters extracted by the router.
     *
     * Expected path params:
     *   entity  — entity-set name (always present for entity routes)
     *   key     — (optional) entity key segment
     *   nav     — (optional) navigation property segment
     *
     * @param WP_REST_Request $wp_request  The incoming WordPress REST request.
     * @param array           $path_params Path parameters parsed from the route pattern.
     * @return self
     */
    public static function from_wp( WP_REST_Request $wp_request, array $path_params ): self {
        $method     = strtoupper( $wp_request->get_method() );
        $entity_set = $path_params['entity'] ?? '';
        $key        = isset( $path_params['key'] ) && '' !== $path_params['key']
                        ? $path_params['key']
                        : null;
        $nav        = isset( $path_params['nav'] ) && '' !== $path_params['nav']
                        ? $path_params['nav']
                        : null;

        // OData system query options — prefixed with '$' in the URL.
        $filter  = self::str_param( $wp_request, '$filter' );
        $select  = self::str_param( $wp_request, '$select' );
        $expand  = self::str_param( $wp_request, '$expand' );
        $orderby = self::str_param( $wp_request, '$orderby' );
        $search  = self::str_param( $wp_request, '$search' );
        $compute = self::str_param( $wp_request, '$compute' );
        $format  = self::str_param( $wp_request, '$format' );

        // $top — default 100, hard max 1000.
        $top_raw = $wp_request->get_param( '$top' );
        if ( null === $top_raw || '' === $top_raw ) {
            $top = self::DEFAULT_TOP;
        } else {
            $top = (int) $top_raw;
            if ( $top > self::MAX_TOP ) {
                $top = self::MAX_TOP;
            }
            if ( $top < 0 ) {
                $top = 0;
            }
        }

        // $skip.
        $skip_raw = $wp_request->get_param( '$skip' );
        $skip     = ( null !== $skip_raw && '' !== $skip_raw ) ? (int) $skip_raw : null;

        // $count=true.
        $count_raw = $wp_request->get_param( '$count' );
        $count     = in_array( strtolower( (string) $count_raw ), [ 'true', '1' ], true );

        // Prefer header.
        $prefer = $wp_request->get_header( 'prefer' ) ?: null;

        // Parsed JSON body (empty array for requests without a body).
        $json_body = $wp_request->get_json_params();
        $body      = is_array( $json_body ) ? $json_body : [];

        // Batch / query-POST flags.
        $is_batch      = ( '' === $entity_set ) && ( 'POST' === $method )
                         && isset( $path_params['_batch'] );
        $is_query_post = ( '' !== $entity_set ) && ( 'POST' === $method )
                         && isset( $path_params['_query'] );

        return new self(
            entity_set:    $entity_set,
            method:        $method,
            key:           $key,
            nav_property:  $nav,
            filter:        $filter,
            select:        $select,
            expand:        $expand,
            orderby:       $orderby,
            top:           $top,
            skip:          $skip,
            count:         $count,
            search:        $search,
            compute:       $compute,
            body:          $body,
            format:        $format,
            prefer:        $prefer,
            is_batch:      $is_batch,
            is_query_post: $is_query_post,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return a trimmed string param or null if absent/empty.
     */
    private static function str_param( WP_REST_Request $req, string $param ): ?string {
        $val = $req->get_param( $param );
        if ( null === $val || '' === $val ) {
            return null;
        }
        $trimmed = trim( (string) $val );
        return '' !== $trimmed ? $trimmed : null;
    }
}
