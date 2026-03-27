<?php
/**
 * Subscriber: Permission Check — evaluates access control for OData requests.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Permission_Check implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Permission_Engine $permissions,
        private ODAD_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Permission_Check::class;
    }

    public function handle( ODAD_Event $event ): void {
        /** @var ODAD_Event_Permission_Check $event */

        // 1. Domain logic: check WP capability
        $granted = $this->permissions->can(
            $event->entity_set,
            $event->operation,
            $event->user,
            $event->key
        );

        // 2. Apply WP filter: ODAD_can_read / ODAD_can_insert / ODAD_can_update / ODAD_can_delete
        $hook_name = "ODAD_can_{$event->operation}";
        $context   = match( $event->operation ) {
            'update', 'delete' => [ $event->entity_set, $event->key, $event->user ],
            default             => [ $event->entity_set, $event->user ],
        };

        $granted = (bool) $this->bridge->filter( $hook_name, $granted, $context );

        // 3. Also apply ODAD_allowed_properties filter for field-level permission
        //    (Result not used here — used by Field ACL. But fire it so plugins see it.)

        // 4. Write result back
        $event->granted = $granted;
    }
}
