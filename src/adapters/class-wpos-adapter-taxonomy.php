<?php
defined( 'ABSPATH' ) || exit;

class WPOS_Adapter_Taxonomy extends WPOS_Adapter_WP_Terms {

    /**
     * Auto-discovers all registered public non-builtin taxonomies and returns
     * one WPOS_Adapter_Taxonomy instance per taxonomy, keyed by entity set name.
     *
     * @return WPOS_Adapter_Taxonomy[]  keyed by entity set name
     */
    public static function discover_all(): array {
        $taxonomies = get_taxonomies( [ 'public' => true, '_builtin' => false ], 'objects' );
        $adapters   = [];

        foreach ( $taxonomies as $taxonomy => $obj ) {
            $entity_set              = self::to_entity_set_name( $obj->labels->name ?? $taxonomy );
            $adapters[ $entity_set ] = new self( $taxonomy, $entity_set );
        }

        return $adapters;
    }

    /**
     * Convert a taxonomy label to a PascalCase entity set name.
     * Examples: 'Genre' → 'Genres', 'Book Genre' → 'BookGenres'
     *
     * @param string $label  Human-readable label from register_taxonomy().
     * @return string
     */
    public static function to_entity_set_name( string $label ): string {
        $words = preg_split( '/[\s_-]+/', $label );
        return implode( '', array_map( 'ucfirst', (array) $words ) );
    }
}
