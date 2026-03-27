<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles OData v4.01 set-based operations:
 *   PATCH /{Entity}/$filter(@x)/$each?@x={expr}   → bulk update
 *   DELETE /{Entity}/$filter(@x)/$each?@x={expr}  → bulk delete
 *
 * Compiles to a SINGLE SQL statement for atomicity.
 * Does NOT loop over individual entities or fire per-row write events.
 */
class ODAD_Set_Operations {

    public function __construct(
        private ODAD_Adapter_Resolver $adapter_resolver,
        private ODAD_Filter_Parser    $filter_parser,
        private ODAD_Filter_Compiler  $filter_compiler,
        private ODAD_Event_Bus        $event_bus,
    ) {}

    /**
     * Bulk-update all entities matching the filter.
     *
     * @param string   $entity_set
     * @param string   $filter_expression  OData $filter string.
     * @param array    $payload            Properties to update.
     * @param \WP_User $user
     * @return int     Number of affected rows.
     */
    public function patch_each(
        string   $entity_set,
        string   $filter_expression,
        array    $payload,
        \WP_User $user
    ): int {
        $ctx = new ODAD_Query_Context();
        $ctx->filter = $filter_expression;

        // Dispatch before event.
        $before = new ODAD_Event_Set_Operation_Before( $entity_set, 'patch', $user, $ctx, $payload );
        $this->event_bus->dispatch( $before );

        if ( $before->cancelled ) {
            return 0;
        }

        $ctx     = $before->filter_ctx;
        $payload = $before->payload;

        if ( empty( $payload ) ) {
            return 0;
        }

        $adapter    = $this->adapter_resolver->resolve( $entity_set );
        $column_map = $this->build_column_map( $adapter );
        $table      = $this->get_table_name( $entity_set );

        // Compile filter to WHERE clause.
        [ $where_sql, $where_params ] = $this->compile_filter( $ctx->filter, $column_map );

        // Build SET clause.
        [ $set_sql, $set_params ] = $this->build_set_clause( $payload, $column_map );

        if ( empty( $where_sql ) ) {
            return 0; // Safety: never update without a WHERE clause.
        }

        global $wpdb;
        $sql      = "UPDATE {$table} SET {$set_sql} WHERE {$where_sql}";
        $all_params = array_merge( $set_params, $where_params );
        $wpdb->query( $wpdb->prepare( $sql, ...$all_params ) );

        $affected = (int) $wpdb->rows_affected;

        // Dispatch after event.
        $after = new ODAD_Event_Set_Operation_After( $entity_set, 'patch', $user, $affected );
        $this->event_bus->dispatch( $after );

        return $affected;
    }

    /**
     * Bulk-delete all entities matching the filter.
     *
     * @param string   $entity_set
     * @param string   $filter_expression
     * @param \WP_User $user
     * @return int     Number of deleted rows.
     */
    public function delete_each(
        string   $entity_set,
        string   $filter_expression,
        \WP_User $user
    ): int {
        $ctx = new ODAD_Query_Context();
        $ctx->filter = $filter_expression;

        $before = new ODAD_Event_Set_Operation_Before( $entity_set, 'delete', $user, $ctx, [] );
        $this->event_bus->dispatch( $before );

        if ( $before->cancelled ) {
            return 0;
        }

        $ctx = $before->filter_ctx;

        $adapter    = $this->adapter_resolver->resolve( $entity_set );
        $column_map = $this->build_column_map( $adapter );
        $table      = $this->get_table_name( $entity_set );

        [ $where_sql, $where_params ] = $this->compile_filter( $ctx->filter, $column_map );

        if ( empty( $where_sql ) ) {
            return 0;
        }

        global $wpdb;
        $sql = "DELETE FROM {$table} WHERE {$where_sql}";
        $wpdb->query( $wpdb->prepare( $sql, ...$where_params ) );

        $affected = (int) $wpdb->rows_affected;

        $after = new ODAD_Event_Set_Operation_After( $entity_set, 'delete', $user, $affected );
        $this->event_bus->dispatch( $after );

        return $affected;
    }

    // -------------------------------------------------------------------------

    private function compile_filter( ?string $filter, array $column_map ): array {
        if ( empty( $filter ) ) {
            return [ '', [] ];
        }
        $ast = $this->filter_parser->parse( $filter );
        return $this->filter_compiler->compile( $ast, $column_map );
    }

    private function build_set_clause( array $payload, array $column_map ): array {
        $parts  = [];
        $params = [];
        foreach ( $payload as $property => $value ) {
            if ( ! isset( $column_map[ $property ] ) ) {
                continue;
            }
            $col      = $column_map[ $property ];
            $parts[]  = "{$col} = %s";
            $params[] = $value;
        }
        return [ implode( ', ', $parts ), $params ];
    }

    private function build_column_map( ODAD_Adapter $adapter ): array {
        $def = $adapter->get_entity_type_definition();
        $map = [];
        foreach ( $def['properties'] ?? [] as $prop => $info ) {
            $map[ $prop ] = $info['column'] ?? $prop;
        }
        return $map;
    }

    private function get_table_name( string $entity_set ): string {
        global $wpdb;
        $adapter = $this->adapter_resolver->resolve( $entity_set );
        $def     = $adapter->get_entity_type_definition();
        return $def['table'] ?? $wpdb->posts;
    }
}
