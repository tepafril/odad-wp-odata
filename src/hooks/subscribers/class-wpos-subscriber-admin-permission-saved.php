<?php
/**
 * Subscriber: Admin Permission Saved — fires when an admin saves permission settings.
 *
 * Handles WPOS_Event_Admin_Permission_Saved:
 *   1. Fires the 'wpos_admin_permission_saved' WP action.
 *   2. Dispatches WPOS_Event_Schema_Changed to bust the metadata cache.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Subscriber_Admin_Permission_Saved implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Hook_Bridge $bridge,
        private WPOS_Event_Bus   $event_bus,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Admin_Permission_Saved::class;
    }

    public function handle( WPOS_Event $event ): void {
        /** @var WPOS_Event_Admin_Permission_Saved $event */

        // 1. Fire WP action so external plugins can react.
        $this->bridge->action( 'wpos_admin_permission_saved', [
            $event->entity_set,
            $event->permissions,
        ] );

        // 2. Schema changed event busts the metadata cache transient.
        $this->event_bus->dispatch( new WPOS_Event_Schema_Changed(
            reason:     'permissions_updated',
            entity_set: $event->entity_set,
        ) );
    }
}
