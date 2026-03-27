<?php
/**
 * Subscriber: Admin Permission Saved — fires when an admin saves permission settings.
 *
 * Handles ODAD_Event_Admin_Permission_Saved:
 *   1. Fires the 'ODAD_admin_permission_saved' WP action.
 *   2. Dispatches ODAD_Event_Schema_Changed to bust the metadata cache.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Admin_Permission_Saved implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Hook_Bridge $bridge,
        private ODAD_Event_Bus   $event_bus,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Admin_Permission_Saved::class;
    }

    public function handle( ODAD_Event $event ): void {
        /** @var ODAD_Event_Admin_Permission_Saved $event */

        // 1. Fire WP action so external plugins can react.
        $this->bridge->action( 'ODAD_admin_permission_saved', [
            $event->entity_set,
            $event->permissions,
        ] );

        // 2. Schema changed event busts the metadata cache transient.
        $this->event_bus->dispatch( new ODAD_Event_Schema_Changed(
            reason:     'permissions_updated',
            entity_set: $event->entity_set,
        ) );
    }
}
