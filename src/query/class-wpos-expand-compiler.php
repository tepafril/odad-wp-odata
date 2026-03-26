<?php
defined( 'ABSPATH' ) || exit;

/**
 * Compiles and executes OData $expand clauses.
 *
 * No direct WordPress calls are made here. Related entities are fetched
 * through WPOS_Adapter instances provided by WPOS_Adapter_Resolver.
 */
class WPOS_Expand_Compiler {

    public function __construct(
        private WPOS_Adapter_Resolver $adapter_resolver,
    ) {}

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Parse the raw $expand string into a structured expand plan.
     *
     * Each entry in the returned array has the shape:
     * [
     *   'nav_property'   => string,
     *   'entity_set'     => string,
     *   'is_collection'  => bool,
     *   'fk'             => string,          // foreign-key property on the base row
     *   'remote_fk'      => string|null,     // FK on the remote side (collections)
     *   'nested_select'  => string[]|null,
     *   'nested_filter'  => string|null,
     *   'nested_expand'  => string|null,
     *   'nested_top'     => int|null,
     *   'nested_skip'    => int|null,
     *   'nested_orderby' => string|null,
     * ]
     *
     * @param string $expand          Raw $expand string, e.g. "Author,Tags($select=Name)"
     * @param array  $nav_property_map Map from adapter's get_entity_type_definition()['nav_properties']
     * @return array Expand plan
     * @throws WPOS_Expand_Exception If a navigation property name is not found in $nav_property_map.
     */
    public function parse( string $expand, array $nav_property_map ): array {
        $segments = $this->split_top_level( $expand, ',' );
        $plan     = [];

        foreach ( $segments as $raw_segment ) {
            $raw_segment = trim( $raw_segment );
            if ( $raw_segment === '' ) {
                continue;
            }

            [ $nav_name, $inner_options ] = $this->extract_nav_and_options( $raw_segment );

            if ( ! isset( $nav_property_map[ $nav_name ] ) ) {
                throw new WPOS_Expand_Exception(
                    sprintf( 'Unknown navigation property in $expand: "%s"', $nav_name )
                );
            }

            $nav_def = $nav_property_map[ $nav_name ];

            $entry = [
                'nav_property'   => $nav_name,
                'entity_set'     => $nav_def['type'],
                'is_collection'  => (bool) ( $nav_def['collection'] ?? false ),
                'fk'             => $nav_def['fk'] ?? 'ID',
                'remote_fk'      => $nav_def['remote_fk'] ?? null,
                'nested_select'  => null,
                'nested_filter'  => null,
                'nested_expand'  => null,
                'nested_top'     => null,
                'nested_skip'    => null,
                'nested_orderby' => null,
            ];

            if ( $inner_options !== null ) {
                $this->apply_nested_options( $entry, $inner_options );
            }

            $plan[] = $entry;
        }

        return $plan;
    }

    /**
     * Execute the expand plan against an array of base-entity rows.
     *
     * Uses batched loading: one query per navigation property across all rows,
     * never N+1.
     *
     * @param array  $rows            Base entity rows (OData property names as keys).
     * @param array  $expand_plan     Output of parse().
     * @param string $base_entity_set Entity set name of the base rows (used for key lookup).
     * @return array $rows with navigation properties populated inline.
     */
    public function execute( array $rows, array $expand_plan, string $base_entity_set ): array {
        if ( empty( $rows ) || empty( $expand_plan ) ) {
            return $rows;
        }

        // Determine the key property of the base entity set.
        $base_key = $this->get_key_property( $base_entity_set );

        foreach ( $expand_plan as $entry ) {
            $nav_prop    = $entry['nav_property'];
            $entity_set  = $entry['entity_set'];
            $is_coll     = $entry['is_collection'];
            $fk          = $entry['fk'];
            $remote_fk   = $entry['remote_fk'];

            // Build the query context for the related adapter.
            $ctx = new WPOS_Query_Context();
            $ctx->top  = PHP_INT_MAX; // no artificial limit during expand
            $ctx->skip = 0;

            if ( $entry['nested_filter'] !== null ) {
                $ctx->filter = $entry['nested_filter'];
            }
            if ( $entry['nested_expand'] !== null ) {
                $ctx->expand = $entry['nested_expand'];
            }
            if ( $entry['nested_top'] !== null ) {
                $ctx->top = (int) $entry['nested_top'];
            }
            if ( $entry['nested_skip'] !== null ) {
                $ctx->skip = (int) $entry['nested_skip'];
            }
            if ( $entry['nested_orderby'] !== null ) {
                $ctx->orderby = $entry['nested_orderby'];
            }
            if ( $entry['nested_select'] !== null ) {
                $ctx->select = $entry['nested_select'];
            }

            if ( $is_coll ) {
                $rows = $this->execute_collection(
                    $rows, $entry, $entity_set, $nav_prop, $fk, $remote_fk, $base_key, $ctx
                );
            } else {
                $rows = $this->execute_single(
                    $rows, $entry, $entity_set, $nav_prop, $fk, $ctx
                );
            }
        }

        return $rows;
    }

    // ── Parsing helpers ──────────────────────────────────────────────────────

    /**
     * Split $text by $delimiter only at the top level (not inside parentheses).
     *
     * @return string[]
     */
    private function split_top_level( string $text, string $delimiter ): array {
        $parts  = [];
        $depth  = 0;
        $buffer = '';
        $len    = strlen( $text );

        for ( $i = 0; $i < $len; $i++ ) {
            $ch = $text[ $i ];

            if ( $ch === '(' ) {
                $depth++;
                $buffer .= $ch;
            } elseif ( $ch === ')' ) {
                $depth--;
                $buffer .= $ch;
            } elseif ( $ch === $delimiter && $depth === 0 ) {
                $parts[] = $buffer;
                $buffer  = '';
            } else {
                $buffer .= $ch;
            }
        }

        if ( $buffer !== '' ) {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /**
     * Given a segment like "Author($select=Name;$top=5)", return
     * [ 'Author', '$select=Name;$top=5' ].
     * Returns [ 'Author', null ] when no parentheses are present.
     *
     * @return array{0: string, 1: string|null}
     */
    private function extract_nav_and_options( string $segment ): array {
        $paren_pos = strpos( $segment, '(' );

        if ( $paren_pos === false ) {
            return [ trim( $segment ), null ];
        }

        $nav_name = trim( substr( $segment, 0, $paren_pos ) );

        // Strip outermost parentheses.
        $inner = substr( $segment, $paren_pos + 1 );
        if ( str_ends_with( $inner, ')' ) ) {
            $inner = substr( $inner, 0, -1 );
        }

        return [ $nav_name, $inner ];
    }

    /**
     * Parse semicolon-separated nested options (e.g. "$select=Name;$top=5")
     * and apply them to the plan entry in-place.
     *
     * @param array  $entry        Plan entry to mutate.
     * @param string $inner_options Semicolon-delimited option string.
     */
    private function apply_nested_options( array &$entry, string $inner_options ): void {
        // Nested options use ';' as separator.
        $options = $this->split_top_level( $inner_options, ';' );

        foreach ( $options as $option ) {
            $option = trim( $option );
            if ( $option === '' ) {
                continue;
            }

            // Each option is "$key=value".
            $eq_pos = strpos( $option, '=' );
            if ( $eq_pos === false ) {
                continue;
            }

            $key   = trim( substr( $option, 0, $eq_pos ) );
            $value = trim( substr( $option, $eq_pos + 1 ) );

            switch ( strtolower( $key ) ) {
                case '$select':
                    $entry['nested_select'] = array_map( 'trim', explode( ',', $value ) );
                    break;

                case '$filter':
                    $entry['nested_filter'] = $value;
                    break;

                case '$expand':
                    $entry['nested_expand'] = $value;
                    break;

                case '$top':
                    $entry['nested_top'] = (int) $value;
                    break;

                case '$skip':
                    $entry['nested_skip'] = (int) $value;
                    break;

                case '$orderby':
                    $entry['nested_orderby'] = $value;
                    break;
            }
        }
    }

    // ── Execution helpers ────────────────────────────────────────────────────

    /**
     * Batch-load related entities for a single-entity nav property and attach
     * them to the corresponding rows.
     *
     * Strategy:
     *   1. Collect all unique FK values from base rows (e.g. AuthorID).
     *   2. Load all matching related entities in one query via extra_conditions.
     *   3. Index related entities by their key property.
     *   4. Assign to each base row.
     *
     * @return array Updated rows.
     */
    private function execute_single(
        array $rows,
        array $entry,
        string $entity_set,
        string $nav_prop,
        string $fk,
        WPOS_Query_Context $ctx
    ): array {
        // 1. Collect unique FK values (e.g. all AuthorID values from posts).
        $fk_values = [];
        foreach ( $rows as $row ) {
            if ( isset( $row[ $fk ] ) && $row[ $fk ] !== null ) {
                $fk_values[] = $row[ $fk ];
            }
        }
        $fk_values = array_values( array_unique( $fk_values ) );

        if ( empty( $fk_values ) ) {
            // Nothing to fetch; set null on every row.
            foreach ( $rows as &$row ) {
                $row[ $nav_prop ] = null;
            }
            unset( $row );
            return $rows;
        }

        // 2. Resolve the related entity adapter.
        $adapter    = $this->adapter_resolver->resolve( $entity_set );
        $remote_key = $this->get_key_property( $entity_set );

        // 3. Load all matching entities in one batched query.
        //    We ask the adapter for a collection filtered to the FK values.
        $batch_ctx                    = clone $ctx;
        $batch_ctx->extra_conditions  = array_merge(
            $ctx->extra_conditions,
            [ [ 'key_in' => $fk_values, 'key_property' => $remote_key ] ]
        );
        $batch_ctx->top               = PHP_INT_MAX;
        $batch_ctx->skip              = 0;

        $related_rows = $adapter->get_collection( $batch_ctx );

        // 4. Index by the related entity's key property.
        $indexed = [];
        foreach ( $related_rows as $related ) {
            if ( isset( $related[ $remote_key ] ) ) {
                $indexed[ $related[ $remote_key ] ] = $related;
            }
        }

        // 5. Attach to each base row.
        foreach ( $rows as &$row ) {
            $fk_val          = $row[ $fk ] ?? null;
            $row[ $nav_prop ] = ( $fk_val !== null && isset( $indexed[ $fk_val ] ) )
                ? $indexed[ $fk_val ]
                : null;
        }
        unset( $row );

        return $rows;
    }

    /**
     * Batch-load related entities for a collection nav property and attach
     * them to the corresponding rows.
     *
     * Strategy:
     *   1. Collect all unique base-row IDs.
     *   2. Load all related entities whose remote FK is in that set — one query.
     *   3. Group the related entities by parent ID.
     *   4. Assign to each base row.
     *
     * @return array Updated rows.
     */
    private function execute_collection(
        array $rows,
        array $entry,
        string $entity_set,
        string $nav_prop,
        string $fk,
        ?string $remote_fk,
        string $base_key,
        WPOS_Query_Context $ctx
    ): array {
        // 1. Collect all unique base-row IDs.
        $parent_ids = [];
        foreach ( $rows as $row ) {
            if ( isset( $row[ $fk ] ) && $row[ $fk ] !== null ) {
                $parent_ids[] = $row[ $fk ];
            }
        }
        $parent_ids = array_values( array_unique( $parent_ids ) );

        if ( empty( $parent_ids ) ) {
            foreach ( $rows as &$row ) {
                $row[ $nav_prop ] = [];
            }
            unset( $row );
            return $rows;
        }

        // Determine which property on the remote side points back to the parent.
        // Fall back to the base entity key property if remote_fk is not specified.
        $parent_ref_property = $remote_fk ?? $base_key;

        // 2. Resolve adapter and batch-load.
        $adapter   = $this->adapter_resolver->resolve( $entity_set );
        $batch_ctx = clone $ctx;
        $batch_ctx->extra_conditions = array_merge(
            $ctx->extra_conditions,
            [ [ 'parent_ids' => $parent_ids, 'parent_ref_property' => $parent_ref_property ] ]
        );
        $batch_ctx->top  = PHP_INT_MAX;
        $batch_ctx->skip = 0;

        $related_rows = $adapter->get_collection( $batch_ctx );

        // 3. Group by parent ID.
        $grouped = [];
        foreach ( $related_rows as $related ) {
            $pid = $related[ $parent_ref_property ] ?? null;
            if ( $pid !== null ) {
                $grouped[ $pid ][] = $related;
            }
        }

        // 4. Assign arrays to each base row.
        foreach ( $rows as &$row ) {
            $pid             = $row[ $fk ] ?? null;
            $row[ $nav_prop ] = ( $pid !== null && isset( $grouped[ $pid ] ) )
                ? $grouped[ $pid ]
                : [];
        }
        unset( $row );

        return $rows;
    }

    /**
     * Return the key property name for a given entity set by querying its adapter.
     */
    private function get_key_property( string $entity_set ): string {
        $adapter    = $this->adapter_resolver->resolve( $entity_set );
        $definition = $adapter->get_entity_type_definition();
        return $definition['key_property'] ?? 'ID';
    }
}
