<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thrown when the OData $filter expression cannot be parsed.
 *
 * Carries the byte-offset position inside the expression where the error
 * was detected so callers can produce useful diagnostic messages.
 */
class WPOS_Filter_Parse_Exception extends \InvalidArgumentException {
    /**
     * @param string $message    Human-readable description of the parse error.
     * @param int    $position   Zero-based offset into $expression where the
     *                           error was detected (or -1 if not applicable).
     * @param string $expression The original filter string being parsed.
     */
    public function __construct(
        string $message,
        public readonly int    $position,
        public readonly string $expression,
    ) {
        parent::__construct( $message );
    }
}
