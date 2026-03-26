<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thrown when the AST produced by WPOS_Filter_Parser cannot be compiled to SQL.
 *
 * The most common cause is a property path that is not present in the
 * column_map supplied to WPOS_Filter_Compiler::compile(), which would
 * otherwise allow SQL injection via a crafted property name.
 */
class WPOS_Filter_Compile_Exception extends \InvalidArgumentException {
    /**
     * @param string $message        Human-readable description of the error.
     * @param string $property_name  The offending OData property name, or ''
     *                               when the error is not property-specific.
     */
    public function __construct(
        string $message,
        public readonly string $property_name = '',
    ) {
        parent::__construct( $message );
    }
}
