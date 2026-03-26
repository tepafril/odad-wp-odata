<?php
defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates all write operations: insert, update, delete.
 * Routes to WPOS_Deep_Insert / WPOS_Deep_Update for nested payloads,
 * or direct adapter calls for simple payloads.
 * Dispatches WPOS_Event_Write_Before and WPOS_Event_Write_After.
 */
class WPOS_Write_Handler {

    public function __construct(
        private WPOS_Adapter_Resolver $adapter_resolver,
        private WPOS_Schema_Registry  $schema_registry,
        private WPOS_Deep_Insert      $deep_insert,
        private WPOS_Deep_Update      $deep_update,
        private WPOS_Set_Operations   $set_operations,
        private WPOS_Event_Bus        $event_bus,
    ) {}

    /**
     * Insert a new entity.
     *
     * @param string   $entity_set
     * @param array    $payload
     * @param \WP_User $user
     * @return array   The created entity.
     */
    public function insert( string $entity_set, array $payload, \WP_User $user ): array {
        // Dispatch write-before event (permission check + payload filter happen in subscriber).
        $before = new WPOS_Event_Write_Before( $entity_set, 'insert', $user, $payload );
        $this->event_bus->dispatch( $before );

        if ( $before->cancelled ) {
            throw new \RuntimeException( 'Insert cancelled: permission denied.' );
        }

        $payload = $before->payload;

        // If payload contains navigation properties, use deep insert.
        if ( $this->has_nav_properties( $entity_set, $payload ) ) {
            $result = $this->deep_insert->execute( $entity_set, $payload, $user );
        } else {
            $adapter = $this->adapter_resolver->resolve( $entity_set );
            $key     = $adapter->insert( $payload );
            $ctx     = new WPOS_Query_Context();
            $result  = $adapter->get_entity( $key, $ctx ) ?? [];
        }

        // Dispatch write-after event.
        $key   = $result[ $this->get_key_property( $entity_set ) ] ?? null;
        $after = new WPOS_Event_Write_After( $entity_set, 'insert', $user, $key, $result );
        $this->event_bus->dispatch( $after );

        return $result;
    }

    /**
     * Update an existing entity (PATCH — partial update).
     *
     * @param string   $entity_set
     * @param mixed    $key
     * @param array    $payload
     * @param \WP_User $user
     * @return array   The updated entity.
     */
    public function update( string $entity_set, mixed $key, array $payload, \WP_User $user ): array {
        $before = new WPOS_Event_Write_Before( $entity_set, 'update', $user, $payload, $key );
        $this->event_bus->dispatch( $before );

        if ( $before->cancelled ) {
            throw new \RuntimeException( 'Update cancelled: permission denied.' );
        }

        $payload = $before->payload;

        // If payload contains delta nav properties, use deep update.
        if ( $this->has_delta_nav_properties( $entity_set, $payload ) ) {
            $result = $this->deep_update->execute( $entity_set, $key, $payload, $user );
        } else {
            $adapter = $this->adapter_resolver->resolve( $entity_set );
            $adapter->update( $key, $payload );
            $ctx    = new WPOS_Query_Context();
            $result = $adapter->get_entity( $key, $ctx ) ?? [];
        }

        $after = new WPOS_Event_Write_After( $entity_set, 'update', $user, $key, $result );
        $this->event_bus->dispatch( $after );

        return $result;
    }

    /**
     * Delete an entity.
     *
     * @param string   $entity_set
     * @param mixed    $key
     * @param \WP_User $user
     */
    public function delete( string $entity_set, mixed $key, \WP_User $user ): void {
        $before = new WPOS_Event_Write_Before( $entity_set, 'delete', $user, [], $key );
        $this->event_bus->dispatch( $before );

        if ( $before->cancelled ) {
            throw new \RuntimeException( 'Delete cancelled: permission denied.' );
        }

        $adapter = $this->adapter_resolver->resolve( $entity_set );
        $adapter->delete( $key );

        $after = new WPOS_Event_Write_After( $entity_set, 'delete', $user, $key, [] );
        $this->event_bus->dispatch( $after );
    }

    // -------------------------------------------------------------------------

    private function has_nav_properties( string $entity_set, array $payload ): bool {
        $nav_map = $this->get_nav_map( $entity_set );
        foreach ( array_keys( $payload ) as $key ) {
            if ( isset( $nav_map[ $key ] ) && is_array( $payload[ $key ] ) ) {
                return true;
            }
        }
        return false;
    }

    private function has_delta_nav_properties( string $entity_set, array $payload ): bool {
        foreach ( array_keys( $payload ) as $key ) {
            if ( str_ends_with( $key, '@delta' ) ) {
                return true;
            }
        }
        return $this->has_nav_properties( $entity_set, $payload );
    }

    private function get_nav_map( string $entity_set ): array {
        if ( ! $this->schema_registry->has( $entity_set ) ) {
            return [];
        }
        $def = $this->schema_registry->get( $entity_set );
        return $def['nav_properties'] ?? [];
    }

    private function get_key_property( string $entity_set ): string {
        if ( $this->schema_registry->has( $entity_set ) ) {
            $def = $this->schema_registry->get( $entity_set );
            return $def['key_property'] ?? 'ID';
        }
        return 'ID';
    }
}
