<?php
/**
 * Subscriber: Deep Update Before — fires before a deep-update operation begins.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Deep_Update implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Permission_Engine $permissions,
        private ODAD_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Deep_Update_Before::class;
    }

    public function handle( ODAD_Event $event ): void {
        if ( $event instanceof ODAD_Event_Deep_Update_Before ) {
            $event->payload = $this->bridge->filter(
                'ODAD_before_deep_update',
                $event->payload,
                [ $event->entity_set, $event->key, $event->user ]
            );
            return;
        }

        if ( $event instanceof ODAD_Event_Deep_Update_Nested_Before ) {
            $can = match ( $event->operation ) {
                'insert' => $this->permissions->can_insert( $event->nested_entity_set, $event->user ),
                'update' => $this->permissions->can_update( $event->nested_entity_set, $event->user, $event->nested_key ),
                'delete' => $this->permissions->can_delete( $event->nested_entity_set, $event->user, $event->nested_key ),
                default  => false,
            };
            if ( ! $can ) {
                $event->cancelled = true;
                return;
            }
            $event->nested_payload = $this->bridge->filter(
                'ODAD_nested_entity_payload',
                $event->nested_payload,
                [ $event->parent_entity_set, $event->nested_entity_set, $event->user ]
            );
            return;
        }

        if ( $event instanceof ODAD_Event_Deep_Update_After ) {
            $this->bridge->action( 'ODAD_deep_updated', [
                $event->entity_set,
                $event->key,
                $event->result,
            ] );
        }
    }
}
