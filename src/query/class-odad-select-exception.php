<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thrown when an unknown OData property is referenced in a $select clause.
 */
class ODAD_Select_Exception extends InvalidArgumentException {}
