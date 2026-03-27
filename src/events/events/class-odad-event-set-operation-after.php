<?php
/**
 * Event fired after a set-based operation completes.
 *
 * @package ODAD
 */

class ODAD_Event_Set_Operation_After implements ODAD_Event {
    public function __construct(
        public string   $entity_set,
        public string   $operation,
        public \WP_User $user,
        public int      $affected_count,
    ) {}
}
