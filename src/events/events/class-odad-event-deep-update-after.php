<?php
/**
 * Event fired after a deep update operation completes.
 *
 * @package ODAD
 */

class ODAD_Event_Deep_Update_After implements ODAD_Event {
    public function __construct(
        public string   $entity_set,
        public mixed    $key,
        public \WP_User $user,
        public array    $result,
    ) {}
}
