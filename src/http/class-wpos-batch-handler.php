<?php
/**
 * ODAD_Batch_Handler — handles OData $batch requests.
 *
 * Supports both:
 *   - JSON batch (OData v4.01): Content-Type: application/json
 *   - Multipart MIME batch (OData v4.0): Content-Type: multipart/mixed; boundary=...
 *
 * Each sub-request is dispatched through the existing router pipeline so that
 * all permission checks, query engine, and write handler logic is exercised
 * identically to non-batch requests.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Batch_Handler {

    /** Maximum number of requests in a single batch. */
    public const MAX_BATCH_REQUESTS = 100;

    /** Maximum number of operations inside a single changeset. */
    public const MAX_CHANGESET_SIZE = 50;

    /**
     * Resolved router instance (populated lazily on first sub-request dispatch).
     */
    private ?ODAD_Router $router_instance = null;

    /**
     * @param \Closure             $router_resolver  Zero-arg closure that returns the ODAD_Router singleton.
     *                                               Using a lazy closure breaks the construction-time circular
     *                                               dependency: ODAD_Router → ODAD_Batch_Handler → ODAD_Router.
     * @param ODAD_Permission_Engine $permissions    Permission engine instance.
     */
    public function __construct(
        private readonly \Closure              $router_resolver,
        private readonly ODAD_Permission_Engine $permissions,
    ) {}

    /**
     * Return the ODAD_Router singleton, resolving it on first call.
     */
    private function router(): ODAD_Router {
        if ( null === $this->router_instance ) {
            $this->router_instance = ( $this->router_resolver )();
        }
        return $this->router_instance;
    }

    // =========================================================================
    // Public entry point
    // =========================================================================

    /**
     * Handle a $batch request.
     * Detects format from Content-Type and delegates to the appropriate handler.
     *
     * @param ODAD_Request $request The incoming batch request.
     * @param \WP_User     $user    The authenticated user.
     * @return WP_REST_Response
     */
    public function handle( ODAD_Request $request, \WP_User $user ): WP_REST_Response {
        // We need the raw WP_REST_Request for headers and raw body; the caller
        // passes the typed ODAD_Request but we also need access to the raw request.
        // We retrieve the global REST server's current request.
        $raw = $this->get_current_wp_request();

        if ( null === $raw ) {
            return ODAD_Error::internal( 'Unable to retrieve current REST request in batch handler.' );
        }

        $content_type = strtolower( (string) ( $raw->get_header( 'content-type' ) ?? '' ) );

        // JSON batch: Content-Type: application/json
        if ( str_contains( $content_type, 'application/json' ) ) {
            $batch_body = $raw->get_json_params();
            if ( ! is_array( $batch_body ) ) {
                return ODAD_Error::bad_request( 'InvalidBatchBody', 'JSON batch body must be a JSON object.' );
            }
            return $this->handle_json( $batch_body, $user );
        }

        // Multipart batch: Content-Type: multipart/mixed; boundary=<value>
        if ( str_contains( $content_type, 'multipart/mixed' ) ) {
            $boundary = $this->parse_boundary( $content_type );
            if ( null === $boundary ) {
                return ODAD_Error::bad_request(
                    'InvalidBatchContentType',
                    'multipart/mixed batch request must include a boundary parameter.'
                );
            }
            $body = $raw->get_body();
            return $this->handle_multipart( $body, $boundary, $user );
        }

        return ODAD_Error::bad_request(
            'UnsupportedBatchFormat',
            'Content-Type must be application/json or multipart/mixed for $batch requests.'
        );
    }

    // =========================================================================
    // JSON Batch (OData v4.01)
    // =========================================================================

    /**
     * Parse and execute a JSON batch request.
     *
     * Expected body shape:
     *   { "requests": [ { "id": "1", "method": "GET", "url": "...", ... }, ... ] }
     *
     * Response shape:
     *   { "responses": [ { "id": "1", "status": 200, "headers": {...}, "body": {...} }, ... ] }
     *
     * @param array    $batch_body Decoded JSON batch body.
     * @param \WP_User $user       The authenticated user.
     * @return WP_REST_Response
     */
    private function handle_json( array $batch_body, \WP_User $user ): WP_REST_Response {
        if ( ! isset( $batch_body['requests'] ) || ! is_array( $batch_body['requests'] ) ) {
            return ODAD_Error::bad_request(
                'InvalidBatchBody',
                'JSON batch body must contain a "requests" array.'
            );
        }

        $items = $batch_body['requests'];

        if ( count( $items ) > self::MAX_BATCH_REQUESTS ) {
            return ODAD_Error::bad_request(
                'BatchTooLarge',
                sprintf(
                    'Batch request exceeds the maximum of %d requests.',
                    self::MAX_BATCH_REQUESTS
                )
            );
        }

        // Keyed by request id → completed response envelope.
        /** @var array<string, array> $completed */
        $completed = [];
        $responses = [];

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                $responses[] = $this->json_error_envelope( '', 400, 'InvalidBatchItem', 'Each batch item must be a JSON object.' );
                continue;
            }

            $id     = (string) ( $item['id'] ?? '' );
            $method = strtoupper( (string) ( $item['method'] ?? '' ) );
            $url    = (string) ( $item['url'] ?? '' );

            if ( '' === $id ) {
                $responses[] = $this->json_error_envelope( $id, 400, 'MissingBatchId', 'Batch item is missing required "id" field.' );
                continue;
            }

            if ( '' === $method ) {
                $responses[] = $this->json_error_envelope( $id, 400, 'MissingBatchMethod', 'Batch item is missing required "method" field.' );
                continue;
            }

            if ( '' === $url ) {
                $responses[] = $this->json_error_envelope( $id, 400, 'MissingBatchUrl', 'Batch item is missing required "url" field.' );
                continue;
            }

            // Resolve dependsOn: all referenced IDs must be already completed.
            $depends_on = isset( $item['dependsOn'] ) && is_array( $item['dependsOn'] )
                ? $item['dependsOn']
                : [];

            $dependency_error = $this->check_json_dependencies( $id, $depends_on, $completed );
            if ( null !== $dependency_error ) {
                $responses[] = $dependency_error;
                $completed[ $id ] = $dependency_error;
                continue;
            }

            // Substitute ${'<depId>'} references in the URL.
            $url = $this->substitute_references( $url, $completed );

            // Item-level headers.
            $item_headers = isset( $item['headers'] ) && is_array( $item['headers'] )
                ? $item['headers']
                : [];

            // Item body (already decoded JSON object or null).
            $item_body = isset( $item['body'] ) && is_array( $item['body'] )
                ? $item['body']
                : [];

            // Execute the sub-request.
            $wp_sub_response = $this->dispatch_sub_request( $method, $url, $item_headers, $item_body, $user );

            $envelope = $this->response_to_json_envelope( $id, $wp_sub_response );
            $responses[]       = $envelope;
            $completed[ $id ]  = $envelope;
        }

        $response_body = [
            '@odata.context' => rest_url( ODAD_Router::NAMESPACE . '/$metadata#Collection($ref)' ),
            'responses'      => $responses,
        ];

        $result = new WP_REST_Response( $response_body, 200 );
        $result->header( ODAD_Response::HEADER_ODATA_VER,    ODAD_Response::ODATA_VERSION );
        $result->header( ODAD_Response::HEADER_CONTENT_TYPE, ODAD_Response::CT_JSON_ODATA );
        return $result;
    }

    // =========================================================================
    // Multipart MIME Batch (OData v4.0)
    // =========================================================================

    /**
     * Parse and execute a multipart MIME batch request.
     *
     * Parts are either:
     *   - Individual HTTP requests (Content-Type: application/http) — executed independently.
     *   - Changesets (Content-Type: multipart/mixed; boundary=...) — executed atomically.
     *
     * @param string   $body     Raw request body.
     * @param string   $boundary MIME boundary value (without leading --).
     * @param \WP_User $user     The authenticated user.
     * @return WP_REST_Response
     */
    private function handle_multipart( string $body, string $boundary, \WP_User $user ): WP_REST_Response {
        $parts = $this->split_multipart( $body, $boundary );

        if ( count( $parts ) > self::MAX_BATCH_REQUESTS ) {
            return ODAD_Error::bad_request(
                'BatchTooLarge',
                sprintf(
                    'Batch request exceeds the maximum of %d parts.',
                    self::MAX_BATCH_REQUESTS
                )
            );
        }

        $response_parts = [];

        foreach ( $parts as $part ) {
            [ $part_headers, $part_body ] = $this->split_headers_body( $part );
            $part_content_type = strtolower( trim( $part_headers['content-type'] ?? '' ) );

            if ( str_contains( $part_content_type, 'multipart/mixed' ) ) {
                // This part is a changeset — execute atomically.
                $cs_boundary = $this->parse_boundary( $part_content_type );
                if ( null === $cs_boundary ) {
                    $response_parts[] = $this->build_http_error_part( 400, 'Missing changeset boundary.' );
                    continue;
                }

                $cs_responses = $this->execute_changeset( $part_body, $cs_boundary, $user );
                $response_parts = array_merge( $response_parts, $cs_responses );

            } elseif ( str_contains( $part_content_type, 'application/http' ) ) {
                // Individual HTTP request part — execute independently.
                $http_response = $this->execute_http_part( $part_body, $user );
                $response_parts[] = $http_response;

            } else {
                $response_parts[] = $this->build_http_error_part(
                    400,
                    "Unsupported batch part Content-Type: {$part_content_type}"
                );
            }
        }

        // Build multipart response body.
        $response_boundary = 'batchresponse_' . wp_generate_uuid4();
        $response_body     = $this->build_multipart_response( $response_parts, $response_boundary );

        $result = new WP_REST_Response( $response_body, 200 );
        $result->header( ODAD_Response::HEADER_ODATA_VER,    ODAD_Response::ODATA_VERSION );
        $result->header(
            ODAD_Response::HEADER_CONTENT_TYPE,
            'multipart/mixed; boundary=' . $response_boundary
        );
        return $result;
    }

    // =========================================================================
    // Changeset execution (atomic)
    // =========================================================================

    /**
     * Execute a changeset atomically (all operations or none).
     *
     * All operations in the changeset are executed; if any fails, all results
     * are replaced with error responses (simulating rollback). In a production
     * implementation backed by a transactional database this would use actual
     * DB transactions; here we apply a best-effort approach.
     *
     * @param string   $cs_body     Raw changeset body.
     * @param string   $cs_boundary Changeset MIME boundary.
     * @param \WP_User $user        The authenticated user.
     * @return string[]  Array of formatted HTTP response part strings.
     */
    private function execute_changeset( string $cs_body, string $cs_boundary, \WP_User $user ): array {
        $cs_parts = $this->split_multipart( $cs_body, $cs_boundary );

        if ( count( $cs_parts ) > self::MAX_CHANGESET_SIZE ) {
            return [
                $this->build_http_error_part(
                    400,
                    sprintf(
                        'Changeset exceeds the maximum of %d operations.',
                        self::MAX_CHANGESET_SIZE
                    )
                ),
            ];
        }

        $results     = [];
        $had_error   = false;
        $error_parts = [];

        try {
            foreach ( $cs_parts as $cs_part ) {
                [ $cs_part_headers, $cs_part_body ] = $this->split_headers_body( $cs_part );
                $cs_part_ct = strtolower( trim( $cs_part_headers['content-type'] ?? '' ) );

                if ( ! str_contains( $cs_part_ct, 'application/http' ) ) {
                    // Changesets must only contain application/http parts (no nested changesets).
                    throw new \RuntimeException(
                        "Changeset parts must be Content-Type: application/http; got: {$cs_part_ct}"
                    );
                }

                $http_response = $this->execute_http_part( $cs_part_body, $user );
                $results[]     = $http_response;

                // Check whether the response represents an error (4xx or 5xx).
                $status = $this->extract_status_from_http_part( $http_response );
                if ( $status >= 400 ) {
                    $had_error = true;
                    break;
                }
            }
        } catch ( \Exception $e ) {
            $had_error   = true;
            $error_parts = [
                $this->build_http_error_part( 500, $e->getMessage() ),
            ];
        }

        if ( $had_error ) {
            // Replace all successful responses with 400 rollback notices and
            // preserve the error response.
            $rollback_results = [];
            foreach ( $results as $idx => $part ) {
                $status = $this->extract_status_from_http_part( $part );
                if ( $status >= 400 ) {
                    // Keep the original error response.
                    $rollback_results[] = $part;
                } else {
                    // Mark previously-successful operations as rolled back.
                    $rollback_results[] = $this->build_http_error_part(
                        400,
                        'Changeset rolled back due to a failure in a subsequent operation.'
                    );
                }
            }
            // If we threw before any result, return only error parts.
            if ( ! empty( $error_parts ) ) {
                return $error_parts;
            }
            return $rollback_results;
        }

        return $results;
    }

    // =========================================================================
    // Sub-request dispatch helpers
    // =========================================================================

    /**
     * Execute a single application/http part.
     * Parses the raw HTTP request line + headers + body, then dispatches.
     *
     * @param string   $part_body Raw text of the HTTP request (request line + headers + body).
     * @param \WP_User $user      The authenticated user.
     * @return string  Formatted HTTP response part string.
     */
    private function execute_http_part( string $part_body, \WP_User $user ): string {
        $part_body = ltrim( $part_body, "\r\n" );

        // First line is the request line, e.g. "GET /odata/v4/Posts?$top=2 HTTP/1.1"
        $line_end    = strpos( $part_body, "\n" );
        $request_line = false !== $line_end
            ? rtrim( substr( $part_body, 0, $line_end ) )
            : rtrim( $part_body );

        $rest = false !== $line_end ? substr( $part_body, $line_end + 1 ) : '';

        // Parse method + URL from the request line.
        $parts  = explode( ' ', $request_line, 3 );
        $method = strtoupper( $parts[0] ?? 'GET' );
        $url    = $parts[1] ?? '/';

        // Parse headers and remaining body from the rest.
        [ $inline_headers, $raw_body ] = $this->split_headers_body( $rest );

        // Decode JSON body if present.
        $item_body = [];
        if ( '' !== trim( $raw_body ) ) {
            $decoded = json_decode( trim( $raw_body ), true );
            if ( is_array( $decoded ) ) {
                $item_body = $decoded;
            }
        }

        $wp_response = $this->dispatch_sub_request( $method, $url, $inline_headers, $item_body, $user );

        return $this->format_http_response_part( $wp_response );
    }

    /**
     * Dispatch a sub-request through the router by constructing a synthetic
     * WP_REST_Request and calling the appropriate router handler.
     *
     * @param string   $method  HTTP method (GET, POST, PATCH, PUT, DELETE).
     * @param string   $url     Relative URL, e.g. "Posts(1)" or "Posts?$filter=..."
     * @param array    $headers Request headers.
     * @param array    $body    Decoded JSON body.
     * @param \WP_User $user    The authenticated user.
     * @return WP_REST_Response
     */
    private function dispatch_sub_request(
        string $method,
        string $url,
        array  $headers,
        array  $body,
        \WP_User $user
    ): WP_REST_Response {
        // Reject external URLs to prevent SSRF.
        if ( ! $this->is_safe_url( $url ) ) {
            return ODAD_Error::bad_request(
                'InvalidBatchUrl',
                'Batch sub-request URL must be a relative path or a local WordPress REST API URL.'
            );
        }

        // Normalize the URL: strip the OData service root prefix if present.
        $url = $this->normalize_sub_request_url( $url );

        // Separate path from query string.
        $query_string = '';
        $q_pos        = strpos( $url, '?' );
        if ( false !== $q_pos ) {
            $query_string = substr( $url, $q_pos + 1 );
            $url          = substr( $url, 0, $q_pos );
        }

        // Parse query string into key→value pairs.
        $query_params = [];
        if ( '' !== $query_string ) {
            parse_str( $query_string, $query_params );
        }

        // Route the URL to extract entity, key, nav.
        $route_match = $this->match_route( $url );

        if ( null === $route_match ) {
            return ODAD_Error::not_found(
                "No route matched for batch sub-request: {$method} {$url}"
            );
        }

        [ $route_type, $path_params ] = $route_match;

        // Build a synthetic WP_REST_Request.
        $wp_request = $this->build_wp_rest_request( $method, $url, $query_params, $headers, $body );

        // Set path params on the request.
        foreach ( $path_params as $key => $value ) {
            $wp_request->set_param( $key, $value );
        }

        // Temporarily set the current user to the batch user so that
        // is_user_logged_in() and wp_get_current_user() return the right user.
        $original_user_id = get_current_user_id();
        wp_set_current_user( $user->ID );

        try {
            $response = $this->call_route_handler( $route_type, $wp_request, $path_params, $method );
        } finally {
            wp_set_current_user( $original_user_id );
        }

        return $response;
    }

    /**
     * Determine which router handler to invoke, based on the parsed route type
     * and HTTP method.
     *
     * @param string          $route_type  One of: 'service', 'metadata', 'entity_collection',
     *                                     'entity_key', 'nav_property', 'count', 'query_post'.
     * @param WP_REST_Request $wp_request  Synthetic WP_REST_Request.
     * @param array           $path_params Extracted path parameters.
     * @param string          $method      HTTP method.
     * @return WP_REST_Response
     */
    private function call_route_handler(
        string          $route_type,
        WP_REST_Request $wp_request,
        array           $path_params,
        string          $method
    ): WP_REST_Response {
        switch ( $route_type ) {
            case 'service':
                return $this->router()->handle_service_document( $wp_request );

            case 'metadata':
                return $this->router()->handle_metadata( $wp_request );

            case 'entity_collection':
                if ( 'GET' === $method ) {
                    return $this->router()->handle_collection( $wp_request );
                }
                if ( 'POST' === $method ) {
                    return $this->router()->handle_create( $wp_request );
                }
                return ODAD_Error::method_not_allowed();

            case 'query_post':
                return $this->router()->handle_query_post( $wp_request );

            case 'entity_key':
                switch ( $method ) {
                    case 'GET':
                        return $this->router()->handle_read( $wp_request );
                    case 'PATCH':
                        return $this->router()->handle_update( $wp_request );
                    case 'PUT':
                        return $this->router()->handle_replace( $wp_request );
                    case 'DELETE':
                        return $this->router()->handle_delete( $wp_request );
                    default:
                        return ODAD_Error::method_not_allowed();
                }

            case 'nav_property':
                return $this->router()->handle_nav_property( $wp_request );

            case 'count':
                if ( 'GET' === $method ) {
                    return $this->router()->handle_count( $wp_request );
                }
                return ODAD_Error::method_not_allowed();

            default:
                return ODAD_Error::not_found( "Unknown route type: {$route_type}" );
        }
    }

    // =========================================================================
    // Route matching
    // =========================================================================

    /**
     * Match a normalized URL path to a route type and extract path parameters.
     *
     * @param string $path URL path without query string, normalized (no leading slash, no service root).
     * @return array{0: string, 1: array}|null  [route_type, path_params] or null.
     */
    private function match_route( string $path ): ?array {
        // Trim leading/trailing slashes for consistent matching.
        $path = trim( $path, '/' );

        // Service document: empty or just "/"
        if ( '' === $path ) {
            return [ 'service', [] ];
        }

        // $metadata
        if ( '$metadata' === $path ) {
            return [ 'metadata', [] ];
        }

        // {entity}/$count
        if ( preg_match( '/^([a-zA-Z0-9_]+)\/\$count$/', $path, $m ) ) {
            return [ 'count', [ 'entity' => $m[1] ] ];
        }

        // {entity}/$query
        if ( preg_match( '/^([a-zA-Z0-9_]+)\/\$query$/', $path, $m ) ) {
            return [ 'query_post', [ 'entity' => $m[1], '_query' => true ] ];
        }

        // {entity}({key})/{nav}
        if ( preg_match( '/^([a-zA-Z0-9_]+)\(([^)]+)\)\/([a-zA-Z0-9_]+)$/', $path, $m ) ) {
            return [ 'nav_property', [ 'entity' => $m[1], 'key' => $m[2], 'nav' => $m[3] ] ];
        }

        // {entity}({key})
        if ( preg_match( '/^([a-zA-Z0-9_]+)\(([^)]+)\)$/', $path, $m ) ) {
            return [ 'entity_key', [ 'entity' => $m[1], 'key' => $m[2] ] ];
        }

        // {entity}
        if ( preg_match( '/^([a-zA-Z0-9_]+)$/', $path, $m ) ) {
            return [ 'entity_collection', [ 'entity' => $m[1] ] ];
        }

        return null;
    }

    // =========================================================================
    // dependsOn reference substitution
    // =========================================================================

    /**
     * Check that all dependsOn IDs are already completed.
     * Returns an error envelope if any dependency is missing/failed, null otherwise.
     *
     * @param string                $id         Current request id.
     * @param string[]              $depends_on List of dependency ids.
     * @param array<string, array>  $completed  Map of id → response envelope.
     * @return array|null  Error envelope or null if all deps are satisfied.
     */
    private function check_json_dependencies( string $id, array $depends_on, array $completed ): ?array {
        foreach ( $depends_on as $dep_id ) {
            $dep_id = (string) $dep_id;
            if ( ! isset( $completed[ $dep_id ] ) ) {
                return $this->json_error_envelope(
                    $id,
                    424,
                    'DependencyFailed',
                    "Dependency '{$dep_id}' has not been completed or does not exist."
                );
            }
            // If the dependency itself failed (4xx/5xx), skip this request too.
            if ( ( $completed[ $dep_id ]['status'] ?? 200 ) >= 400 ) {
                return $this->json_error_envelope(
                    $id,
                    424,
                    'DependencyFailed',
                    "Dependency '{$dep_id}' failed with status {$completed[$dep_id]['status']}."
                );
            }
        }
        return null;
    }

    /**
     * Replace all `${'<depId>'}` placeholders in a URL string with the
     * corresponding response key from a completed dependency.
     *
     * The "response key" is derived as follows (in priority order):
     *   1. The numeric/string entity key from the Location header of the dep response.
     *   2. The first scalar value from the body of the dep response (fallback).
     *
     * @param string               $url       The URL that may contain placeholders.
     * @param array<string, array> $completed Map of id → response envelope.
     * @return string  The URL with all placeholders resolved.
     */
    private function substitute_references( string $url, array $completed ): string {
        return preg_replace_callback(
            '/\$\{\'([^\']+)\'\}/',
            function ( array $matches ) use ( $completed ): string {
                $dep_id = $matches[1];
                if ( ! isset( $completed[ $dep_id ] ) ) {
                    // Leave placeholder intact if the dep is unknown.
                    return $matches[0];
                }
                $envelope = $completed[ $dep_id ];
                return $this->extract_response_key( $envelope );
            },
            $url
        );
    }

    /**
     * Extract the entity key from a JSON batch response envelope.
     * Checks the Location header first, then the response body.
     *
     * @param array $envelope Response envelope.
     * @return string  The resolved key value as a string, or empty string.
     */
    private function extract_response_key( array $envelope ): string {
        // Try Location header: .../Posts(42) → "42"
        $location = $envelope['headers']['Location'] ?? '';
        if ( '' !== $location ) {
            if ( preg_match( '/\(([^)]+)\)\s*$/', $location, $m ) ) {
                return $m[1];
            }
        }

        // Fallback: look for a common key property in the body.
        $body = $envelope['body'] ?? [];
        if ( is_array( $body ) ) {
            foreach ( [ 'ID', 'Id', 'id' ] as $key_prop ) {
                if ( isset( $body[ $key_prop ] ) && is_scalar( $body[ $key_prop ] ) ) {
                    return (string) $body[ $key_prop ];
                }
            }
        }

        return '';
    }

    // =========================================================================
    // WP_REST_Request construction
    // =========================================================================

    /**
     * Build a synthetic WP_REST_Request for a batch sub-request.
     *
     * @param string $method        HTTP method.
     * @param string $path          URL path (without query string).
     * @param array  $query_params  Parsed query string parameters.
     * @param array  $headers       Request headers.
     * @param array  $body          Decoded JSON body.
     * @return WP_REST_Request
     */
    private function build_wp_rest_request(
        string $method,
        string $path,
        array  $query_params,
        array  $headers,
        array  $body
    ): WP_REST_Request {
        $full_route = '/' . ODAD_Router::NAMESPACE . '/' . ltrim( $path, '/' );
        $wp_request = new WP_REST_Request( $method, $full_route );

        // Set query string params.
        foreach ( $query_params as $key => $value ) {
            $wp_request->set_param( $key, $value );
        }

        // Set headers.
        foreach ( $headers as $header_name => $header_value ) {
            $wp_request->add_header( $header_name, $header_value );
        }

        // Set JSON body.
        if ( ! empty( $body ) ) {
            $wp_request->set_body( wp_json_encode( $body ) );
            $wp_request->add_header( 'content-type', 'application/json' );
        }

        return $wp_request;
    }

    // =========================================================================
    // URL normalization
    // =========================================================================

    /**
     * Return true if $url is safe to use as a batch sub-request URL.
     *
     * Allows:
     *   - Relative paths starting with '/' (e.g. /wp-json/odata/v4/Posts)
     *   - Bare relative paths without a scheme (e.g. Posts, $metadata)
     *
     * Disallows:
     *   - Absolute URLs with http:// or https:// pointing to any host
     *     (including the local host) to prevent SSRF via batch requests.
     *
     * @param string $url Raw URL from the batch item.
     * @return bool
     */
    private function is_safe_url( string $url ): bool {
        // Allow relative URLs starting with /
        if ( str_starts_with( $url, '/' ) ) {
            return true;
        }
        // Allow bare relative paths (no scheme, e.g. "Posts(1)", "$metadata")
        if ( ! str_contains( $url, '://' ) ) {
            return true;
        }
        // Disallow absolute URLs to external hosts
        return false;
    }

    /**
     * Strip the OData service root prefix from a sub-request URL, leaving
     * only the path relative to the service root (e.g. "Posts(1)").
     *
     * Handles:
     *   - Absolute URLs: https://example.com/wp-json/odata/v4/Posts(1)
     *   - Absolute paths: /wp-json/odata/v4/Posts(1)
     *   - Namespace-prefixed: odata/v4/Posts(1)
     *   - Already-relative: Posts(1)
     *
     * @param string $url The raw URL from the batch item.
     * @return string  Relative path, e.g. "Posts(1)" or "$metadata".
     */
    private function normalize_sub_request_url( string $url ): string {
        // Strip query string for path normalization (caller already split it).
        $q_pos = strpos( $url, '?' );
        $qs    = '';
        if ( false !== $q_pos ) {
            $qs  = substr( $url, $q_pos );
            $url = substr( $url, 0, $q_pos );
        }

        // Strip scheme + host if present.
        $url = preg_replace( '#^https?://[^/]+#', '', $url );

        // Strip WordPress REST API base paths.
        $rest_base = trailingslashit( rest_get_url_prefix() );  // e.g. "wp-json/"
        $odata_ns  = ODAD_Router::NAMESPACE . '/';              // e.g. "odata/v4/"

        foreach ( [
            '/' . $rest_base . $odata_ns,
            '/' . $odata_ns,
            $rest_base . $odata_ns,
            $odata_ns,
        ] as $prefix ) {
            if ( str_starts_with( $url, $prefix ) ) {
                $url = substr( $url, strlen( $prefix ) );
                break;
            }
        }

        return ltrim( $url, '/' ) . $qs;
    }

    // =========================================================================
    // Multipart parsing helpers
    // =========================================================================

    /**
     * Split a multipart body into individual part strings.
     *
     * @param string $body     Raw multipart body.
     * @param string $boundary MIME boundary value (without leading --).
     * @return string[]  Array of raw part bodies (headers + body each).
     */
    private function split_multipart( string $body, string $boundary ): array {
        $delimiter   = '--' . $boundary;
        $terminator  = '--' . $boundary . '--';
        $parts       = [];

        // Normalize line endings.
        $body = str_replace( "\r\n", "\n", $body );

        $segments = explode( $delimiter, $body );

        foreach ( $segments as $segment ) {
            $segment = ltrim( $segment, "\n" );

            // Skip preamble (before first boundary) and epilogue (after terminator).
            if ( '' === trim( $segment ) ) {
                continue;
            }

            // Strip the trailing "--" and anything after it (terminator end).
            if ( str_starts_with( $segment, '--' ) ) {
                continue;
            }

            // Remove leading/trailing CRLF/LF.
            $segment = ltrim( $segment, "\n\r" );
            $segment = rtrim( $segment, "\n\r-" );

            if ( '' !== trim( $segment ) ) {
                $parts[] = $segment;
            }
        }

        return $parts;
    }

    /**
     * Split a part into its headers array and raw body string.
     *
     * The headers and body are separated by a blank line (\n\n or \r\n\r\n).
     *
     * @param string $part Raw part text.
     * @return array{0: array<string, string>, 1: string}  [headers, body]
     */
    private function split_headers_body( string $part ): array {
        $part = str_replace( "\r\n", "\n", $part );

        $blank_line = strpos( $part, "\n\n" );
        if ( false === $blank_line ) {
            // No body section.
            return [ $this->parse_headers( $part ), '' ];
        }

        $header_section = substr( $part, 0, $blank_line );
        $body_section   = substr( $part, $blank_line + 2 );

        return [ $this->parse_headers( $header_section ), $body_section ];
    }

    /**
     * Parse a raw header block into a lowercase key → value array.
     *
     * @param string $header_block Raw header text.
     * @return array<string, string>
     */
    private function parse_headers( string $header_block ): array {
        $headers = [];
        $lines   = explode( "\n", $header_block );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line ) {
                continue;
            }
            $colon = strpos( $line, ':' );
            if ( false === $colon ) {
                continue;
            }
            $name            = strtolower( trim( substr( $line, 0, $colon ) ) );
            $value           = trim( substr( $line, $colon + 1 ) );
            $headers[ $name ] = $value;
        }
        return $headers;
    }

    /**
     * Extract the MIME boundary value from a Content-Type header string.
     *
     * @param string $content_type Full Content-Type header value.
     * @return string|null  The boundary value without leading --, or null.
     */
    private function parse_boundary( string $content_type ): ?string {
        if ( preg_match( '/boundary\s*=\s*"?([^";,\s]+)"?/i', $content_type, $m ) ) {
            return $m[1];
        }
        return null;
    }

    // =========================================================================
    // Response formatting helpers
    // =========================================================================

    /**
     * Build a formatted HTTP response part string for embedding in a multipart body.
     *
     * @param WP_REST_Response $response The response to format.
     * @return string
     */
    private function format_http_response_part( WP_REST_Response $response ): string {
        $status  = $response->get_status();
        $headers = $response->get_headers();
        $data    = $response->get_data();

        $body = '';
        if ( null !== $data ) {
            $body = wp_json_encode( $data );
        }

        $reason = $this->http_reason_phrase( $status );
        $lines  = [ "HTTP/1.1 {$status} {$reason}" ];

        foreach ( $headers as $name => $value ) {
            $lines[] = "{$name}: {$value}";
        }

        if ( '' !== $body ) {
            $lines[] = 'Content-Type: application/json;odata.metadata=minimal';
            $lines[] = 'Content-Length: ' . strlen( $body );
            $lines[] = '';
            $lines[] = $body;
        }

        return implode( "\r\n", $lines );
    }

    /**
     * Build a simple HTTP error response part string (no real WP_REST_Response).
     *
     * @param int    $status  HTTP status code.
     * @param string $message Error message.
     * @return string
     */
    private function build_http_error_part( int $status, string $message ): string {
        $reason  = $this->http_reason_phrase( $status );
        $body    = wp_json_encode( [
            'error' => [
                'code'    => 'BatchError',
                'message' => $message,
            ],
        ] );
        $lines   = [
            "HTTP/1.1 {$status} {$reason}",
            'Content-Type: application/json;odata.metadata=minimal',
            'Content-Length: ' . strlen( $body ),
            '',
            $body,
        ];
        return implode( "\r\n", $lines );
    }

    /**
     * Build the full multipart response body from an array of part strings.
     *
     * @param string[] $parts    Individual response part strings.
     * @param string   $boundary The response boundary.
     * @return string
     */
    private function build_multipart_response( array $parts, string $boundary ): string {
        $output = '';
        foreach ( $parts as $part ) {
            $output .= "--{$boundary}\r\n";
            $output .= "Content-Type: application/http\r\n";
            $output .= "\r\n";
            $output .= $part;
            $output .= "\r\n";
        }
        $output .= "--{$boundary}--\r\n";
        return $output;
    }

    /**
     * Extract the HTTP status code from a formatted HTTP response part string.
     *
     * @param string $part Formatted HTTP response part.
     * @return int  The status code, or 200 if not found.
     */
    private function extract_status_from_http_part( string $part ): int {
        $first_line = strtok( str_replace( "\r\n", "\n", $part ), "\n" );
        if ( preg_match( '/HTTP\/\S+\s+(\d{3})/', (string) $first_line, $m ) ) {
            return (int) $m[1];
        }
        return 200;
    }

    /**
     * Convert a WP_REST_Response to a JSON batch response envelope array.
     *
     * @param string           $id       The batch request ID.
     * @param WP_REST_Response $response The response to envelope.
     * @return array
     */
    private function response_to_json_envelope( string $id, WP_REST_Response $response ): array {
        $status  = $response->get_status();
        $headers = $response->get_headers();
        $data    = $response->get_data();

        $envelope = [
            'id'      => $id,
            'status'  => $status,
            'headers' => $headers,
        ];

        if ( null !== $data ) {
            $envelope['body'] = $data;
        }

        return $envelope;
    }

    /**
     * Build a JSON batch error envelope directly (without dispatching a sub-request).
     *
     * @param string $id      Batch request ID.
     * @param int    $status  HTTP status code.
     * @param string $code    OData error code.
     * @param string $message Human-readable message.
     * @return array
     */
    private function json_error_envelope( string $id, int $status, string $code, string $message ): array {
        return [
            'id'      => $id,
            'status'  => $status,
            'headers' => [
                ODAD_Response::HEADER_CONTENT_TYPE => ODAD_Response::CT_JSON_ODATA,
                ODAD_Response::HEADER_ODATA_VER    => ODAD_Response::ODATA_VERSION,
            ],
            'body'    => [
                'error' => [
                    'code'    => $code,
                    'message' => $message,
                ],
            ],
        ];
    }

    /**
     * Map an HTTP status code to its standard reason phrase.
     *
     * @param int $status HTTP status code.
     * @return string  The reason phrase.
     */
    private function http_reason_phrase( int $status ): string {
        $phrases = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            424 => 'Failed Dependency',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
        ];
        return $phrases[ $status ] ?? 'Unknown';
    }

    // =========================================================================
    // Misc helpers
    // =========================================================================

    /**
     * Retrieve the current WP_REST_Request being processed by the REST server.
     *
     * WordPress stores this on the global $wp_rest_server or it can be accessed
     * via the rest_get_server() function. We use a direct approach here.
     *
     * @return WP_REST_Request|null
     */
    private function get_current_wp_request(): ?WP_REST_Request {
        // The REST server stores the current request in WP_REST_Server::$current_route
        // but not the request itself. We use a filter-based approach.
        if ( isset( $GLOBALS['ODAD_current_batch_request'] )
            && $GLOBALS['ODAD_current_batch_request'] instanceof WP_REST_Request
        ) {
            return $GLOBALS['ODAD_current_batch_request'];
        }
        return null;
    }
}
