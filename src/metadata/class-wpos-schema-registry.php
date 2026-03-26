<?php
/**
 * WPOS_Schema_Registry — holds the canonical list of entity-set definitions.
 *
 * External adapters (Phase 2+) call register() to contribute entity types,
 * properties, navigation properties, and adapter bindings. The metadata
 * builder reads the registry when constructing the CSDL document.
 *
 * Definition array shape:
 * [
 *   'entity_type'    => 'PostEntityType',
 *   'key_property'   => 'ID',
 *   'properties'     => [
 *     'ID'    => [ 'type' => 'Edm.Int32',  'nullable' => false ],
 *     'Title' => [ 'type' => 'Edm.String', 'nullable' => true  ],
 *   ],
 *   'nav_properties' => [
 *     'Author' => [ 'type' => 'Users', 'collection' => false ],
 *     'Tags'   => [ 'type' => 'Tags',  'collection' => true  ],
 *   ],
 *   'adapter_class'  => WPOS_Adapter_WP_Posts::class,
 * ]
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Schema_Registry {

    /** @var array<string, array> entity_set_name → definition */
    private array $entity_sets = [];

    /**
     * Register an entity set with its full definition.
     *
     * Registering the same name a second time overwrites the previous definition,
     * allowing adapters to update their schema during runtime.
     *
     * @param string $entity_set Entity-set name, e.g. 'Posts'.
     * @param array  $definition Entity-set definition array.
     */
    public function register( string $entity_set, array $definition ): void {
        if ( '' !== $entity_set ) {
            $this->entity_sets[ $entity_set ] = $definition;
        }
    }

    /**
     * Check whether an entity set is registered.
     *
     * @param string $entity_set Entity-set name.
     * @return bool True if the entity set is registered.
     */
    public function has( string $entity_set ): bool {
        return isset( $this->entity_sets[ $entity_set ] );
    }

    /**
     * Retrieve the definition for a single entity set.
     *
     * @param string $entity_set Entity-set name.
     * @return array Definition array, or empty array when not found.
     */
    public function get( string $entity_set ): array {
        return $this->entity_sets[ $entity_set ] ?? [];
    }

    /**
     * Return all registered entity-set definitions keyed by entity-set name.
     *
     * @return array<string, array>
     */
    public function all(): array {
        return $this->entity_sets;
    }

    /**
     * Deregister an entity set.
     *
     * @param string $entity_set Entity-set name.
     */
    public function remove( string $entity_set ): void {
        unset( $this->entity_sets[ $entity_set ] );
    }

    // -------------------------------------------------------------------------
    // Backwards-compatibility helpers used by the Phase 1 metadata builder.
    // -------------------------------------------------------------------------

    /**
     * Return all registered entity-set names.
     *
     * @return string[]
     */
    public function get_entity_set_names(): array {
        return array_keys( $this->entity_sets );
    }
}
