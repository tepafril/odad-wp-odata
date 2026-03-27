<?php
defined( 'ABSPATH' ) || exit;

class ODAD_Adapter_Custom_Table implements ODAD_Adapter {

    /**
     * @param string      $table_name      Table name without $wpdb prefix (e.g. 'employees').
     * @param string      $entity_set_name OData entity set name (e.g. 'Employees').
     * @param string      $key_column      Primary key column name (default 'id').
     * @param array|null  $schema          Optional manual schema. If null, auto-detected via DESCRIBE.
     */
    public function __construct(
        private string $table_name,
        private string $entity_set_name,
        private string $key_column = 'id',
        private ?array $schema     = null,
    ) {}

    // ── Schema ────────────────────────────────────────────────────────────

    public function get_entity_set_name(): string {
        return $this->entity_set_name;
    }

    public function get_entity_type_definition(): array {
        $schema = $this->schema ?? $this->detect_schema();

        return [
            'entity_type'    => $this->entity_set_name . 'EntityType',
            'key_property'   => $this->key_column,
            'properties'     => $schema,
            'nav_properties' => [],
            'adapter_class'  => static::class,
        ];
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

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is sanitised; values are parameterised.
        $sql = $wpdb->prepare(
            'SELECT * FROM `' . $safe_table . '` LIMIT %d OFFSET %d',
            $ctx->top,
            $ctx->skip,
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        return is_array( $rows ) ? $rows : [];
    }

    public function get_entity( mixed $key, ODAD_Query_Context $ctx ): ?array {
        global $wpdb;

        $full_table  = $wpdb->prefix . $this->table_name;
        $safe_table  = preg_replace( '/[^a-zA-Z0-9_]/', '', $full_table );
        $safe_column = preg_replace( '/[^a-zA-Z0-9_]/', '', $this->key_column );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers sanitised; value parameterised.
        $sql = $wpdb->prepare(
            'SELECT * FROM `' . $safe_table . '` WHERE `' . $safe_column . '` = %s LIMIT 1',
            $key,
        );

        $row = $wpdb->get_row( $sql, ARRAY_A );

        return is_array( $row ) ? $row : null;
    }

    public function get_count( ODAD_Query_Context $ctx ): int {
        global $wpdb;

        $full_table = $wpdb->prefix . $this->table_name;
        $safe_table = preg_replace( '/[^a-zA-Z0-9_]/', '', $full_table );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is sanitised.
        $count = $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $safe_table . '`' );

        return (int) $count;
    }

    // ── Writes ────────────────────────────────────────────────────────────

    /**
     * Insert a new row using $wpdb->insert() — never raw interpolated SQL.
     *
     * @param array<string,mixed> $data  Column → value map.
     * @return mixed  Inserted row's primary key value, or null on failure.
     */
    public function insert( array $data ): mixed {
        global $wpdb;

        $full_table = $wpdb->prefix . $this->table_name;

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
     * @param array<string,mixed> $data  Column → value map of fields to update.
     * @return bool
     */
    public function update( mixed $key, array $data ): bool {
        global $wpdb;

        $full_table = $wpdb->prefix . $this->table_name;

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
