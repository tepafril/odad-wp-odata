<?php
/**
 * Event fired when schema registration is triggered.
 *
 * @package WPOS
 */

class WPOS_Event_Schema_Register implements WPOS_Event {
    public function __construct(
        public WPOS_Schema_Registry $registry,
    ) {}
}
