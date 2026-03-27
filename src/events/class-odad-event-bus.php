<?php
/**
 * Internal event bus dispatcher.
 *
 * @package ODAD
 */

class ODAD_Event_Bus {
    /** @var array<string, ODAD_Event_Listener[]> */
    private array $listeners = [];

    public function subscribe( ODAD_Event_Listener $listener ): void {
        $this->listeners[ $listener->get_event() ][] = $listener;
    }

    public function dispatch( ODAD_Event $event ): ODAD_Event {
        foreach ( $this->listeners[ get_class( $event ) ] ?? [] as $listener ) {
            $listener->handle( $event );
            if ( $event instanceof ODAD_Stoppable_Event && $event->is_stopped() ) {
                break;
            }
        }
        return $event;
    }
}
