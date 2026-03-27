<?php
/**
 * Event fired when schema registration is triggered.
 *
 * @package WPOS
 */

class ODAD_Event_Schema_Register implements ODAD_Event {
    public function __construct(
        public ODAD_Schema_Registry $registry,
    ) {}
}
