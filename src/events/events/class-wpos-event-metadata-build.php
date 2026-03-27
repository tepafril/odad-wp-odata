<?php
/**
 * Event fired when OData metadata is being built.
 *
 * @package WPOS
 */

class ODAD_Event_Metadata_Build implements ODAD_Event {
    public function __construct(
        public array $entity_types,   // mutable
        public array $entity_sets,    // mutable
    ) {}
}
