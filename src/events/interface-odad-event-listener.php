<?php
/**
 * Interface for event listeners.
 *
 * @package ODAD
 */

interface ODAD_Event_Listener {
    /** Return the fully-qualified class name of the event this listener handles. */
    public function get_event(): string;
    public function handle( ODAD_Event $event ): void;
}
