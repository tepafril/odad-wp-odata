<?php
/**
 * Event fired after a standard write operation completes.
 *
 * @package WPOS
 */

class WPOS_Event_Write_After implements WPOS_Event {
    public function __construct(
        public string   $entity_set,
        public string   $operation,
        public \WP_User $user,
        public mixed    $key,
        public array    $result,
    ) {}
}
