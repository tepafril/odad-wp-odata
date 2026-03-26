<?php
/**
 * Event fired after an OData query has been executed.
 *
 * @package WPOS
 */

class WPOS_Event_Query_After implements WPOS_Event {
    public function __construct(
        public string             $entity_set,
        public \WP_User           $user,
        public WPOS_Query_Context $query_context,
        public array              $results,          // mutable
    ) {}
}
