<?php
defined( 'ABSPATH' ) || exit;

class WPOS_Adapter_WP_Users implements WPOS_Adapter {

    // ── Schema ────────────────────────────────────────────────────────────

    public function get_entity_set_name(): string {
        return 'Users';
    }

    public function get_entity_type_definition(): array {
        return [
            'entity_type'    => 'UserEntityType',
            'key_property'   => 'ID',
            'properties'     => [
                'ID'             => [ 'type' => 'Edm.Int32',          'nullable' => false ],
                'DisplayName'    => [ 'type' => 'Edm.String',         'nullable' => true  ],
                'RegisteredDate' => [ 'type' => 'Edm.DateTimeOffset', 'nullable' => true  ],
                'Login'          => [
                    'type'                => 'Edm.String',
                    'nullable'            => true,
                    'required_capability' => 'list_users',
                ],
                'Email'          => [
                    'type'                => 'Edm.String',
                    'nullable'            => true,
                    'required_capability' => 'list_users',
                ],
                'Url'            => [ 'type' => 'Edm.String', 'nullable' => true ],
                'NiceName'       => [ 'type' => 'Edm.String', 'nullable' => true ],
                'Status'         => [ 'type' => 'Edm.Int32',  'nullable' => true ],
            ],
            'nav_properties' => [
                'Posts' => [ 'type' => 'Posts',    'collection' => true ],
                'Meta'  => [ 'type' => 'UserMeta', 'collection' => true ],
            ],
            'adapter_class'  => static::class,
        ];
    }

    // ── Reads ─────────────────────────────────────────────────────────────

    public function get_collection( WPOS_Query_Context $ctx ): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT ID, display_name, user_registered, user_login, user_email,
                    user_url, user_nicename, user_status
             FROM {$wpdb->users}
             ORDER BY ID ASC
             LIMIT %d OFFSET %d",
            $ctx->top,
            $ctx->skip
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        return array_map( [ $this, 'map_row' ], $rows );
    }

    public function get_entity( mixed $key, WPOS_Query_Context $ctx ): ?array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT ID, display_name, user_registered, user_login, user_email,
                    user_url, user_nicename, user_status
             FROM {$wpdb->users}
             WHERE ID = %d
             LIMIT 1",
            (int) $key
        );

        $row = $wpdb->get_row( $sql, ARRAY_A );
        if ( ! is_array( $row ) ) {
            return null;
        }

        return $this->map_row( $row );
    }

    public function get_count( WPOS_Query_Context $ctx ): int {
        global $wpdb;

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );

        return (int) $count;
    }

    // ── Writes ────────────────────────────────────────────────────────────

    public function insert( array $data ): mixed {
        $user_data = $this->map_odata_to_wp( $data );

        $result = wp_insert_user( $user_data );

        if ( is_wp_error( $result ) ) {
            return null;
        }

        return (int) $result;
    }

    public function update( mixed $key, array $data ): bool {
        $user_data         = $this->map_odata_to_wp( $data );
        $user_data['ID']   = (int) $key;

        $result = wp_update_user( $user_data );

        return ! is_wp_error( $result );
    }

    public function delete( mixed $key ): bool {
        $result = wp_delete_user( (int) $key );

        return (bool) $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Map a raw wp_users database row to an OData property array.
     * user_pass is permanently excluded.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function map_row( array $row ): array {
        return [
            'ID'             => (int) $row['ID'],
            'DisplayName'    => $row['display_name'],
            'RegisteredDate' => $row['user_registered'],
            'Login'          => $row['user_login'],
            'Email'          => $row['user_email'],
            'Url'            => $row['user_url'],
            'NiceName'       => $row['user_nicename'],
            'Status'         => (int) $row['user_status'],
        ];
    }

    /**
     * Map OData property names to the wp_insert_user / wp_update_user array keys.
     * user_pass is allowed as an input for creation/update but will never be
     * returned from any read method.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function map_odata_to_wp( array $data ): array {
        $map = [
            'DisplayName'    => 'display_name',
            'RegisteredDate' => 'user_registered',
            'Login'          => 'user_login',
            'Email'          => 'user_email',
            'Url'            => 'user_url',
            'NiceName'       => 'user_nicename',
            'Status'         => 'user_status',
        ];

        $user_data = [];
        foreach ( $map as $odata_key => $wp_key ) {
            if ( array_key_exists( $odata_key, $data ) ) {
                $user_data[ $wp_key ] = $data[ $odata_key ];
            }
        }

        // user_pass is only accepted as direct input (never returned).
        if ( array_key_exists( 'user_pass', $data ) ) {
            $user_data['user_pass'] = $data['user_pass'];
        }

        return $user_data;
    }
}
