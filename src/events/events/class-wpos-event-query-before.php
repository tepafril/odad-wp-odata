<?php
/**
 * Event fired before an OData query is executed.
 *
 * @package WPOS
 */

class WPOS_Event_Query_Before implements WPOS_Event {
    public function __construct(
        public string             $entity_set,
        public \WP_User           $user,
        public WPOS_Query_Context $query_context,   // mutable
    ) {}
}
