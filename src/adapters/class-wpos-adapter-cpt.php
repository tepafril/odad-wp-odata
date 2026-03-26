<?php
defined( 'ABSPATH' ) || exit;

class WPOS_Adapter_CPT extends WPOS_Adapter_WP_Posts {

    /**
     * Auto-discovers all registered public non-builtin CPTs and returns one
     * WPOS_Adapter_CPT instance per post type, keyed by entity set name.
     *
     * @return WPOS_Adapter_CPT[]  keyed by entity set name
     */
    public static function discover_all(): array {
        $post_types = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
        $adapters   = [];

        foreach ( $post_types as $post_type => $obj ) {
            $entity_set              = self::to_entity_set_name( $obj->labels->name ?? $post_type );
            $adapters[ $entity_set ] = new self( $post_type, $entity_set );
        }

        return $adapters;
    }

    /**
     * Convert a post type label to a PascalCase entity set name.
     * Examples: 'Book' → 'Books', 'Book Review' → 'BookReviews'
     *
     * @param string $label  Human-readable label from register_post_type().
     * @return string
     */
    public static function to_entity_set_name( string $label ): string {
        $words = preg_split( '/[\s_-]+/', $label );
        return implode( '', array_map( 'ucfirst', (array) $words ) );
    }
}
