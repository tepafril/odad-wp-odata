<?php
/**
 * Subscriber: Write After — fires after an OData insert or update completes.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Write_After implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Hook_Bridge $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Write_After::class;
    }

    public function handle( ODAD_Event $event ): void {
        /** @var ODAD_Event_Write_After $event */

        $hook    = "ODAD_{$event->operation}d";   // ODAD_inserted / ODAD_updated / ODAD_deleted
        $context = match( $event->operation ) {
            'delete' => [ $event->entity_set, $event->key ],
            default  => [ $event->entity_set, $event->key, $event->result ],
        };

        $this->bridge->action( $hook, $context );
    }
}
