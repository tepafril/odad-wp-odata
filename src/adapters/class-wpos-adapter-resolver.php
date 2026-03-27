<?php
defined( 'ABSPATH' ) || exit;

class ODAD_Adapter_Resolver {

    /** @var array<string, ODAD_Adapter> */
    private array $adapters = [];

    public function register( string $entity_set, ODAD_Adapter $adapter ): void {
        $this->adapters[ $entity_set ] = $adapter;
    }

    public function resolve( string $entity_set ): ODAD_Adapter {
        if ( ! isset( $this->adapters[ $entity_set ] ) ) {
            throw new ODAD_Unknown_Entity_Exception(
                "No adapter registered for entity set: {$entity_set}"
            );
        }
        return $this->adapters[ $entity_set ];
    }

    public function has( string $entity_set ): bool {
        return isset( $this->adapters[ $entity_set ] );
    }

    /** @return string[] */
    public function registered_entity_sets(): array {
        return array_keys( $this->adapters );
    }
}
