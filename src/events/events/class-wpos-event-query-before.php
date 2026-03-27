<?php
/**
 * Event fired before an OData query is executed.
 *
 * @package WPOS
 */

class ODAD_Event_Query_Before implements ODAD_Event {
    public function __construct(
        public string             $entity_set,
        public \WP_User           $user,
        public ODAD_Query_Context $query_context,   // mutable
    ) {}
}
