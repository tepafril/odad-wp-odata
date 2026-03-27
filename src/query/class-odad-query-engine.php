<?php
/**
 * ODAD_Query_Engine — orchestrates OData query execution end-to-end.
 *
 * Execution flow for execute():
 *   1. Resolve the adapter for the requested entity set.
 *   2. Build a ODAD_Query_Context from the request (parse filter, compile SQL,
 *      compile select/orderby/compute/search, set top/skip).
 *   3. Dispatch ODAD_Event_Query_Before — subscribers may inject row-level
 *      security conditions or modify the context via the ODAD_query_context filter.
 *   4. Fetch rows via adapter->get_collection($ctx).
 *   5. Optionally fetch total count via adapter->get_count($ctx).
 *   6. If $expand is requested, execute the expand plan (batched, no N+1).
 *   7. Dispatch ODAD_Event_Query_After — subscribers strip field ACL and fire
 *      the ODAD_query_results filter.
 *   8. Build @odata.nextLink when rows === $top (more pages may exist).
 *   9. Return ODAD_Query_Result.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Query_Engine {

    public function __construct(
        private ODAD_Filter_Parser    $filter_parser,
        private ODAD_Filter_Compiler  $filter_compiler,
        private ODAD_Select_Compiler  $select_compiler,
        private ODAD_Expand_Compiler  $expand_compiler,
        private ODAD_Compute_Compiler $compute_compiler,
        private ODAD_Orderby_Compiler $orderby_compiler,
        private ODAD_Search_Compiler  $search_compiler,
        private ODAD_Adapter_Resolver $adapter_resolver,
        private ODAD_Event_Bus        $event_bus,
    ) {}

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Execute an OData collection query end-to-end.
     *
     * @param ODAD_Request $request Parsed incoming OData request.
     * @param WP_User      $user    Current WordPress user.
     * @return ODAD_Query_Result
     * @throws ODAD_Unknown_Entity_Exception When the entity set has no registered adapter.
     */
    public function execute( ODAD_Request $request, WP_User $user ): ODAD_Query_Result {

        // ------------------------------------------------------------------
        // 1. Resolve adapter.
        // ------------------------------------------------------------------
        $adapter = $this->adapter_resolver->resolve( $request->entity_set );

        // ------------------------------------------------------------------
        // 2. Build query context from the request.
        // ------------------------------------------------------------------
        $ctx = $this->build_context( $request, $adapter );

        // ------------------------------------------------------------------
        // 3. Dispatch Query Before — row-level security + ODAD_query_context.
        // ------------------------------------------------------------------
        $before_event = new ODAD_Event_Query_Before(
            entity_set:    $request->entity_set,
            user:          $user,
            query_context: $ctx,
        );
        /** @var ODAD_Event_Query_Before $before_event */
        $before_event = $this->event_bus->dispatch( $before_event );
        $ctx          = $before_event->query_context;

        // ------------------------------------------------------------------
        // 4. Fetch rows.
        // ------------------------------------------------------------------
        $rows = $adapter->get_collection( $ctx );

        // ------------------------------------------------------------------
        // 5. Optionally fetch total count.
        // ------------------------------------------------------------------
        $total_count = null;
        if ( $ctx->count ) {
            $total_count = $adapter->get_count( $ctx );
        }

        // ------------------------------------------------------------------
        // 6. Execute $expand if requested.
        // ------------------------------------------------------------------
        if ( $ctx->expand !== null && ! empty( $rows ) ) {
            $entity_def      = $adapter->get_entity_type_definition();
            $nav_property_map = $entity_def['nav_properties'] ?? [];

            if ( ! empty( $nav_property_map ) ) {
                try {
                    $expand_plan = $this->expand_compiler->parse(
                        $ctx->expand,
                        $nav_property_map
                    );
                    $rows = $this->expand_compiler->execute(
                        $rows,
                        $expand_plan,
                        $request->entity_set
                    );
                } catch ( ODAD_Expand_Exception $e ) {
                    // Invalid expand expression — skip silently; the adapter
                    // already returned base rows which we can still serve.
                    unset( $e );
                }
            }
        }

        // ------------------------------------------------------------------
        // 7. Dispatch Query After — field ACL + ODAD_query_results filter.
        // ------------------------------------------------------------------
        $after_event = new ODAD_Event_Query_After(
            entity_set:    $request->entity_set,
            user:          $user,
            query_context: $ctx,
            results:       $rows,
        );
        /** @var ODAD_Event_Query_After $after_event */
        $after_event = $this->event_bus->dispatch( $after_event );
        $rows        = $after_event->results;

        // ------------------------------------------------------------------
        // 8. Build @odata.nextLink if current page is full (more may exist).
        // ------------------------------------------------------------------
        $next_link = null;
        if ( count( $rows ) === $ctx->top && $ctx->top > 0 ) {
            $next_link = $this->build_next_link( $request, $ctx );
        }

        // ------------------------------------------------------------------
        // 9. Return result.
        // ------------------------------------------------------------------
        return new ODAD_Query_Result(
            rows:        $rows,
            total_count: $total_count,
            next_link:   $next_link,
        );
    }

    /**
     * Fetch a single entity by key.
     *
     * Dispatches Query Before (context) and Query After (single-row results)
     * events so that row-level security and field ACL apply consistently.
     *
     * @param ODAD_Request $request Parsed OData request (must have a key set).
     * @param WP_User      $user    Current WordPress user.
     * @return array|null The entity row, or null if not found / access denied.
     * @throws ODAD_Unknown_Entity_Exception When the entity set has no registered adapter.
     */
    public function get_entity( ODAD_Request $request, WP_User $user ): ?array {

        $adapter = $this->adapter_resolver->resolve( $request->entity_set );

        $ctx = $this->build_context( $request, $adapter );

        // Dispatch Query Before for context modification (e.g. row-level security).
        $before_event = new ODAD_Event_Query_Before(
            entity_set:    $request->entity_set,
            user:          $user,
            query_context: $ctx,
        );
        /** @var ODAD_Event_Query_Before $before_event */
        $before_event = $this->event_bus->dispatch( $before_event );
        $ctx          = $before_event->query_context;

        $row = $adapter->get_entity( $request->key, $ctx );

        if ( null === $row ) {
            return null;
        }

        // Dispatch Query After so field ACL stripping applies to single entities too.
        $after_event = new ODAD_Event_Query_After(
            entity_set:    $request->entity_set,
            user:          $user,
            query_context: $ctx,
            results:       [ $row ],
        );
        /** @var ODAD_Event_Query_After $after_event */
        $after_event = $this->event_bus->dispatch( $after_event );
        $rows        = $after_event->results;

        return $rows[0] ?? null;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build a ODAD_Query_Context from a ODAD_Request.
     *
     * Runs all compilers (filter, select, orderby, compute, search) and stores
     * both the raw strings and the compiled SQL fragments on the context.
     * Compilation errors are caught and silently ignored — the adapter will
     * fall back to its default behaviour when a compiled field is absent.
     *
     * For $query POST requests the system query options are read from the
     * request body (already merged into $request by the router) rather than
     * URL parameters.
     *
     * @param ODAD_Request $request
     * @param ODAD_Adapter $adapter
     * @return ODAD_Query_Context
     */
    private function build_context( ODAD_Request $request, ODAD_Adapter $adapter ): ODAD_Query_Context {
        $ctx = new ODAD_Query_Context();

        // ---------------------------------------------------------------
        // For /$query POST, body params override URL params.
        // ---------------------------------------------------------------
        $filter  = $request->filter;
        $select  = $request->select;
        $orderby = $request->orderby;
        $expand  = $request->expand;
        $search  = $request->search;
        $compute = $request->compute;
        $top     = $request->top ?? ODAD_Request::DEFAULT_TOP;
        $skip    = $request->skip ?? 0;
        $count   = $request->count;

        if ( $request->is_query_post ) {
            $body    = $request->body;
            $filter  = isset( $body['$filter'] )  && '' !== $body['$filter']  ? (string) $body['$filter']  : $filter;
            $select  = isset( $body['$select'] )  && '' !== $body['$select']  ? (string) $body['$select']  : $select;
            $orderby = isset( $body['$orderby'] ) && '' !== $body['$orderby'] ? (string) $body['$orderby'] : $orderby;
            $expand  = isset( $body['$expand'] )  && '' !== $body['$expand']  ? (string) $body['$expand']  : $expand;
            $search  = isset( $body['$search'] )  && '' !== $body['$search']  ? (string) $body['$search']  : $search;
            $compute = isset( $body['$compute'] ) && '' !== $body['$compute'] ? (string) $body['$compute'] : $compute;

            if ( isset( $body['$top'] ) ) {
                $top_raw = (int) $body['$top'];
                $top     = min( max( $top_raw, 0 ), ODAD_Request::MAX_TOP );
            }
            if ( isset( $body['$skip'] ) ) {
                $skip = max( (int) $body['$skip'], 0 );
            }
            if ( isset( $body['$count'] ) ) {
                $count = in_array( strtolower( (string) $body['$count'] ), [ 'true', '1' ], true );
            }
        }

        // Enforce defaults and caps.
        if ( $top <= 0 ) {
            $top = ODAD_Request::DEFAULT_TOP;
        }
        $top  = min( $top, ODAD_Request::MAX_TOP );
        $skip = max( $skip, 0 );

        // Store raw strings.
        $ctx->top    = $top;
        $ctx->skip   = $skip;
        $ctx->count  = $count;
        $ctx->expand = $expand;

        // ---------------------------------------------------------------
        // Derive a column map from the adapter's entity type definition.
        // The adapter's public definition lists property names; we map
        // each property name to itself as a plain-column fallback so the
        // compilers have something to work with.  Adapters that need real
        // SQL column expressions will override context fields themselves
        // during execution.
        // ---------------------------------------------------------------
        $entity_def = $adapter->get_entity_type_definition();
        $properties = $entity_def['properties'] ?? [];
        $column_map = [];
        foreach ( $properties as $prop => $meta ) {
            // Resolve the DB column name from the property meta when available
            // (custom-table adapters define 'column'; built-in adapters omit it
            // and their get_collection() builds their own queries anyway).
            $db_col = is_array( $meta ) ? ( $meta['column'] ?? $prop ) : $prop;
            $safe   = preg_replace( '/[^a-zA-Z0-9_]/', '', $db_col );
            $column_map[ $prop ] = '`' . $safe . '`';
        }

        // ---------------------------------------------------------------
        // Compile $filter → filter_sql + filter_params.
        // ---------------------------------------------------------------
        if ( null !== $filter && '' !== $filter ) {
            $ctx->filter = $filter;
            try {
                $ast    = $this->filter_parser->parse( $filter );
                $result = $this->filter_compiler->compile( $ast, $column_map );
                $ctx->filter_sql    = $result['sql'];
                $ctx->filter_params = $result['params'];
            } catch ( \Exception $e ) {
                // Parse/compile error — leave filter_sql null; adapter uses raw $filter.
                unset( $e );
            }
        }

        // ---------------------------------------------------------------
        // Compile $select → array of property names.
        // ---------------------------------------------------------------
        if ( null !== $select && '' !== $select ) {
            $names = array_map( 'trim', explode( ',', $select ) );
            $names = array_filter( $names, static fn( $n ) => '' !== $n );
            $ctx->select = array_values( $names );
        }

        // ---------------------------------------------------------------
        // Compile $orderby.
        // ---------------------------------------------------------------
        if ( null !== $orderby && '' !== $orderby ) {
            $ctx->orderby = $this->parse_orderby( $orderby );
        }

        // ---------------------------------------------------------------
        // Compile $compute.
        // ---------------------------------------------------------------
        if ( null !== $compute && '' !== $compute ) {
            $ctx->compute = $compute;
        }

        // ---------------------------------------------------------------
        // Compile $search.
        // ---------------------------------------------------------------
        if ( null !== $search && '' !== $search ) {
            $ctx->search = $search;
        }

        return $ctx;
    }

    /**
     * Parse an OData $orderby string into the structured array format expected
     * by ODAD_Query_Context::$orderby.
     *
     * @param string $orderby Raw $orderby value, e.g. "PublishedDate desc,Title".
     * @return array<int, array{property: string, dir: string}>
     */
    private function parse_orderby( string $orderby ): array {
        $parts  = [];
        $tokens = array_filter(
            array_map( 'trim', explode( ',', $orderby ) ),
            static fn( $t ) => '' !== $t
        );

        foreach ( $tokens as $token ) {
            $segments = preg_split( '/\s+/', $token, 2 );
            $property = $segments[0] ?? '';
            $dir      = strtolower( $segments[1] ?? 'asc' );

            if ( '' === $property ) {
                continue;
            }

            $parts[] = [
                'property' => $property,
                'dir'      => in_array( $dir, [ 'asc', 'desc' ], true ) ? $dir : 'asc',
            ];
        }

        return $parts;
    }

    /**
     * Build the @odata.nextLink URL for the next page.
     *
     * Constructs the URL from the WP REST base URL and replaces/appends
     * the $skip parameter to advance by one page.
     *
     * @param ODAD_Request      $request
     * @param ODAD_Query_Context $ctx
     * @return string
     */
    private function build_next_link( ODAD_Request $request, ODAD_Query_Context $ctx ): string {
        $next_skip = $ctx->skip + $ctx->top;
        $base_url  = rest_url( ODAD_Router::NAMESPACE . '/' . $request->entity_set );

        // Collect query params to forward.
        $params = [];
        if ( null !== $request->filter ) {
            $params['$filter'] = $request->filter;
        }
        if ( null !== $request->select ) {
            $params['$select'] = $request->select;
        }
        if ( null !== $request->orderby ) {
            $params['$orderby'] = $request->orderby;
        }
        if ( null !== $request->expand ) {
            $params['$expand'] = $request->expand;
        }
        if ( null !== $request->search ) {
            $params['$search'] = $request->search;
        }
        if ( null !== $request->compute ) {
            $params['$compute'] = $request->compute;
        }
        if ( $request->count ) {
            $params['$count'] = 'true';
        }

        $params['$top']  = (string) $ctx->top;
        $params['$skip'] = (string) $next_skip;

        return add_query_arg(
            array_map( 'rawurlencode', $params ),
            $base_url
        );
    }
}
