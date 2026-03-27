<?php
/**
 * Subscriber: Schema Changed — invalidates the metadata cache when the schema changes.
 *
 * Receives ODAD_Event_Schema_Changed dispatched whenever an entity set is
 * registered, removed, or its configuration is updated. Delegates cache
 * invalidation to ODAD_Metadata_Cache::bust() so that the next request
 * triggers a full CSDL rebuild.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Schema_Changed implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Metadata_Cache     $cache,
        private ?ODAD_OpenAPI_Cache     $openapi_cache = null,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Schema_Changed::class;
    }

    public function handle( ODAD_Event $event ): void {
        $this->cache->bust();
        $this->openapi_cache?->bust();
    }
}
