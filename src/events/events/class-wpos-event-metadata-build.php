<?php
/**
 * Event fired when OData metadata is being built.
 *
 * @package WPOS
 */

class WPOS_Event_Metadata_Build implements WPOS_Event {
    public function __construct(
        public array $entity_types,   // mutable
        public array $entity_sets,    // mutable
    ) {}
}
