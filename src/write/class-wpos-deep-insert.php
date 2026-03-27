<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles deep insert: inserts a root entity plus any nested navigation
 * property entities in a single operation, dispatching lifecycle events
 * for each nested entity.
 */
class ODAD_Deep_Insert {

    public function __construct(
        private ODAD_Adapter_Resolver $adapter_resolver,
        private ODAD_Schema_Registry  $schema_registry,
        private ODAD_Event_Bus        $event_bus,
    ) {}

    /**
     * Execute a deep insert.
     *
     * @param string   $entity_set Root entity set name.
     * @param array    $payload    Full payload including navigation property data.
     * @param \WP_User $user
     * @return array   The inserted root entity (re-fetched after insert).
     * @throws \RuntimeException on failure or cancellation.
     */
    public function execute( string $entity_set, array $payload, \WP_User $user ): array {
        // 1. Separate root properties from navigation property data.
        [ $root_payload, $nav_payloads ] = $this->split_payload( $entity_set, $payload );

        // 2. Dispatch before event — external plugins can modify or cancel.
        $before          = new ODAD_Event_Deep_Insert_Before( $entity_set, $user, $payload );
        $this->event_bus->dispatch( $before );

        if ( $before->cancelled ) {
            throw new \RuntimeException( 'Deep insert cancelled by event listener.' );
        }

        // Use potentially-modified payload from the event.
        $payload      = $before->payload;
        [ $root_payload, $nav_payloads ] = $this->split_payload( $entity_set, $payload );

        // 3. Insert root entity.
        $root_adapter = $this->adapter_resolver->resolve( $entity_set );
        $root_key     = $root_adapter->insert( $root_payload );

        if ( $root_key === null || $root_key === false ) {
            throw new \RuntimeException( "Failed to insert root entity '{$entity_set}'." );
        }

        // 4. Insert nested entities.
        $inserted_nested = [];
        try {
            foreach ( $nav_payloads as $nav_property => $nested_items ) {
                $nav_def = $this->get_nav_definition( $entity_set, $nav_property );
                if ( $nav_def === null ) {
                    continue;
                }

                $nested_entity_set = $nav_def['type'];
                $items             = isset( $nested_items[0] ) ? $nested_items : [ $nested_items ];

                foreach ( $items as $nested_payload ) {
                    // Dispatch nested before event.
                    $nested_before = new ODAD_Event_Deep_Insert_Nested_Before(
                        $entity_set,
                        $nested_entity_set,
                        $nav_property,
                        $user,
                        $nested_payload
                    );
                    $this->event_bus->dispatch( $nested_before );

                    if ( $nested_before->cancelled ) {
                        throw new \RuntimeException(
                            "Deep insert of nested entity '{$nested_entity_set}' cancelled."
                        );
                    }

                    $nested_payload = $nested_before->nested_payload;

                    // Insert nested entity.
                    $nested_adapter = $this->adapter_resolver->resolve( $nested_entity_set );
                    $nested_key     = $nested_adapter->insert( $nested_payload );
                    $inserted_nested[] = [
                        'entity_set' => $nested_entity_set,
                        'key'        => $nested_key,
                        'nav'        => $nav_property,
                    ];

                    // Create relationship between root and nested entity.
                    $this->create_relationship( $entity_set, $root_key, $nav_def, $nested_key );
                }
            }
        } catch ( \Throwable $e ) {
            // Attempt cleanup: delete root entity.
            try {
                $root_adapter->delete( $root_key );
            } catch ( \Throwable ) {
                // Best-effort cleanup.
            }
            throw $e;
        }

        // 5. Re-fetch root entity to return complete representation.
        $ctx    = new ODAD_Query_Context();
        $result = $root_adapter->get_entity( $root_key, $ctx ) ?? [];

        // 6. Dispatch after event.
        $after = new ODAD_Event_Deep_Insert_After( $entity_set, $user, $root_key, $result );
        $this->event_bus->dispatch( $after );

        return $result;
    }

    /**
     * Split a payload into root properties and navigation property payloads.
     *
     * @return array{0: array, 1: array}  [root_payload, nav_payloads]
     */
    private function split_payload( string $entity_set, array $payload ): array {
        $nav_map     = $this->get_nav_map( $entity_set );
        $root        = [];
        $nav_payloads = [];

        foreach ( $payload as $key => $value ) {
            if ( isset( $nav_map[ $key ] ) && is_array( $value ) ) {
                $nav_payloads[ $key ] = $value;
            } else {
                $root[ $key ] = $value;
            }
        }

        return [ $root, $nav_payloads ];
    }

    private function get_nav_map( string $entity_set ): array {
        if ( ! $this->schema_registry->has( $entity_set ) ) {
            return [];
        }
        $def = $this->schema_registry->get( $entity_set );
        return $def['nav_properties'] ?? [];
    }

    private function get_nav_definition( string $entity_set, string $nav_property ): ?array {
        $nav_map = $this->get_nav_map( $entity_set );
        return $nav_map[ $nav_property ] ?? null;
    }

    private function create_relationship(
        string $entity_set,
        mixed  $root_key,
        array  $nav_def,
        mixed  $nested_key
    ): void {
        $relationship = $nav_def['relationship'] ?? null;
        if ( $relationship === null ) {
            return;
        }

        if ( $relationship === 'wp_term_relationships' && function_exists( 'wp_set_object_terms' ) ) {
            $taxonomy = $nav_def['taxonomy'] ?? '';
            if ( $taxonomy ) {
                wp_set_object_terms( (int) $root_key, [ (int) $nested_key ], $taxonomy, true );
            }
        }
    }
}
