<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thrown when an unknown OData property is referenced in an $orderby clause.
 */
class WPOS_Orderby_Exception extends InvalidArgumentException {}
