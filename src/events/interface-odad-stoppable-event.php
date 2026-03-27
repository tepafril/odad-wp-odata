<?php
/**
 * Interface for events that support propagation stopping.
 *
 * @package ODAD
 */

interface ODAD_Stoppable_Event extends ODAD_Event {
    public function is_stopped(): bool;
    public function stop_propagation(): void;
}
