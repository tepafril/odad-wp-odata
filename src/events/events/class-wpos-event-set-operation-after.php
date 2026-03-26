<?php
/**
 * Event fired after a set-based operation completes.
 *
 * @package WPOS
 */

class WPOS_Event_Set_Operation_After implements WPOS_Event {
    public function __construct(
        public string   $entity_set,
        public string   $operation,
        public \WP_User $user,
        public int      $affected_count,
    ) {}
}
