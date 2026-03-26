<?php
/**
 * Interface for event listeners.
 *
 * @package WPOS
 */

interface WPOS_Event_Listener {
    /** Return the fully-qualified class name of the event this listener handles. */
    public function get_event(): string;
    public function handle( WPOS_Event $event ): void;
}
