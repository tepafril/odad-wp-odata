<?php
/**
 * Event fired before a set-based operation.
 *
 * @package WPOS
 */

class ODAD_Event_Set_Operation_Before implements ODAD_Event {
    public bool $cancelled = false;

    public function __construct(
        public string             $entity_set,
        public string             $operation,     // 'patch' | 'delete' | 'action'
        public \WP_User           $user,
        public ODAD_Query_Context $filter_ctx,    // mutable
        public array              $payload,       // mutable
    ) {}
}
