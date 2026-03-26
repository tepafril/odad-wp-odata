<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles deep update: updates a root entity plus nested navigation property
 * changes described by a delta payload (items marked @removed are deleted,
 * items with a key are updated, items without a key are inserted).
 */
class WPOS_Deep_Update {

    public function __construct(
        private WPOS_Adapter_Resolver $adapter_resolver,
        private WPOS_Schema_Registry  $schema_registry,
        private WPOS_Event_Bus        $event_bus,
    ) {}

    /**
     * Execute a deep update.
     *
     * @param string   $entity_set
     * @param mixed    $key         Root entity key.
     * @param array    $payload     Delta payload (may contain @delta nav properties).
     * @param \WP_User $user
     * @return array   Updated root entity.
     * @throws \RuntimeException on failure or cancellation.
     */
    public function execute( string $entity_set, mixed $key, array $payload, \WP_User $user ): array {
        // 1. Dispatch before event.
        $before = new WPOS_Event_Deep_Update_Before( $entity_set, $key, $user, $payload );
        $this->event_bus->dispatch( $before );

        if ( $before->cancelled ) {
            throw new \RuntimeException( 'Deep update cancelled by event listener.' );
        }

        $payload = $before->payload;

        // 2. Split root properties from delta navigation properties.
        [ $root_payload, $delta_nav ] = $this->split_delta_payload( $entity_set, $payload );

        $root_adapter = $this->adapter_resolver->resolve( $entity_set );

        // 3. Update root entity (if there are root properties to update).
        if ( ! empty( $root_payload ) ) {
            $root_adapter->update( $key, $root_payload );
        }

        // 4. Process delta navigation properties.
        foreach ( $delta_nav as $nav_property => $delta_items ) {
            $nav_def = $this->get_nav_definition( $entity_set, $nav_property );
            if ( $nav_def === null ) {
                continue;
            }

            $nested_entity_set = $nav_def['type'];
            $nested_adapter    = $this->adapter_resolver->resolve( $nested_entity_set );
            $def               = $this->schema_registry->get( $nested_entity_set );
            $nested_key_prop   = $def['key_property'] ?? 'ID';

            foreach ( $delta_items as $delta_item ) {
                // Determine operation.
                if ( isset( $delta_item['@removed'] ) ) {
                    $operation  = 'delete';
                    $nested_key = $delta_item[ $nested_key_prop ] ?? null;
                } elseif ( isset( $delta_item[ $nested_key_prop ] ) ) {
                    $operation  = 'update';
                    $nested_key = $delta_item[ $nested_key_prop ];
                } else {
                    $operation  = 'insert';
                    $nested_key = null;
                }

                // Clean @removed flag from payload.
                unset( $delta_item['@removed'] );

                // Dispatch nested before event.
                $nested_before = new WPOS_Event_Deep_Update_Nested_Before(
                    $entity_set,
                    $nested_entity_set,
                    $operation,
                    $user,
                    $delta_item,
                    $nested_key
                );
                $this->event_bus->dispatch( $nested_before );

                if ( $nested_before->cancelled ) {
                    throw new \RuntimeException(
                        "Deep update nested operation '{$operation}' on '{$nested_entity_set}' cancelled."
                    );
                }

                $nested_payload = $nested_before->nested_payload;

                match ( $operation ) {
                    'insert' => $nested_adapter->insert( $nested_payload ),
                    'update' => $nested_adapter->update( $nested_key, $nested_payload ),
                    'delete' => $nested_adapter->delete( $nested_key ),
                };
            }
        }

        // 5. Re-fetch updated root entity.
        $ctx    = new WPOS_Query_Context();
        $result = $root_adapter->get_entity( $key, $ctx ) ?? [];

        // 6. Dispatch after event.
        $after = new WPOS_Event_Deep_Update_After( $entity_set, $key, $user, $result );
        $this->event_bus->dispatch( $after );

        return $result;
    }

    /**
     * Split delta payload into root properties and delta navigation payloads.
     * Delta nav properties have keys ending in '@delta'.
     *
     * @return array{0: array, 1: array}  [root_payload, delta_nav]
     */
    private function split_delta_payload( string $entity_set, array $payload ): array {
        $nav_map   = $this->get_nav_map( $entity_set );
        $root      = [];
        $delta_nav = [];

        foreach ( $payload as $key => $value ) {
            if ( str_ends_with( $key, '@delta' ) && is_array( $value ) ) {
                $nav_property          = substr( $key, 0, -6 ); // strip '@delta'
                $delta_nav[ $nav_property ] = $value;
            } elseif ( isset( $nav_map[ $key ] ) && is_array( $value ) ) {
                // Non-delta nav properties treated as replace (insert all).
                $delta_nav[ $key ] = $value;
            } else {
                $root[ $key ] = $value;
            }
        }

        return [ $root, $delta_nav ];
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
}
