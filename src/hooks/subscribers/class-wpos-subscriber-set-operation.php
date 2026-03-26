<?php
/**
 * Subscriber: Set Operation Before — fires before a batch set operation begins.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Subscriber_Set_Operation implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Permission_Engine $permissions,
        private WPOS_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Set_Operation_Before::class;
    }

    public function handle( WPOS_Event $event ): void {
        if ( $event instanceof WPOS_Event_Set_Operation_Before ) {
            $wp_op  = $event->operation === 'patch' ? 'update' : 'delete';
            $granted = $this->permissions->can( $event->entity_set, $wp_op, $event->user );
            if ( ! $granted ) {
                $event->cancelled = true;
                return;
            }
            $event->filter_ctx = $this->bridge->filter(
                'wpos_before_set_operation',
                $event->filter_ctx,
                [ $event->entity_set, $event->operation, $event->user ]
            );
            return;
        }

        if ( $event instanceof WPOS_Event_Set_Operation_After ) {
            $this->bridge->action( 'wpos_set_operation_completed', [
                $event->entity_set,
                $event->operation,
                $event->affected_count,
            ] );
        }
    }
}
