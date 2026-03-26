<?php
/**
 * Event fired during a permission check, allowing listeners to override the result.
 *
 * @package WPOS
 */

class WPOS_Event_Permission_Check implements WPOS_Event {
    public function __construct(
        public string   $entity_set,
        public string   $operation,   // 'read' | 'insert' | 'update' | 'delete'
        public \WP_User $user,
        public bool     $granted,     // initial result from capability map, mutable
        public mixed    $key = null,
    ) {}
}
