<?php
/**
 * Subscriber: Admin Entity Config Saved — fires when an admin saves entity configuration.
 *
 * Handles ODAD_Event_Admin_Entity_Config_Saved:
 *   1. Fires the 'ODAD_admin_entity_config_saved' WP action.
 *   2. Dispatches ODAD_Event_Schema_Changed to bust the metadata cache.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Admin_Config_Saved implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Hook_Bridge $bridge,
        private ODAD_Event_Bus   $event_bus,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Admin_Entity_Config_Saved::class;
    }

    public function handle( ODAD_Event $event ): void {
        /** @var ODAD_Event_Admin_Entity_Config_Saved $event */

        // 1. Fire WP action so external plugins can react.
        $this->bridge->action( 'ODAD_admin_entity_config_saved', [
            $event->entity_set,
            $event->config,
        ] );

        // 2. Trigger schema change → metadata cache is busted automatically.
        $this->event_bus->dispatch( new ODAD_Event_Schema_Changed(
            reason:     'config_updated',
            entity_set: $event->entity_set,
        ) );
    }
}
