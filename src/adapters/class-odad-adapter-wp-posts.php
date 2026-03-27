<?php
defined( 'ABSPATH' ) || exit;

class ODAD_Adapter_WP_Posts implements ODAD_Adapter {

    /**
     * Map from OData property names to wp_posts column names.
     */
    private const PROPERTY_MAP = [
        'ID'            => 'ID',
        'Title'         => 'post_title',
        'Content'       => 'post_content',
        'Excerpt'       => 'post_excerpt',
        'Status'        => 'post_status',
        'Slug'          => 'post_name',
        'PublishedDate' => 'post_date_gmt',
        'ModifiedDate'  => 'post_modified_gmt',
        'AuthorID'      => 'post_author',
        'ParentID'      => 'post_parent',
        'MenuOrder'     => 'menu_order',
        'CommentCount'  => 'comment_count',
        'Type'          => 'post_type',
        'GUID'          => 'guid',
    ];

    /**
     * Map from OData property names to wp_insert_post() / wp_update_post() arg keys.
     */
    private const WP_API_MAP = [
        'Title'         => 'post_title',
        'Content'       => 'post_content',
        'Excerpt'       => 'post_excerpt',
        'Status'        => 'post_status',
        'Slug'          => 'post_name',
        'PublishedDate' => 'post_date_gmt',
        'AuthorID'      => 'post_author',
        'ParentID'      => 'post_parent',
        'MenuOrder'     => 'menu_order',
        'Type'          => 'post_type',
        'GUID'          => 'guid',
    ];

    public function __construct(
        private string $post_type,
        private string $entity_set_name,
    ) {}

    // ── Schema ────────────────────────────────────────────────────────────

    public function get_entity_set_name(): string {
        return $this->entity_set_name;
    }

    public function get_entity_type_definition(): array {
        return [
            'entity_type'  => $this->entity_set_name . 'EntityType',
            'key_property' => 'ID',
            'properties'   => [
                'ID'            => [ 'type' => 'Edm.Int32',          'nullable' => false ],
                'Title'         => [ 'type' => 'Edm.String',         'nullable' => true  ],
                'Content'       => [ 'type' => 'Edm.String',         'nullable' => true  ],
                'Excerpt'       => [ 'type' => 'Edm.String',         'nullable' => true  ],
                'Status'        => [ 'type' => 'Edm.String',         'nullable' => true  ],
                'Slug'          => [ 'type' => 'Edm.String',         'nullable' => true  ],
                'PublishedDate' => [ 'type' => 'Edm.DateTimeOffset', 'nullable' => true  ],
                'ModifiedDate'  => [ 'type' => 'Edm.DateTimeOffset', 'nullable' => true  ],
                'AuthorID'      => [ 'type' => 'Edm.Int32',          'nullable' => true  ],
                'ParentID'      => [ 'type' => 'Edm.Int32',          'nullable' => true  ],
                'MenuOrder'     => [ 'type' => 'Edm.Int32',          'nullable' => true  ],
                'CommentCount'  => [ 'type' => 'Edm.Int32',          'nullable' => true  ],
                'Type'          => [ 'type' => 'Edm.String',         'nullable' => true  ],
                'GUID'          => [ 'type' => 'Edm.String',         'nullable' => true  ],
            ],
            'nav_properties' => [
                'Author'     => [ 'type' => 'Users',      'collection' => false ],
                'Tags'       => [ 'type' => 'Tags',       'collection' => true  ],
                'Categories' => [ 'type' => 'Categories', 'collection' => true  ],
                'Meta'       => [ 'type' => 'PostMeta',   'collection' => true  ],
                'Comments'   => [ 'type' => 'Comments',   'collection' => true  ],
            ],
            'adapter_class' => static::class,
        ];
    }

    // ── Reads ─────────────────────────────────────────────────────────────

    public function get_collection( ODAD_Query_Context $ctx ): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT ID, post_title, post_content, post_excerpt, post_status,
                    post_name, post_date_gmt, post_modified_gmt, post_author,
                    post_parent, menu_order, comment_count, post_type, guid
             FROM {$wpdb->posts}
             WHERE post_type = %s
               AND post_status != 'auto-draft'
             ORDER BY ID ASC
             LIMIT %d OFFSET %d",
            $this->post_type,
            $ctx->top,
            $ctx->skip
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        return array_map( [ $this, 'map_row_to_odata' ], $rows );
    }

    public function get_entity( mixed $key, ODAD_Query_Context $ctx ): ?array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT ID, post_title, post_content, post_excerpt, post_status,
                    post_name, post_date_gmt, post_modified_gmt, post_author,
                    post_parent, menu_order, comment_count, post_type, guid
             FROM {$wpdb->posts}
             WHERE ID = %d
               AND post_type = %s
               AND post_status != 'auto-draft'
             LIMIT 1",
            (int) $key,
            $this->post_type
        );

        $row = $wpdb->get_row( $sql, ARRAY_A );

        if ( ! is_array( $row ) ) {
            return null;
        }

        return $this->map_row_to_odata( $row );
    }

    public function get_count( ODAD_Query_Context $ctx ): int {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = %s
               AND post_status != 'auto-draft'",
            $this->post_type
        );

        return (int) $wpdb->get_var( $sql );
    }

    // ── Writes ────────────────────────────────────────────────────────────

    public function insert( array $data ): mixed {
        $post_args = $this->map_odata_to_wp_args( $data );

        // Always enforce the post_type for this adapter instance.
        $post_args['post_type'] = $this->post_type;

        $result = wp_insert_post( $post_args, true );

        if ( is_wp_error( $result ) ) {
            throw new RuntimeException(
                'ODAD_Adapter_WP_Posts::insert() failed: ' . $result->get_error_message()
            );
        }

        return $result; // new post ID
    }

    public function update( mixed $key, array $data ): bool {
        $post_args = $this->map_odata_to_wp_args( $data );

        // Always set the ID being updated.
        $post_args['ID'] = (int) $key;

        $result = wp_update_post( $post_args, true );

        if ( is_wp_error( $result ) ) {
            throw new RuntimeException(
                'ODAD_Adapter_WP_Posts::update() failed: ' . $result->get_error_message()
            );
        }

        return $result !== 0;
    }

    public function delete( mixed $key ): bool {
        $deleted = wp_delete_post( (int) $key, true );

        return $deleted !== false && $deleted !== null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Convert a raw wp_posts row (column names) to an OData property array.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function map_row_to_odata( array $row ): array {
        $col_to_prop = array_flip( self::PROPERTY_MAP );

        $entity = [];
        foreach ( $row as $col => $value ) {
            $prop            = $col_to_prop[ $col ] ?? $col;
            $entity[ $prop ] = $value;
        }

        return $entity;
    }

    /**
     * Convert an OData property array to wp_insert_post() / wp_update_post() args.
     *
     * Read-only properties (ID, CommentCount, ModifiedDate) are intentionally
     * excluded — ModifiedDate is managed by WordPress itself.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function map_odata_to_wp_args( array $data ): array {
        $args = [];
        foreach ( self::WP_API_MAP as $prop => $wp_key ) {
            if ( array_key_exists( $prop, $data ) ) {
                $args[ $wp_key ] = $data[ $prop ];
            }
        }

        return $args;
    }
}
