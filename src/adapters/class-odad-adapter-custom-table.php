<?php
defined( 'ABSPATH' ) || exit;

class ODAD_Adapter_Custom_Table implements ODAD_Adapter {

    /** @var array|null Cached result of get_entity_type_definition(). */
    private ?array $definition_cache = null;

    /**
     * @param string      $table_name      Table name without $wpdb prefix (e.g. 'employees').
     * @param string      $entity_set_name OData entity set name (e.g. 'Employees').
     * @param string      $key_column      Primary key column name (default 'id').
     * @param array|null  $schema          Optional manual schema. If null, auto-detected via DESCRIBE.
     * @param array       $nav_properties  Navigation property definitions for $expand support.
     */
    public function __construct(
        private string $table_name,
        private string $entity_set_name,
        private string $key_column      = 'id',
        private ?array $schema          = null,
        private array  $nav_properties  = [],
    ) {}

    // ── Schema ────────────────────────────────────────────────────────────

    public function get_entity_set_name(): string {
        return $this->entity_set_name;
    }

    public function get_entity_type_definition(): array {
        if ( $this->definition_cache !== null ) {
            return $this->definition_cache;
        }

        // When a manual schema is supplied it is wrapped as ['key' => ..., 'properties' => [...]].
        // Unwrap so 'properties' in the definition is the flat property-name → meta map that
        // Field ACL, metadata builder, and compilers expect.
        if ( null !== $this->schema && isset( $this->schema['properties'] ) ) {
            $properties   = $this->schema['properties'];
            $key_property = $this->schema['key'] ?? $this->key_column;
        } else {
            $properties   = $this->schema ?? $this->detect_schema();
            $key_property = $this->key_column;
        }

        $this->definition_cache = [
            'entity_type'    => $this->entity_set_name . 'EntityType',
            'key_property'   => $key_property,
            'properties'     => $properties,
            'nav_properties' => $this->nav_properties,
            'adapter_class'  => static::class,
        ];

        return $this->definition_cache;
    }

    /**
     * Auto-detect schema by running DESCRIBE on the table.
     * Maps MySQL column types to Edm types.
     *
     * @return array<string, array{type: string, nullable: bool}>
     */
    private function detect_schema(): array {
        global $wpdb;

        $full_table = $wpdb->prefix . $this->table_name;

        // $wpdb->prepare() does not support %i (identifier) on older WPDB versions,
        // so we use get_col_info after a zero-row query to avoid raw interpolation.
        // However, DESCRIBE requires a table name identifier which cannot be a string
        // placeholder. We sanitise the name strictly and rely on wpdb->esc_like is
        // not applicable here; instead we whitelist characters.
        $safe_table = preg_replace( '/[^a-zA-Z0-9_]/', '', $full_table );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is sanitised above.
        $columns = $wpdb->get_results( 'DESCRIBE `' . $safe_table . '`', ARRAY_A );

        if ( ! is_array( $columns ) || empty( $columns ) ) {
            return [];
        }

        $schema = [];
        foreach ( $columns as $col ) {
            $col_name    = $col['Field'];
            $col_type    = strtolower( $col['Type'] );
            $is_nullable = ( strtolower( $col['Null'] ) === 'yes' );

            $schema[ $col_name ] = [
                'type'     => $this->mysql_type_to_edm( $col_type ),
                'nullable' => $is_nullable,
            ];
        }

        return $schema;
    }

    /**
     * Map a MySQL column type string to an OData Edm type.
     *
     * @param string $mysql_type  e.g. 'int(11)', 'varchar(255)', 'tinyint(1)'
     * @return string
     */
    private function mysql_type_to_edm( string $mysql_type ): string {
        // tinyint(1) is conventionally used as a boolean in MySQL/WordPress.
        if ( preg_match( '/^tinyint\s*\(\s*1\s*\)/', $mysql_type ) ) {
            return 'Edm.Boolean';
        }

        // Strip size specifiers for the base-type switch.
        $base = preg_replace( '/\s*\(.*\)/', '', $mysql_type );
        $base = trim( $base );

        return match ( true ) {
            in_array( $base, [ 'bigint', 'int8' ], true )                         => 'Edm.Int64',
            in_array( $base, [ 'int', 'integer', 'mediumint', 'smallint', 'tinyint' ], true ) => 'Edm.Int32',
            in_array( $base, [ 'float', 'real', 'double', 'double precision' ], true ) => 'Edm.Double',
            in_array( $base, [ 'decimal', 'numeric', 'dec' ], true )               => 'Edm.Decimal',
            in_array( $base, [ 'datetime', 'timestamp' ], true )                   => 'Edm.DateTimeOffset',
            $base === 'date'                                                        => 'Edm.Date',
            $base === 'time'                                                        => 'Edm.TimeOfDay',
            in_array( $base, [ 'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set' ], true ) => 'Edm.String',
            in_array( $base, [ 'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob' ], true ) => 'Edm.Binary',
            default                                                                 => 'Edm.String',
        };
    }

    // ── Reads ─────────────────────────────────────────────────────────────

    public function get_collection( ODAD_Query_Context $ctx ): array {
        global $wpdb;

        $full_table = $wpdb->prefix . $this->table_name;
        $safe_table = preg_replace( '/[^a-zA-Z0-9_]/', '', $full_table );
        $col_map    = $this->get_odata_to_column_map();

        $where_parts = [];
        $params      = [];

        // Apply compiled $filter (filter_sql uses %s/%d/%f placeholders).
        if ( null !== $ctx->filter_sql && '' !== $ctx->filter_sql ) {
            $where_parts[] = '(' . $ctx->filter_sql . ')';
            array_push( $params, ...$ctx->filter_params );
        }

        // Apply extra_conditions (injected by the expand compiler for batched FK
        // loading — never from user input, so no SQL injection risk beyond the adapter).
        foreach ( $ctx->extra_conditions as $condition ) {
            if ( isset( $condition['key_in'] ) && ! empty( $condition['key_in'] ) ) {
                // Single-entity expand: PK IN (values).
                $odata_col  = $condition['key_property'] ?? $this->key_column;
                $db_col     = $col_map[ $odata_col ] ?? $odata_col;
                $safe_col   = preg_replace( '/[^a-zA-Z0-9_]/', '', $db_col );
                $ids        = array_values( $condition['key_in'] );
                $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );
                $where_parts[] = '`' . $safe_col . '` IN (' . $placeholders . ')';
                array_push( $params, ...$ids );

            } elseif ( isset( $condition['parent_ids'] ) && ! empty( $condition['parent_ids'] ) ) {
                // Collection expand: FK IN (parent IDs).
                $odata_col  = $condition['parent_ref_property'] ?? $this->key_column;
                $db_col     = $col_map[ $odata_col ] ?? $odata_col;
                $safe_col   = preg_replace( '/[^a-zA-Z0-9_]/', '', $db_col );
                $ids        = array_values( $condition['parent_ids'] );
                $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );
                $where_parts[] = '`' . $safe_col . '` IN (' . $placeholders . ')';
                array_push( $params, ...$ids );
            }
        }

        $where_sql   = empty( $where_parts ) ? '' : ' WHERE ' . implode( ' AND ', $where_parts );
        $select_sql  = $this->build_select_sql( $ctx->select, $col_map );
        $orderby_sql = $this->build_orderby_sql( $ctx->orderby, $col_map );

        array_push( $params, $ctx->top, $ctx->skip );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/column identifiers sanitised; values parameterised.
        $sql  = $wpdb->prepare(
            'SELECT ' . $select_sql . ' FROM `' . $safe_table . '`' . $where_sql . $orderby_sql . ' LIMIT %d OFFSET %d',
            ...$params,
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        return array_map( [ $this, 'map_row_to_odata' ], is_array( $rows ) ? $rows : [] );
    }

    public function get_entity( mixed $key, ODAD_Query_Context $ctx ): ?array {
        global $wpdb;

        $full_table  = $wpdb->prefix . $this->table_name;
        $safe_table  = preg_replace( '/[^a-zA-Z0-9_]/', '', $full_table );
        $safe_column = preg_replace( '/[^a-zA-Z0-9_]/', '', $this->key_column );
        $col_map     = $this->get_odata_to_column_map();
        $select_sql  = $this->build_select_sql( $ctx->select, $col_map );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers sanitised; value parameterised.
        $sql = $wpdb->prepare(
            'SELECT ' . $select_sql . ' FROM `' . $safe_table . '` WHERE `' . $safe_column . '` = %s LIMIT 1',
            $key,
        );

        $row = $wpdb->get_row( $sql, ARRAY_A );

        return is_array( $row ) ? $this->map_row_to_odata( $row ) : null;
    }

    public function get_count( ODAD_Query_Context $ctx ): int {
        global $wpdb;

        $full_table = $wpdb->prefix . $this->table_name;
        $safe_table = preg_replace( '/[^a-zA-Z0-9_]/', '', $full_table );

        if ( null !== $ctx->filter_sql && '' !== $ctx->filter_sql ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table sanitised; filter parameterised.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM `' . $safe_table . '` WHERE (' . $ctx->filter_sql . ')',
                    ...$ctx->filter_params,
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is sanitised.
            $count = $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $safe_table . '`' );
        }

        return (int) $count;
    }

    // ── Row mapping helpers ───────────────────────────────────────────────

    /**
     * Build the SQL SELECT column list for a query.
     *
     * - When $select is null/empty → returns '*' (all columns).
     * - When $select is provided  → resolves each OData property name to its DB
     *   column via $col_map and emits a safe backtick-quoted list.
     * - The key column is always prepended so the primary key is never omitted.
     * - Unknown property names (not in $col_map) are silently skipped.
     * - For auto-detected schemas $col_map is empty, so the OData property name
     *   is used directly as the DB column name.
     *
     * @param string[]|null        $select  OData property names from $ctx->select.
     * @param array<string,string> $col_map OData name → DB column name.
     * @return string SQL column list, e.g. "`id`, `full_name`", or "*".
     */
    private function build_select_sql( ?array $select, array $col_map ): string {
        if ( empty( $select ) ) {
            return '*';
        }

        $def      = $this->get_entity_type_definition();
        $key_prop = $def['key_property'] ?? null;

        // Ensure the key property is always included.
        $odata_names = $select;
        if ( $key_prop !== null && ! in_array( $key_prop, $odata_names, true ) ) {
            array_unshift( $odata_names, $key_prop );
        }

        $cols = [];
        foreach ( $odata_names as $odata_name ) {
            if ( ! empty( $col_map ) ) {
                // Manual schema: look up the DB column name.
                if ( ! isset( $col_map[ $odata_name ] ) ) {
                    continue; // unknown OData property — skip
                }
                $db_col = $col_map[ $odata_name ];
            } else {
                // Auto-detected schema: OData name IS the DB column name.
                $db_col = $odata_name;
            }
            $cols[] = '`' . preg_replace( '/[^a-zA-Z0-9_]/', '', $db_col ) . '`';
        }

        return empty( $cols ) ? '*' : implode( ', ', array_unique( $cols ) );
    }

    /**
     * Build the SQL ORDER BY clause from $ctx->orderby.
     *
     * @param array|null           $orderby Parsed orderby: [['property'=>'FullName','dir'=>'asc'], ...]
     * @param array<string,string> $col_map OData name → DB column name.
     * @return string e.g. " ORDER BY `full_name` ASC, `hired_at` DESC", or "".
     */
    private function build_orderby_sql( ?array $orderby, array $col_map ): string {
        if ( empty( $orderby ) ) {
            return '';
        }

        $parts = [];
        foreach ( $orderby as $clause ) {
            $odata_name = $clause['property'] ?? '';
            $dir        = strtoupper( $clause['dir'] ?? 'ASC' );
            $dir        = in_array( $dir, [ 'ASC', 'DESC' ], true ) ? $dir : 'ASC';

            if ( $odata_name === '' ) {
                continue;
            }

            if ( ! empty( $col_map ) ) {
                if ( ! isset( $col_map[ $odata_name ] ) ) {
                    continue; // unknown property — skip
                }
                $db_col = $col_map[ $odata_name ];
            } else {
                $db_col = $odata_name; // auto-detected schema
            }

            $parts[] = '`' . preg_replace( '/[^a-zA-Z0-9_]/', '', $db_col ) . '` ' . $dir;
        }

        return empty( $parts ) ? '' : ' ORDER BY ' . implode( ', ', $parts );
    }

    /**
     * Convert a raw DB row (column names as keys) to OData property names.
     *
     * For manually specified schemas the schema defines the column→property mapping
     * via the 'column' key in each property meta.
     * For auto-detected schemas the column name IS the property name, so the row
     * is returned as-is.
     *
     * @param array $row Raw associative DB row.
     * @return array Row keyed by OData property names.
     */
    private function map_row_to_odata( array $row ): array {
        if ( null === $this->schema || ! isset( $this->schema['properties'] ) ) {
            return $row;
        }

        $mapped = [];
        foreach ( $this->schema['properties'] as $odata_name => $meta ) {
            $col = $meta['column'] ?? $odata_name;
            if ( array_key_exists( $col, $row ) ) {
                $mapped[ $odata_name ] = $row[ $col ];
            }
        }
        return $mapped;
    }

    /**
     * Build a map from OData property name → DB column name.
     *
     * Used to resolve property names in extra_conditions back to DB column identifiers.
     * Returns an empty array for auto-detected schemas (column name = property name).
     *
     * @return array<string,string>
     */
    private function get_odata_to_column_map(): array {
        if ( null === $this->schema || ! isset( $this->schema['properties'] ) ) {
            return [];
        }

        $map = [];
        foreach ( $this->schema['properties'] as $odata_name => $meta ) {
            $map[ $odata_name ] = $meta['column'] ?? $odata_name;
        }
        return $map;
    }

    // ── Writes ────────────────────────────────────────────────────────────

    /**
     * Convert an OData property-name payload to a DB column-name payload.
     *
     * Used by insert() and update() so callers can pass OData property names
     * (e.g. 'FullName') and the adapter transparently maps them to DB column
     * names (e.g. 'full_name') before writing.
     *
     * Unknown OData property names that have no 'column' mapping are passed
     * through unchanged so auto-detected schemas (column name = property name)
     * continue to work without any mapping.
     *
     * @param array<string,mixed> $data OData property name → value map.
     * @return array<string,mixed> DB column name → value map.
     */
    private function map_odata_payload_to_db( array $data ): array {
        $col_map = $this->get_odata_to_column_map();
        if ( empty( $col_map ) ) {
            return $data; // auto-detected schema: names are already DB columns
        }

        $mapped = [];
        foreach ( $data as $odata_name => $value ) {
            $db_col           = $col_map[ $odata_name ] ?? $odata_name;
            $mapped[ $db_col ] = $value;
        }
        return $mapped;
    }

    /**
     * Insert a new row using $wpdb->insert() — never raw interpolated SQL.
     *
     * @param array<string,mixed> $data  OData property name → value map.
     * @return mixed  Inserted row's primary key value, or null on failure.
     */
    public function insert( array $data ): mixed {
        global $wpdb;

        $full_table = $wpdb->prefix . $this->table_name;
        $data       = $this->map_odata_payload_to_db( $data );

        $result = $wpdb->insert( $full_table, $data );

        if ( $result === false ) {
            throw new RuntimeException(
                'ODAD_Adapter_Custom_Table::insert() failed for table "' .
                esc_html( $full_table ) . '": ' . $wpdb->last_error
            );
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a row using $wpdb->update() — never raw interpolated SQL.
     *
     * @param mixed               $key   Primary key value.
     * @param array<string,mixed> $data  OData property name → value map of fields to update.
     * @return bool
     */
    public function update( mixed $key, array $data ): bool {
        global $wpdb;

        $full_table = $wpdb->prefix . $this->table_name;
        $data       = $this->map_odata_payload_to_db( $data );

        $result = $wpdb->update(
            $full_table,
            $data,
            [ $this->key_column => $key ],
        );

        if ( $result === false ) {
            throw new RuntimeException(
                'ODAD_Adapter_Custom_Table::update() failed for table "' .
                esc_html( $full_table ) . '": ' . $wpdb->last_error
            );
        }

        return $result !== 0;
    }

    /**
     * Delete a row using $wpdb->delete() — never raw interpolated SQL.
     *
     * @param mixed $key  Primary key value.
     * @return bool
     */
    public function delete( mixed $key ): bool {
        global $wpdb;

        $full_table = $wpdb->prefix . $this->table_name;

        $result = $wpdb->delete(
            $full_table,
            [ $this->key_column => $key ],
        );

        if ( $result === false ) {
            return false;
        }

        return $result !== 0;
    }
}
