<?php
/**
 * Event fired after an OData query has been executed.
 *
 * @package WPOS
 */

class ODAD_Event_Query_After implements ODAD_Event {
    public function __construct(
        public string             $entity_set,
        public \WP_User           $user,
        public ODAD_Query_Context $query_context,
        public array              $results,          // mutable
    ) {}
}
