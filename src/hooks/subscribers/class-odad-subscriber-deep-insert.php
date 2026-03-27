<?php
/**
 * Subscriber: Deep Insert Before — fires before a deep-insert operation begins.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Deep_Insert implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Permission_Engine $permissions,
        private ODAD_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Deep_Insert_Before::class;
    }

    public function handle( ODAD_Event $event ): void {
        if ( $event instanceof ODAD_Event_Deep_Insert_Before ) {
            $event->payload = $this->bridge->filter(
                'ODAD_before_deep_insert',
                $event->payload,
                [ $event->entity_set, $event->user ]
            );
            return;
        }

        if ( $event instanceof ODAD_Event_Deep_Insert_Nested_Before ) {
            if ( ! $this->permissions->can_insert( $event->nested_entity_set, $event->user ) ) {
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

        if ( $event instanceof ODAD_Event_Deep_Insert_After ) {
            $this->bridge->action( 'ODAD_deep_inserted', [
                $event->entity_set,
                $event->key,
                $event->result,
            ] );
        }
    }
}
