<?php
/**
 * ODAD_Query_Result — immutable value object returned by ODAD_Query_Engine.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Query_Result {

    /**
     * @param array       $rows        Array of entity row arrays (OData property names as keys).
     * @param int|null    $total_count Total matching row count (only when $count=true was requested).
     * @param string|null $next_link   @odata.nextLink URL for server-driven pagination; null if no next page.
     */
    public function __construct(
        public readonly array   $rows,
        public readonly ?int    $total_count = null,
        public readonly ?string $next_link   = null,
    ) {}
}
