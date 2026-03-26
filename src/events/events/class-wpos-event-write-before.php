<?php
/**
 * Event fired before a standard write operation.
 *
 * @package WPOS
 */

class WPOS_Event_Write_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $entity_set,
        public string   $operation,   // 'insert' | 'update' | 'delete'
        public \WP_User $user,
        public array    $payload,     // mutable
        public mixed    $key = null,
    ) {}
}
