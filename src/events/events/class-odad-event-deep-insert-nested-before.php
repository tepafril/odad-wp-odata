<?php
/**
 * Event fired before each nested entity is inserted during a deep insert.
 *
 * @package ODAD
 */

class ODAD_Event_Deep_Insert_Nested_Before implements ODAD_Event {
    public bool $cancelled = false;

    public function __construct(
        public string   $parent_entity_set,
        public string   $nested_entity_set,
        public string   $nav_property,
        public \WP_User $user,
        public array    $nested_payload,  // mutable
    ) {}
}
