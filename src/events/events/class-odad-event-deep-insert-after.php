<?php
/**
 * Event fired after a deep insert operation completes.
 *
 * @package ODAD
 */

class ODAD_Event_Deep_Insert_After implements ODAD_Event {
    public function __construct(
        public string   $entity_set,
        public \WP_User $user,
        public mixed    $key,
        public array    $result,
    ) {}
}
