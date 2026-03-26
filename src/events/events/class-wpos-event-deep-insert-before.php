<?php
/**
 * Event fired before a deep insert operation.
 *
 * @package WPOS
 */

class WPOS_Event_Deep_Insert_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $entity_set,
        public \WP_User $user,
        public array    $payload,        // full nested payload, mutable
    ) {}
}
