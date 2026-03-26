<?php
/**
 * Subscriber: Metadata Build — fires when the OData $metadata document is assembled.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Subscriber_Metadata_Build implements WPOS_Event_Listener {

    public function __construct() {}

    public function get_event(): string {
        return WPOS_Event_Metadata_Build::class;
    }

    public function handle( WPOS_Event $event ): void {
        // Implemented in a later task
    }
}
