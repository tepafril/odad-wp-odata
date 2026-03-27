<?php
/**
 * Event fired when an entity configuration is saved in the admin.
 *
 * @package ODAD
 */

class ODAD_Event_Admin_Entity_Config_Saved implements ODAD_Event {
    public function __construct(
        public string $entity_set,
        public array  $config,
    ) {}
}
