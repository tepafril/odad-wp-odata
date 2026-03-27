<?php
/**
 * ODAD_Error — OData-formatted error response factory.
 *
 * OData error shape:
 *   { "error": { "code": "<string>", "message": "<string>" } }
 *
 * All responses include OData-Version and Content-Type headers.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Error {

    /**
     * 404 Not Found.
     *
     * @param string $message Optional human-readable detail.
     * @return WP_REST_Response
     */
    public static function not_found( string $message = '' ): WP_REST_Response {
        return self::make(
            404,
            'ResourceNotFound',
            '' !== $message ? $message : 'The requested resource was not found.',
        );
    }

    /**
     * 403 Forbidden.
     *
     * @param string $message Optional human-readable detail.
     * @return WP_REST_Response
     */
    public static function forbidden( string $message = '' ): WP_REST_Response {
        return self::make(
            403,
            'AccessDenied',
            '' !== $message ? $message : 'Access to this resource is forbidden.',
        );
    }

    /**
     * 400 Bad Request.
     *
     * @param string $code    Machine-readable OData error code.
     * @param string $message Human-readable description of the problem.
     * @return WP_REST_Response
     */
    public static function bad_request( string $code, string $message ): WP_REST_Response {
        return self::make( 400, $code, $message );
    }

    /**
     * 405 Method Not Allowed.
     *
     * @return WP_REST_Response
     */
    public static function method_not_allowed(): WP_REST_Response {
        return self::make( 405, 'MethodNotAllowed', 'The HTTP method is not allowed for this resource.' );
    }

    /**
     * 500 Internal Server Error.
     *
     * @param string $message Optional human-readable detail.
     * @return WP_REST_Response
     */
    public static function internal( string $message = '' ): WP_REST_Response {
        $detail = defined( 'WP_DEBUG' ) && WP_DEBUG ? $message : 'An internal error occurred.';
        return self::make( 500, 'InternalError', $detail );
    }

    /**
     * 501 Not Implemented.
     *
     * @param string $message Optional human-readable detail.
     * @return WP_REST_Response
     */
    public static function not_implemented( string $message = '' ): WP_REST_Response {
        return self::make(
            501,
            'NotImplemented',
            '' !== $message ? $message : 'This endpoint is not yet implemented.',
        );
    }

    /**
     * Convert a WP_Error into an OData-formatted WP_REST_Response.
     *
     * Uses the first error code and message from the WP_Error object.
     * HTTP status is derived from the error data 'status' field when available;
     * defaults to 400.
     *
     * @param WP_Error $error Source WordPress error.
     * @return WP_REST_Response
     */
    public static function from_wp_error( WP_Error $error ): WP_REST_Response {
        $code    = (string) ( $error->get_error_code() ?? 'UnknownError' );
        $message = (string) ( $error->get_error_message() ?? 'An unknown error occurred.' );

        // Try to get an HTTP status from the error data.
        $data   = $error->get_error_data( $code );
        $status = 400;
        if ( is_array( $data ) && isset( $data['status'] ) ) {
            $status = (int) $data['status'];
        } elseif ( is_int( $data ) && $data >= 100 ) {
            $status = $data;
        }

        return self::make( $status, $code, $message );
    }

    // -------------------------------------------------------------------------
    // Internal helper
    // -------------------------------------------------------------------------

    /**
     * Build an OData error response body and attach standard headers.
     *
     * @param int    $status  HTTP status code.
     * @param string $code    OData error code string.
     * @param string $message Human-readable message.
     * @return WP_REST_Response
     */
    private static function make( int $status, string $code, string $message ): WP_REST_Response {
        $body = [
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
        ];

        $response = new WP_REST_Response( $body, $status );
        $response->header( ODAD_Response::HEADER_ODATA_VER,    ODAD_Response::ODATA_VERSION );
        $response->header( ODAD_Response::HEADER_CONTENT_TYPE, ODAD_Response::CT_JSON_ODATA );
        return $response;
    }
}
