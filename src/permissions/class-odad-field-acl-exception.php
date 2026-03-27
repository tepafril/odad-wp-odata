<?php
/**
 * ODAD_Field_ACL_Exception — thrown when a write payload contains forbidden fields.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Field_ACL_Exception extends \RuntimeException {

    /**
     * @param string   $message
     * @param string   $entity_set      The entity set name (e.g. 'Posts', 'Users').
     * @param string[] $forbidden_fields The list of field names that caused the violation.
     */
    public function __construct(
        string $message,
        public readonly string $entity_set,
        public readonly array $forbidden_fields,
    ) {
        parent::__construct( $message );
    }
}
