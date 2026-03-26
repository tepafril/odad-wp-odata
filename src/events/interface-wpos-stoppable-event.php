<?php
/**
 * Interface for events that support propagation stopping.
 *
 * @package WPOS
 */

interface WPOS_Stoppable_Event extends WPOS_Event {
    public function is_stopped(): bool;
    public function stop_propagation(): void;
}
