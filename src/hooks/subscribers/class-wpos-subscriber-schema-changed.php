<?php
/**
 * Subscriber: Schema Changed — invalidates the metadata cache when the schema changes.
 *
 * Receives WPOS_Event_Schema_Changed dispatched whenever an entity set is
 * registered, removed, or its configuration is updated. Delegates cache
 * invalidation to WPOS_Metadata_Cache::bust() so that the next request
 * triggers a full CSDL rebuild.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Subscriber_Schema_Changed implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Metadata_Cache $cache,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Schema_Changed::class;
    }

    public function handle( WPOS_Event $event ): void {
        $this->cache->bust();
    }
}
