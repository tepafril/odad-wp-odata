<?php
/**
 * Event fired after a standard write operation completes.
 *
 * @package ODAD
 */

class ODAD_Event_Write_After implements ODAD_Event {
    public function __construct(
        public string   $entity_set,
        public string   $operation,
        public \WP_User $user,
        public mixed    $key,
        public array    $result,
    ) {}
}
