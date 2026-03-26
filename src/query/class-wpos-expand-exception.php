<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thrown when an unknown or malformed navigation property is referenced
 * in an OData $expand clause.
 */
class WPOS_Expand_Exception extends InvalidArgumentException {}
