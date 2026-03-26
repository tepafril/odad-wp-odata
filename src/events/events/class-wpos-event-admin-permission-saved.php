<?php
/**
 * Event fired when permission settings are saved in the admin.
 *
 * @package WPOS
 */

class WPOS_Event_Admin_Permission_Saved implements WPOS_Event {
    public function __construct(
        public string $entity_set,
        public array  $permissions,
    ) {}
}
