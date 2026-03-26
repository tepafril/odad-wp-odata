<?php
/**
 * Subscriber: Admin Entity Config Saved — fires when an admin saves entity configuration.
 *
 * Handles WPOS_Event_Admin_Entity_Config_Saved:
 *   1. Fires the 'wpos_admin_entity_config_saved' WP action.
 *   2. Dispatches WPOS_Event_Schema_Changed to bust the metadata cache.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Subscriber_Admin_Config_Saved implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Hook_Bridge $bridge,
        private WPOS_Event_Bus   $event_bus,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Admin_Entity_Config_Saved::class;
    }

    public function handle( WPOS_Event $event ): void {
        /** @var WPOS_Event_Admin_Entity_Config_Saved $event */

        // 1. Fire WP action so external plugins can react.
        $this->bridge->action( 'wpos_admin_entity_config_saved', [
            $event->entity_set,
            $event->config,
        ] );

        // 2. Trigger schema change → metadata cache is busted automatically.
        $this->event_bus->dispatch( new WPOS_Event_Schema_Changed(
            reason:     'config_updated',
            entity_set: $event->entity_set,
        ) );
    }
}
