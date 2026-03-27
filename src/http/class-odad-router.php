<?php
/**
 * ODAD_Router — registers all OData REST routes with WP_REST_Server.
 *
 * Base namespace : odata/v4
 *
 * Phase 1 behaviour
 * - GET /                 → service_document()   (functional)
 * - GET /$metadata        → metadata()            (functional)
 * - All entity CRUD routes return 501 Not Implemented stubs until later phases.
 * - $batch returns 501.
 *
 * Phase 5 additions
 * - GET  /odata/v4/{NS.Function}(...)                         → handle_function()
 * - GET  /odata/v4/{entity}/{NS.Function}(...)                → handle_function()  (bound to entity set)
 * - GET  /odata/v4/{entity}({key})/{NS.Function}(...)         → handle_function()  (bound to entity)
 * - POST /odata/v4/{NS.Action}                                → handle_action()
 * - POST /odata/v4/{entity}({key})/{NS.Action}                → handle_action()    (bound to entity)
 * - GET  /odata/v4/$status/{job_id}                           → handle_async_status()
 * - Delta token ($deltatoken param) injected into query context.
 * - Prefer: respond-async delegates to ODAD_Async_Handler.
 *
 * Injected services:
 *   ODAD_Query_Engine        — may be null in Phase 1
 *   ODAD_Write_Handler       — may be null in Phase 1
 *   ODAD_Metadata_Builder    — required; must be functional in Phase 1
 *   ODAD_Permission_Engine   — may be null in Phase 1
 *   ODAD_Function_Registry   — may be null pre-Phase-5
 *   ODAD_Action_Registry     — may be null pre-Phase-5
 *   ODAD_Async_Handler       — may be null pre-Phase-5
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Router {

    /** WP REST API namespace for all OData endpoints. */
    public const NAMESPACE = 'odata/v4';

    /**
     * @param object|null              $query_engine       ODAD_Query_Engine instance or null stub.
     * @param object|null              $write_handler      ODAD_Write_Handler instance or null stub.
     * @param object                   $metadata_builder   ODAD_Metadata_Builder instance (required).
     * @param object|null              $permission_engine  ODAD_Permission_Engine instance or null stub.
     * @param ODAD_Hook_Bridge|null    $bridge             Hook bridge for applying WP filters.
     * @param ODAD_Function_Registry|null $function_registry OData function registry.
     * @param ODAD_Action_Registry|null   $action_registry   OData action registry.
     * @param ODAD_Async_Handler|null     $async_handler     Async request handler.
     * @param ODAD_Batch_Handler|null     $batch_handler     Batch request handler.
     */
    public function __construct(
        private readonly mixed                    $query_engine,
        private readonly mixed                    $write_handler,
        private readonly mixed                    $metadata_builder,
        private readonly mixed                    $permission_engine,
        private readonly ?ODAD_Hook_Bridge        $bridge            = null,
        private readonly ?ODAD_Function_Registry  $function_registry = null,
        private readonly ?ODAD_Action_Registry    $action_registry   = null,
        private readonly ?ODAD_Async_Handler      $async_handler     = null,
        private readonly ?ODAD_Batch_Handler      $batch_handler     = null,
    ) {}

    /**
     * Register all OData routes with WP_REST_Server.
     *
     * Must be called during or after the rest_api_init action.
     */
    public function register_routes(): void {
        $ns = self::NAMESPACE;

        // ------------------------------------------------------------------
        // Service document  GET /odata/v4/
        // ------------------------------------------------------------------
        register_rest_route( $ns, '/', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'handle_service_document' ],
            'permission_callback' => '__return_true',
        ] );

        // ------------------------------------------------------------------
        // $metadata  GET /odata/v4/$metadata
        // ------------------------------------------------------------------
        register_rest_route( $ns, '/\$metadata', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'handle_metadata' ],
            'permission_callback' => '__return_true',
        ] );

        // ------------------------------------------------------------------
        // $batch  POST /odata/v4/$batch  [stub: 501]
        // ------------------------------------------------------------------
        register_rest_route( $ns, '/\$batch', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_batch' ],
            'permission_callback' => '__return_true',
        ] );

        // ------------------------------------------------------------------
        // Entity collection
        //   GET  /odata/v4/{entity}         → collection()  [stub: 501]
        //   POST /odata/v4/{entity}         → create()      [stub: 501]
        //   POST /odata/v4/{entity}/$query  → query_post()  [stub: 501]
        // ------------------------------------------------------------------
        register_rest_route( $ns, '/(?P<entity>[a-zA-Z0-9_]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle_collection' ],
                'permission_callback' => '__return_true',
                'args'                => [ 'entity' => [ 'required' => true ] ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_create' ],
                'permission_callback' => '__return_true',
                'args'                => [ 'entity' => [ 'required' => true ] ],
            ],
        ] );

        register_rest_route( $ns, '/(?P<entity>[a-zA-Z0-9_]+)/\$query', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_query_post' ],
            'permission_callback' => '__return_true',
            'args'                => [ 'entity' => [ 'required' => true ] ],
        ] );

        // ------------------------------------------------------------------
        // Entity by key
        //   GET    /odata/v4/{entity}({key})  → read()    [stub: 501]
        //   PATCH  /odata/v4/{entity}({key})  → update()  [stub: 501]
        //   PUT    /odata/v4/{entity}({key})  → replace() [stub: 501]
        //   DELETE /odata/v4/{entity}({key})  → delete()  [stub: 501]
        // ------------------------------------------------------------------
        register_rest_route( $ns, '/(?P<entity>[a-zA-Z0-9_]+)\((?P<key>[^)]+)\)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle_read' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'entity' => [ 'required' => true ],
                    'key'    => [ 'required' => true ],
                ],
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [ $this, 'handle_update' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'entity' => [ 'required' => true ],
                    'key'    => [ 'required' => true ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'handle_replace' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'entity' => [ 'required' => true ],
                    'key'    => [ 'required' => true ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'handle_delete' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'entity' => [ 'required' => true ],
                    'key'    => [ 'required' => true ],
                ],
            ],
        ] );

        // ------------------------------------------------------------------
        // Navigation property
        //   GET /odata/v4/{entity}({key})/{nav}  [stub: 501]
        // ------------------------------------------------------------------
        register_rest_route(
            $ns,
            '/(?P<entity>[a-zA-Z0-9_]+)\((?P<key>[^)]+)\)/(?P<nav>[a-zA-Z0-9_]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle_nav_property' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'entity' => [ 'required' => true ],
                    'key'    => [ 'required' => true ],
                    'nav'    => [ 'required' => true ],
                ],
            ]
        );

        // ------------------------------------------------------------------
        // $count on a collection
        //   GET /odata/v4/{entity}/$count  [stub: 501]
        // ------------------------------------------------------------------
        register_rest_route( $ns, '/(?P<entity>[a-zA-Z0-9_]+)/\$count', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'handle_count' ],
            'permission_callback' => '__return_true',
            'args'                => [ 'entity' => [ 'required' => true ] ],
        ] );

        // ------------------------------------------------------------------
        // Async job status
        //   GET /odata/v4/$status/{job_id}
        // ------------------------------------------------------------------
        register_rest_route( $ns, '/\$status/(?P<job_id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'handle_async_status' ],
            'permission_callback' => '__return_true',
            'args'                => [ 'job_id' => [ 'required' => true ] ],
        ] );

        // ------------------------------------------------------------------
        // Bound function on single entity
        //   GET /odata/v4/{entity}({key})/{NS.Function}(...)
        // Must be registered before the plain nav-property route so the more
        // specific pattern (dot in function name) wins.
        // ------------------------------------------------------------------
        register_rest_route(
            $ns,
            '/(?P<entity>[a-zA-Z0-9_]+)\((?P<key>[^)]+)\)/(?P<function>[a-zA-Z0-9_.]+)\((?P<params>[^)]*)\)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle_function' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'entity'   => [ 'required' => true ],
                    'key'      => [ 'required' => true ],
                    'function' => [ 'required' => true ],
                ],
            ]
        );

        // ------------------------------------------------------------------
        // Bound action on single entity
        //   POST /odata/v4/{entity}({key})/{NS.Action}
        // ------------------------------------------------------------------
        register_rest_route(
            $ns,
            '/(?P<entity>[a-zA-Z0-9_]+)\((?P<key>[^)]+)\)/(?P<action>[a-zA-Z0-9_.]+)',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_action' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'entity' => [ 'required' => true ],
                    'key'    => [ 'required' => true ],
                    'action' => [ 'required' => true ],
                ],
            ]
        );

        // ------------------------------------------------------------------
        // Bound function on entity set
        //   GET /odata/v4/{entity}/{NS.Function}(...)
        // ------------------------------------------------------------------
        register_rest_route(
            $ns,
            '/(?P<entity>[a-zA-Z0-9_]+)/(?P<function>[a-zA-Z0-9_.]+)\((?P<params>[^)]*)\)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle_function' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'entity'   => [ 'required' => true ],
                    'function' => [ 'required' => true ],
                ],
            ]
        );

        // ------------------------------------------------------------------
        // Unbound function
        //   GET /odata/v4/{NS.Function}(...)
        // ------------------------------------------------------------------
        register_rest_route(
            $ns,
            '/(?P<function>[a-zA-Z0-9_.]+)\((?P<params>[^)]*)\)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle_function' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'function' => [ 'required' => true ],
                ],
            ]
        );

        // ------------------------------------------------------------------
        // Unbound action
        //   POST /odata/v4/{NS.Action}
        // The entity collection POST route already covers plain entity names;
        // qualified names (containing a dot) distinguish actions.
        // ------------------------------------------------------------------
        register_rest_route(
            $ns,
            '/(?P<action>[a-zA-Z0-9_.]+)',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_action' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'action' => [ 'required' => true ],
                ],
            ]
        );
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Check whether the request is allowed to proceed for the given entity set
     * and HTTP method.
     *
     * If the user is not authenticated, applies the `ODAD_allow_public_access`
     * filter. If the filter returns false (the default), returns a 403 response.
     * Returns null when the request may proceed.
     *
     * @param string $entity_set OData entity-set name.
     * @param string $method     HTTP method (GET, POST, PATCH, PUT, DELETE).
     * @return WP_REST_Response|null  Non-null means the caller should return this response immediately.
     */
    private function check_public_access( string $entity_set, string $method ): ?WP_REST_Response {
        if ( is_user_logged_in() ) {
            return null;
        }

        $allow_public = false;
        if ( null !== $this->bridge ) {
            $allow_public = (bool) $this->bridge->filter(
                'ODAD_allow_public_access',
                false,
                [ $entity_set, $method ]
            );
        }

        if ( ! $allow_public ) {
            return ODAD_Error::forbidden( 'Authentication required.' );
        }

        return null;
    }

    // =========================================================================
    // Route handlers
    // =========================================================================

    /**
     * GET /odata/v4/
     * Returns the OData service document listing all registered entity sets.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_service_document( WP_REST_Request $wp_request ): WP_REST_Response {
        $entity_sets = $this->metadata_builder->get_entity_set_names();
        $base_url    = rest_url( self::NAMESPACE . '/' );
        return ODAD_Response::service_document( $entity_sets, $base_url );
    }

    /**
     * GET /odata/v4/$metadata
     * Returns CSDL XML (default) or JSON CSDL when:
     *   - $format=application/json query param is present, or
     *   - Accept: application/json request header is set.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_metadata( WP_REST_Request $wp_request ): WP_REST_Response {
        $format = strtolower( (string) ( $wp_request->get_param( '$format' ) ?? '' ) );
        $accept = strtolower( (string) ( $wp_request->get_header( 'accept' ) ?? '' ) );

        $want_json = str_contains( $format, 'json' )
                  || str_contains( $accept, 'application/json' );

        if ( $want_json ) {
            $csdl_json = $this->metadata_builder->build_json();
            return ODAD_Response::metadata_json( $csdl_json );
        }

        $csdl_xml = $this->metadata_builder->build_xml();
        return ODAD_Response::metadata_xml( $csdl_xml );
    }

    /**
     * POST /odata/v4/$batch
     *
     * Delegates to ODAD_Batch_Handler when available.
     * Stores the raw WP_REST_Request in a global so the batch handler can
     * access headers and raw body via get_current_wp_request().
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_batch( WP_REST_Request $wp_request ): WP_REST_Response {
        if ( null === $this->batch_handler ) {
            return ODAD_Error::not_implemented( '$batch is not yet implemented.' );
        }

        // Expose the raw WP_REST_Request to the batch handler so it can inspect
        // Content-Type and raw body without needing an additional parameter.
        $GLOBALS['ODAD_current_batch_request'] = $wp_request;

        $path_params              = [];
        $path_params['_batch']    = true;
        $request                  = ODAD_Request::from_wp( $wp_request, $path_params );
        $user                     = wp_get_current_user();

        return $this->batch_handler->handle( $request, $user );
    }

    /**
     * GET /odata/v4/{entity}
     * Returns a paged OData collection response.
     *
     * Supports:
     *   - $deltatoken param → inject modified_after into query context (delta responses).
     *   - Prefer: respond-async header → queue via ODAD_Async_Handler and return 202.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_collection( WP_REST_Request $wp_request ): WP_REST_Response {
        $entity = $wp_request->get_param( 'entity' ) ?? '';
        $guard  = $this->check_public_access( $entity, 'GET' );
        if ( null !== $guard ) {
            return $guard;
        }

        if ( null === $this->query_engine ) {
            return ODAD_Error::not_implemented( 'Entity collection queries are not yet implemented.' );
        }

        $request = ODAD_Request::from_wp( $wp_request, $wp_request->get_url_params() );
        $user    = wp_get_current_user();

        // ── Prefer: respond-async ─────────────────────────────────────────────
        if ( null !== $this->async_handler && $this->is_async_request( $request ) ) {
            return $this->queue_async( $request, $user );
        }

        // ── Delta token ───────────────────────────────────────────────────────
        $delta_token_raw = $wp_request->get_param( '$deltatoken' );
        $modified_after  = null;

        if ( null !== $delta_token_raw && '' !== $delta_token_raw ) {
            $modified_after = ODAD_Delta_Token::decode( (string) $delta_token_raw );
            if ( null === $modified_after ) {
                return ODAD_Error::bad_request( 'InvalidDeltaToken', 'The supplied $deltatoken is invalid or has expired.' );
            }
        }

        try {
            $result = $this->query_engine->execute( $request, $user, $modified_after );
        } catch ( ODAD_Unknown_Entity_Exception $e ) {
            return ODAD_Error::not_found( $e->getMessage() );
        } catch ( \Exception $e ) {
            return ODAD_Error::bad_request( 'InvalidQuery', $e->getMessage() );
        }

        $context_url = rest_url( self::NAMESPACE . '/' . $request->entity_set );

        // When a delta token was supplied, include a new @odata.deltaLink in the response.
        if ( null !== $modified_after ) {
            $new_token  = ODAD_Delta_Token::now();
            $delta_link = rest_url( self::NAMESPACE . '/' . $request->entity_set . '?$deltatoken=' . $new_token );

            $body = [ '@odata.context' => $context_url ];
            if ( null !== $result->total_count ) {
                $body['@odata.count'] = $result->total_count;
            }
            $body['@odata.deltaLink'] = $delta_link;
            $body['value']            = $result->rows;

            $response = new WP_REST_Response( $body, 200 );
            $response->header( ODAD_Response::HEADER_ODATA_VER,    ODAD_Response::ODATA_VERSION );
            $response->header( ODAD_Response::HEADER_CONTENT_TYPE, ODAD_Response::CT_JSON_ODATA );
            return $response;
        }

        return ODAD_Response::collection(
            $result->rows,
            $context_url,
            $result->total_count,
            $result->next_link,
        );
    }

    /**
     * POST /odata/v4/{entity}
     * Creates a new entity. Returns 201 Created with the new entity body.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_create( WP_REST_Request $wp_request ): WP_REST_Response {
        $entity = $wp_request->get_param( 'entity' ) ?? '';
        $guard  = $this->check_public_access( $entity, 'POST' );
        if ( null !== $guard ) {
            return $guard;
        }

        if ( null === $this->write_handler ) {
            return ODAD_Error::not_implemented( 'Entity creation is not yet implemented.' );
        }

        $payload = $wp_request->get_json_params();
        if ( ! is_array( $payload ) ) {
            return ODAD_Error::bad_request( 'InvalidPayload', 'Request body must be a valid JSON object.' );
        }

        $user = wp_get_current_user();

        try {
            $created = $this->write_handler->insert( $entity, $payload, $user );
        } catch ( ODAD_Unknown_Entity_Exception $e ) {
            return ODAD_Error::not_found( $e->getMessage() );
        } catch ( ODAD_Field_ACL_Exception $e ) {
            return ODAD_Error::forbidden( $e->getMessage() );
        } catch ( \RuntimeException $e ) {
            return ODAD_Error::forbidden( $e->getMessage() );
        } catch ( \Exception $e ) {
            return ODAD_Error::bad_request( 'InsertFailed', $e->getMessage() );
        }

        // Derive the key value for the Location header from the created row.
        // 'ID' is the conventional key property; fall back to the first field when absent.
        $key        = $created['ID'] ?? $created[ array_key_first( $created ) ] ?? '';
        $entity_url = rest_url( self::NAMESPACE . '/' . $entity . '(' . rawurlencode( (string) $key ) . ')' );

        return ODAD_Response::created( $created, $entity_url );
    }

    /**
     * POST /odata/v4/{entity}/$query
     * Accepts system query options ($filter, $select, etc.) in the POST body
     * instead of URL query-string parameters — useful for long filter expressions.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_query_post( WP_REST_Request $wp_request ): WP_REST_Response {
        $entity = $wp_request->get_param( 'entity' ) ?? '';
        $guard  = $this->check_public_access( $entity, 'POST' );
        if ( null !== $guard ) {
            return $guard;
        }

        if ( null === $this->query_engine ) {
            return ODAD_Error::not_implemented( 'POST /$query is not yet implemented.' );
        }

        // Mark the request as a /$query POST so the engine merges body params.
        $path_params              = $wp_request->get_url_params();
        $path_params['_query']    = true;

        $request = ODAD_Request::from_wp( $wp_request, $path_params );
        $user    = wp_get_current_user();

        try {
            $result = $this->query_engine->execute( $request, $user );
        } catch ( ODAD_Unknown_Entity_Exception $e ) {
            return ODAD_Error::not_found( $e->getMessage() );
        } catch ( \Exception $e ) {
            return ODAD_Error::bad_request( 'InvalidQuery', $e->getMessage() );
        }

        $context_url = rest_url( self::NAMESPACE . '/' . $request->entity_set );

        return ODAD_Response::collection(
            $result->rows,
            $context_url,
            $result->total_count,
            $result->next_link,
        );
    }

    /**
     * GET /odata/v4/{entity}({key})
     * Returns a single entity by its key.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_read( WP_REST_Request $wp_request ): WP_REST_Response {
        $entity = $wp_request->get_param( 'entity' ) ?? '';
        $guard  = $this->check_public_access( $entity, 'GET' );
        if ( null !== $guard ) {
            return $guard;
        }

        if ( null === $this->query_engine ) {
            return ODAD_Error::not_implemented( 'Single entity reads are not yet implemented.' );
        }

        $request = ODAD_Request::from_wp( $wp_request, $wp_request->get_url_params() );
        $user    = wp_get_current_user();

        try {
            $row = $this->query_engine->get_entity( $request, $user );
        } catch ( ODAD_Unknown_Entity_Exception $e ) {
            return ODAD_Error::not_found( $e->getMessage() );
        } catch ( \Exception $e ) {
            return ODAD_Error::bad_request( 'InvalidQuery', $e->getMessage() );
        }

        if ( null === $row ) {
            return ODAD_Error::not_found();
        }

        $context_url = rest_url(
            self::NAMESPACE . '/' . $request->entity_set . '(' . rawurlencode( (string) $request->key ) . ')'
        );

        return ODAD_Response::entity( $row, $context_url );
    }

    /**
     * PATCH /odata/v4/{entity}({key})
     * Partially updates an entity (merge semantics). Returns 200 with the updated entity.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_update( WP_REST_Request $wp_request ): WP_REST_Response {
        $entity = $wp_request->get_param( 'entity' ) ?? '';
        $guard  = $this->check_public_access( $entity, 'PATCH' );
        if ( null !== $guard ) {
            return $guard;
        }

        if ( null === $this->write_handler ) {
            return ODAD_Error::not_implemented( 'Entity updates are not yet implemented.' );
        }

        $key     = $wp_request->get_param( 'key' ) ?? '';
        $payload = $wp_request->get_json_params();
        if ( ! is_array( $payload ) ) {
            return ODAD_Error::bad_request( 'InvalidPayload', 'Request body must be a valid JSON object.' );
        }

        $user = wp_get_current_user();

        try {
            $updated = $this->write_handler->update( $entity, $key, $payload, $user );
        } catch ( ODAD_Unknown_Entity_Exception $e ) {
            return ODAD_Error::not_found( $e->getMessage() );
        } catch ( ODAD_Field_ACL_Exception $e ) {
            return ODAD_Error::forbidden( $e->getMessage() );
        } catch ( \RuntimeException $e ) {
            return ODAD_Error::forbidden( $e->getMessage() );
        } catch ( \Exception $e ) {
            return ODAD_Error::bad_request( 'UpdateFailed', $e->getMessage() );
        }

        $context_url = rest_url( self::NAMESPACE . '/' . $entity . '(' . rawurlencode( (string) $key ) . ')' );

        return ODAD_Response::entity( $updated, $context_url );
    }

    /**
     * PUT /odata/v4/{entity}({key})
     * Full replacement of an entity (upsert-style). Delegates to update() since
     * full PUT semantics are equivalent to a complete PATCH for custom-table entities.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_replace( WP_REST_Request $wp_request ): WP_REST_Response {
        $entity = $wp_request->get_param( 'entity' ) ?? '';
        $guard  = $this->check_public_access( $entity, 'PUT' );
        if ( null !== $guard ) {
            return $guard;
        }

        if ( null === $this->write_handler ) {
            return ODAD_Error::not_implemented( 'Entity replacement is not yet implemented.' );
        }

        $key     = $wp_request->get_param( 'key' ) ?? '';
        $payload = $wp_request->get_json_params();
        if ( ! is_array( $payload ) ) {
            return ODAD_Error::bad_request( 'InvalidPayload', 'Request body must be a valid JSON object.' );
        }

        $user = wp_get_current_user();

        try {
            $updated = $this->write_handler->update( $entity, $key, $payload, $user );
        } catch ( ODAD_Unknown_Entity_Exception $e ) {
            return ODAD_Error::not_found( $e->getMessage() );
        } catch ( ODAD_Field_ACL_Exception $e ) {
            return ODAD_Error::forbidden( $e->getMessage() );
        } catch ( \RuntimeException $e ) {
            return ODAD_Error::forbidden( $e->getMessage() );
        } catch ( \Exception $e ) {
            return ODAD_Error::bad_request( 'ReplaceFailed', $e->getMessage() );
        }

        $context_url = rest_url( self::NAMESPACE . '/' . $entity . '(' . rawurlencode( (string) $key ) . ')' );

        return ODAD_Response::entity( $updated, $context_url );
    }

    /**
     * DELETE /odata/v4/{entity}({key})
     * Deletes an entity. Returns 204 No Content on success.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_delete( WP_REST_Request $wp_request ): WP_REST_Response {
        $entity = $wp_request->get_param( 'entity' ) ?? '';
        $guard  = $this->check_public_access( $entity, 'DELETE' );
        if ( null !== $guard ) {
            return $guard;
        }

        if ( null === $this->write_handler ) {
            return ODAD_Error::not_implemented( 'Entity deletion is not yet implemented.' );
        }

        $key  = $wp_request->get_param( 'key' ) ?? '';
        $user = wp_get_current_user();

        try {
            $this->write_handler->delete( $entity, $key, $user );
        } catch ( ODAD_Unknown_Entity_Exception $e ) {
            return ODAD_Error::not_found( $e->getMessage() );
        } catch ( ODAD_Field_ACL_Exception $e ) {
            return ODAD_Error::forbidden( $e->getMessage() );
        } catch ( \RuntimeException $e ) {
            return ODAD_Error::forbidden( $e->getMessage() );
        } catch ( \Exception $e ) {
            return ODAD_Error::bad_request( 'DeleteFailed', $e->getMessage() );
        }

        return ODAD_Response::no_content();
    }

    /**
     * GET /odata/v4/{entity}({key})/{nav}  [501 stub]
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_nav_property( WP_REST_Request $wp_request ): WP_REST_Response {
        $entity = $wp_request->get_param( 'entity' ) ?? '';
        $guard  = $this->check_public_access( $entity, 'GET' );
        if ( null !== $guard ) {
            return $guard;
        }

        return ODAD_Error::not_implemented( 'Navigation property traversal is not yet implemented.' );
    }

    /**
     * GET /odata/v4/{entity}/$count
     * Returns the total count of entities matching the given filter as plain text.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_count( WP_REST_Request $wp_request ): WP_REST_Response {
        $entity = $wp_request->get_param( 'entity' ) ?? '';
        $guard  = $this->check_public_access( $entity, 'GET' );
        if ( null !== $guard ) {
            return $guard;
        }

        if ( null === $this->query_engine ) {
            return ODAD_Error::not_implemented( 'Inline $count on collections is not yet implemented.' );
        }

        // Force $count=true and a zero $top so we only fetch the total, not rows.
        $path_params = $wp_request->get_url_params();
        $request     = ODAD_Request::from_wp( $wp_request, $path_params );
        $user        = wp_get_current_user();

        // Build a synthetic request with $count forced on and $top=0.
        $count_request = new ODAD_Request(
            entity_set:    $request->entity_set,
            method:        $request->method,
            key:           $request->key,
            nav_property:  $request->nav_property,
            filter:        $request->filter,
            select:        $request->select,
            expand:        null,
            orderby:       null,
            top:           0,
            skip:          0,
            count:         true,
            search:        $request->search,
            compute:       null,
            body:          [],
            format:        null,
            prefer:        null,
            is_batch:      false,
            is_query_post: false,
        );

        try {
            $result = $this->query_engine->execute( $count_request, $user );
        } catch ( ODAD_Unknown_Entity_Exception $e ) {
            return ODAD_Error::not_found( $e->getMessage() );
        } catch ( \Exception $e ) {
            return ODAD_Error::bad_request( 'InvalidQuery', $e->getMessage() );
        }

        $count = $result->total_count ?? 0;

        $response = new WP_REST_Response( (string) $count, 200 );
        $response->header( ODAD_Response::HEADER_ODATA_VER,    ODAD_Response::ODATA_VERSION );
        $response->header( ODAD_Response::HEADER_CONTENT_TYPE, 'text/plain' );
        return $response;
    }

    // =========================================================================
    // Phase 5.4 — Functions & Actions
    // =========================================================================

    /**
     * GET  /odata/v4/{NS.Function}(...)
     * GET  /odata/v4/{entity}/{NS.Function}(...)
     * GET  /odata/v4/{entity}({key})/{NS.Function}(...)
     *
     * Dispatches to the registered OData function handler.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_function( WP_REST_Request $wp_request ): WP_REST_Response {
        if ( null === $this->function_registry ) {
            return ODAD_Error::not_implemented( 'OData functions are not enabled.' );
        }

        $function_name = (string) ( $wp_request->get_param( 'function' ) ?? '' );

        if ( ! $this->function_registry->has( $function_name ) ) {
            return ODAD_Error::not_found( "Function '{$function_name}' is not registered." );
        }

        $fn_entry = $this->function_registry->get( $function_name );
        $raw_params = (string) ( $wp_request->get_param( 'params' ) ?? '' );
        $params   = $this->parse_function_params( $raw_params );
        $user     = wp_get_current_user();

        try {
            $result = ( $fn_entry['handler'] )( $params, $user );
        } catch ( \Throwable $e ) {
            return ODAD_Error::internal( $e->getMessage() );
        }

        $context_url = rest_url( self::NAMESPACE . '/' );
        return ODAD_Response::entity( [ 'value' => $result ], $context_url );
    }

    /**
     * POST /odata/v4/{NS.Action}
     * POST /odata/v4/{entity}({key})/{NS.Action}
     *
     * Dispatches to the registered OData action handler.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_action( WP_REST_Request $wp_request ): WP_REST_Response {
        if ( null === $this->action_registry ) {
            return ODAD_Error::not_implemented( 'OData actions are not enabled.' );
        }

        $action_name = (string) ( $wp_request->get_param( 'action' ) ?? '' );

        if ( ! $this->action_registry->has( $action_name ) ) {
            return ODAD_Error::not_found( "Action '{$action_name}' is not registered." );
        }

        $action_entry = $this->action_registry->get( $action_name );

        // Action parameters come from the JSON request body.
        $json_params = $wp_request->get_json_params();
        $params      = is_array( $json_params ) ? $json_params : [];
        $user        = wp_get_current_user();

        // Pass key when bound to a single entity.
        $key = $wp_request->get_param( 'key' );
        if ( null !== $key && '' !== $key ) {
            $params['_key'] = $key;
        }

        try {
            $result = ( $action_entry['handler'] )( $params, $user );
        } catch ( \Throwable $e ) {
            return ODAD_Error::internal( $e->getMessage() );
        }

        // Void actions return 204 No Content; non-void return 200 with value.
        if ( null === $result ) {
            return ODAD_Response::no_content();
        }

        $context_url = rest_url( self::NAMESPACE . '/' );
        return ODAD_Response::entity( [ 'value' => $result ], $context_url );
    }

    // =========================================================================
    // Phase 5.6 — Async status
    // =========================================================================

    /**
     * GET /odata/v4/$status/{job_id}
     *
     * Returns the status and result of a previously queued async job.
     * Returns 202 if still processing, 200 with result when complete,
     * or 404 for unknown/expired job IDs.
     *
     * @param WP_REST_Request $wp_request
     * @return WP_REST_Response
     */
    public function handle_async_status( WP_REST_Request $wp_request ): WP_REST_Response {
        if ( null === $this->async_handler ) {
            return ODAD_Error::not_implemented( 'Async processing is not enabled.' );
        }

        $job_id = (string) ( $wp_request->get_param( 'job_id' ) ?? '' );

        if ( '' === $job_id ) {
            return ODAD_Error::bad_request( 'MissingJobId', 'A job_id is required.' );
        }

        $status = $this->async_handler->get_status( $job_id );

        switch ( $status['status'] ) {
            case 'not_found':
                return ODAD_Error::not_found( "Job '{$job_id}' was not found or has expired." );

            case 'complete':
                $result      = $status['result'] ?? [];
                $context_url = rest_url( self::NAMESPACE . '/' );
                return ODAD_Response::collection(
                    $result['rows']        ?? [],
                    $context_url,
                    $result['total_count'] ?? null,
                    $result['next_link']   ?? null,
                );

            case 'error':
                return ODAD_Error::internal( $status['message'] ?? 'The job failed.' );

            case 'queued':
            case 'processing':
            default:
                $status_url = rest_url( self::NAMESPACE . '/\$status/' . $job_id );
                $response   = new WP_REST_Response(
                    [ '@odata.status' => $status['status'] ],
                    202
                );
                $response->header( ODAD_Response::HEADER_ODATA_VER,    ODAD_Response::ODATA_VERSION );
                $response->header( ODAD_Response::HEADER_CONTENT_TYPE, ODAD_Response::CT_JSON_ODATA );
                $response->header( 'Location', $status_url );
                return $response;
        }
    }

    // =========================================================================
    // Phase 5.6 — Async helpers
    // =========================================================================

    /**
     * Check whether the request carries "Prefer: respond-async".
     *
     * @param ODAD_Request $request
     * @return bool
     */
    private function is_async_request( ODAD_Request $request ): bool {
        if ( null === $request->prefer ) {
            return false;
        }
        return str_contains( strtolower( $request->prefer ), 'respond-async' );
    }

    /**
     * Queue the request and return a 202 Accepted response with a Location header.
     *
     * @param ODAD_Request $request
     * @param WP_User      $user
     * @return WP_REST_Response
     */
    private function queue_async( ODAD_Request $request, WP_User $user ): WP_REST_Response {
        $job_id     = $this->async_handler->queue( $request, $user );
        $status_url = rest_url( self::NAMESPACE . '/\$status/' . $job_id );

        $response = new WP_REST_Response( [ '@odata.status' => 'queued' ], 202 );
        $response->header( ODAD_Response::HEADER_ODATA_VER,    ODAD_Response::ODATA_VERSION );
        $response->header( ODAD_Response::HEADER_CONTENT_TYPE, ODAD_Response::CT_JSON_ODATA );
        $response->header( 'Location', $status_url );
        return $response;
    }

    // =========================================================================
    // Phase 5.4 — Function param parsing helper
    // =========================================================================

    /**
     * Parse OData function inline parameters from the URL segment.
     *
     * Input example: "status='published',maxResults=10"
     * Output example: [ 'status' => 'published', 'maxResults' => '10' ]
     *
     * Values are returned as strings; callers/handlers are responsible for
     * type-casting based on the function's parameter definition.
     *
     * @param string $raw Comma-separated key=value string from the URL.
     * @return array<string, string>
     */
    private function parse_function_params( string $raw ): array {
        if ( '' === trim( $raw ) ) {
            return [];
        }

        $params = [];

        foreach ( explode( ',', $raw ) as $pair ) {
            $pair = trim( $pair );
            if ( '' === $pair ) {
                continue;
            }

            $eq_pos = strpos( $pair, '=' );
            if ( false === $eq_pos ) {
                // Positional (no key): skip for now; named params are required.
                continue;
            }

            $key   = trim( substr( $pair, 0, $eq_pos ) );
            $value = trim( substr( $pair, $eq_pos + 1 ) );

            // Strip surrounding single-quotes from string literals.
            if ( str_starts_with( $value, "'" ) && str_ends_with( $value, "'" ) && strlen( $value ) >= 2 ) {
                $value = substr( $value, 1, -1 );
            }

            if ( '' !== $key ) {
                $params[ $key ] = $value;
            }
        }

        return $params;
    }
}
