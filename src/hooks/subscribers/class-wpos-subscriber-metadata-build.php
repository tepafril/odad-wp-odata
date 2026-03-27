<?php
/**
 * Subscriber: Metadata Build — fires when the OData $metadata document is assembled.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Metadata_Build implements ODAD_Event_Listener {

    public function __construct() {}

    public function get_event(): string {
        return ODAD_Event_Metadata_Build::class;
    }

    public function handle( ODAD_Event $event ): void {
        // Implemented in a later task
    }
}
