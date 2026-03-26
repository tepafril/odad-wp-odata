<?php
/**
 * Subscriber: Write After — fires after an OData insert or update completes.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Subscriber_Write_After implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Hook_Bridge $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Write_After::class;
    }

    public function handle( WPOS_Event $event ): void {
        /** @var WPOS_Event_Write_After $event */

        $hook    = "wpos_{$event->operation}d";   // wpos_inserted / wpos_updated / wpos_deleted
        $context = match( $event->operation ) {
            'delete' => [ $event->entity_set, $event->key ],
            default  => [ $event->entity_set, $event->key, $event->result ],
        };

        $this->bridge->action( $hook, $context );
    }
}
