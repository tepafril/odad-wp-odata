<?php
/**
 * Event fired before a deep update operation.
 *
 * @package WPOS
 */

class ODAD_Event_Deep_Update_Before implements ODAD_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $entity_set,
        public mixed    $key,
        public \WP_User $user,
        public array    $payload,        // full delta payload, mutable
    ) {}
}
