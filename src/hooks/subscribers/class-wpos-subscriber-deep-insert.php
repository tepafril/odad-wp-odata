<?php
/**
 * Subscriber: Deep Insert Before — fires before a deep-insert operation begins.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Subscriber_Deep_Insert implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Permission_Engine $permissions,
        private WPOS_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Deep_Insert_Before::class;
    }

    public function handle( WPOS_Event $event ): void {
        if ( $event instanceof WPOS_Event_Deep_Insert_Before ) {
            $event->payload = $this->bridge->filter(
                'wpos_before_deep_insert',
                $event->payload,
                [ $event->entity_set, $event->user ]
            );
            return;
        }

        if ( $event instanceof WPOS_Event_Deep_Insert_Nested_Before ) {
            if ( ! $this->permissions->can_insert( $event->nested_entity_set, $event->user ) ) {
                $event->cancelled = true;
                return;
            }
            $event->nested_payload = $this->bridge->filter(
                'wpos_nested_entity_payload',
                $event->nested_payload,
                [ $event->parent_entity_set, $event->nested_entity_set, $event->user ]
            );
            return;
        }

        if ( $event instanceof WPOS_Event_Deep_Insert_After ) {
            $this->bridge->action( 'wpos_deep_inserted', [
                $event->entity_set,
                $event->key,
                $event->result,
            ] );
        }
    }
}
