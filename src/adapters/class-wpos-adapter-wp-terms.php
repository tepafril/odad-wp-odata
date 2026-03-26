<?php
defined( 'ABSPATH' ) || exit;

class WPOS_Adapter_WP_Terms implements WPOS_Adapter {

    public function __construct(
        private string $taxonomy,
        private string $entity_set_name,
    ) {}

    // ── Reads ─────────────────────────────────────────────────────────────

    public function get_collection( WPOS_Query_Context $ctx ): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT t.term_id, t.name, t.slug, tt.description, tt.count, tt.parent, tt.taxonomy
             FROM {$wpdb->terms} t
             JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE tt.taxonomy = %s
             LIMIT %d OFFSET %d",
            $this->taxonomy,
            $ctx->top,
            $ctx->skip,
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
            "SELECT t.term_id, t.name, t.slug, tt.description, tt.count, tt.parent, tt.taxonomy
             FROM {$wpdb->terms} t
             JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE tt.taxonomy = %s
               AND t.term_id = %d",
            $this->taxonomy,
            (int) $key,
        );

        $row = $wpdb->get_row( $sql, ARRAY_A );

        return $row ? $this->map_row( $row ) : null;
    }

    public function get_count( WPOS_Query_Context $ctx ): int {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->terms} t
             JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE tt.taxonomy = %s",
            $this->taxonomy,
        );

        return (int) $wpdb->get_var( $sql );
    }

    // ── Writes ────────────────────────────────────────────────────────────

    public function insert( array $data ): mixed {
        $name = $data['Name'] ?? '';
        $args = [];

        if ( isset( $data['Slug'] ) ) {
            $args['slug'] = $data['Slug'];
        }
        if ( isset( $data['Description'] ) ) {
            $args['description'] = $data['Description'];
        }
        if ( isset( $data['ParentID'] ) ) {
            $args['parent'] = (int) $data['ParentID'];
        }

        $result = wp_insert_term( $name, $this->taxonomy, $args );

        if ( is_wp_error( $result ) ) {
            return null;
        }

        return (int) $result['term_id'];
    }

    public function update( mixed $key, array $data ): bool {
        $args = [];

        if ( isset( $data['Name'] ) ) {
            $args['name'] = $data['Name'];
        }
        if ( isset( $data['Slug'] ) ) {
            $args['slug'] = $data['Slug'];
        }
        if ( isset( $data['Description'] ) ) {
            $args['description'] = $data['Description'];
        }
        if ( isset( $data['ParentID'] ) ) {
            $args['parent'] = (int) $data['ParentID'];
        }

        $result = wp_update_term( (int) $key, $this->taxonomy, $args );

        return ! is_wp_error( $result );
    }

    public function delete( mixed $key ): bool {
        $result = wp_delete_term( (int) $key, $this->taxonomy );

        return $result === true;
    }

    // ── Schema ────────────────────────────────────────────────────────────

    public function get_entity_type_definition(): array {
        return [
            'entity_type'  => $this->entity_set_name . 'EntityType',
            'key_property' => 'ID',
            'properties'   => [
                'ID'          => [ 'type' => 'Edm.Int32',  'nullable' => false ],
                'Name'        => [ 'type' => 'Edm.String', 'nullable' => false ],
                'Slug'        => [ 'type' => 'Edm.String', 'nullable' => false ],
                'Description' => [ 'type' => 'Edm.String', 'nullable' => true  ],
                'Count'       => [ 'type' => 'Edm.Int32',  'nullable' => false, 'read_only' => true ],
                'ParentID'    => [ 'type' => 'Edm.Int32',  'nullable' => true  ],
                'Taxonomy'    => [ 'type' => 'Edm.String', 'nullable' => false ],
            ],
            'nav_properties' => [
                'Posts'    => [ 'type' => 'Posts',                 'collection' => true  ],
                'Parent'   => [ 'type' => $this->entity_set_name,  'collection' => false ],
                'Children' => [ 'type' => $this->entity_set_name,  'collection' => true  ],
            ],
            'adapter_class' => static::class,
        ];
    }

    public function get_entity_set_name(): string {
        return $this->entity_set_name;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function map_row( array $row ): array {
        return [
            'ID'          => (int) $row['term_id'],
            'Name'        => $row['name'],
            'Slug'        => $row['slug'],
            'Description' => $row['description'],
            'Count'       => (int) $row['count'],
            'ParentID'    => isset( $row['parent'] ) ? (int) $row['parent'] : null,
            'Taxonomy'    => $row['taxonomy'],
        ];
    }
}
