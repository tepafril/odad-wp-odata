<?php
/**
 * Internal event bus dispatcher.
 *
 * @package WPOS
 */

class WPOS_Event_Bus {
    /** @var array<string, WPOS_Event_Listener[]> */
    private array $listeners = [];

    public function subscribe( WPOS_Event_Listener $listener ): void {
        $this->listeners[ $listener->get_event() ][] = $listener;
    }

    public function dispatch( WPOS_Event $event ): WPOS_Event {
        foreach ( $this->listeners[ get_class( $event ) ] ?? [] as $listener ) {
            $listener->handle( $event );
            if ( $event instanceof WPOS_Stoppable_Event && $event->is_stopped() ) {
                break;
            }
        }
        return $event;
    }
}
