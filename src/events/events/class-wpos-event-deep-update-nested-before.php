<?php
/**
 * Event fired before each nested entity is processed during a deep update.
 *
 * @package WPOS
 */

class ODAD_Event_Deep_Update_Nested_Before implements ODAD_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $parent_entity_set,
        public string   $nested_entity_set,
        public string   $operation,        // 'insert' | 'update' | 'delete'
        public \WP_User $user,
        public array    $nested_payload,   // mutable
        public mixed    $nested_key = null,
    ) {}
}
