<?php
/**
 * Event fired when the schema has changed.
 *
 * @package ODAD
 */

class ODAD_Event_Schema_Changed implements ODAD_Event {
    // $reason: 'entity_registered' | 'config_updated' | 'entity_removed'
    public function __construct(
        public string $reason,
        public string $entity_set,
    ) {}
}
