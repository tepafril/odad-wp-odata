<?php
/**
 * Subscriber: Write Before — fires before an OData insert or update is persisted.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Subscriber_Write_Before implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Permission_Engine $permissions,
        private WPOS_Field_ACL         $field_acl,
        private WPOS_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Write_Before::class;
    }

    public function handle( WPOS_Event $event ): void {
        /** @var WPOS_Event_Write_Before $event */

        // 1. Check entity-level permission
        $granted = $this->permissions->can(
            $event->entity_set, $event->operation, $event->user, $event->key
        );
        if ( ! $granted ) {
            $event->cancelled = true;
            return;
        }

        // 2. Validate field-level write permission
        if ( $event->operation !== 'delete' ) {
            $this->field_acl->validate_write(
                $event->payload, $event->entity_set, $event->user, $event->operation
            );
        }

        // 3. Apply WP filter to allow payload modification
        $hook = match( $event->operation ) {
            'insert' => 'wpos_before_insert',
            'update' => 'wpos_before_update',
            default  => null,
        };

        if ( $hook ) {
            $context = $event->operation === 'update'
                ? [ $event->entity_set, $event->key, $event->user ]
                : [ $event->entity_set, $event->user ];

            $event->payload = $this->bridge->filter( $hook, $event->payload, $context );
        }
    }
}
