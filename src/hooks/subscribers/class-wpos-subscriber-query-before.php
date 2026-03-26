<?php
/**
 * Subscriber: Query Before — fires before an OData query is executed.
 *
 * Responsibilities:
 *   1. Row-level security: inject WHERE conditions into the query context via
 *      WPOS_Permission_Engine::apply_row_filter() (Phase 4).
 *   2. Expose the public wpos_query_context WP filter so external plugins can
 *      modify the query context before it reaches the adapter.
 *
 * Phase 3 stub behaviour:
 *   If $permissions is null (not yet wired) the permission call is skipped and
 *   only the wpos_query_context WP filter is applied.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Subscriber_Query_Before implements WPOS_Event_Listener {

    /**
     * @param object|null    $permissions WPOS_Permission_Engine instance, or null in Phase 3.
     * @param WPOS_Hook_Bridge $bridge    Hook bridge for firing the wpos_query_context filter.
     */
    public function __construct(
        private readonly mixed          $permissions,
        private readonly WPOS_Hook_Bridge $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Query_Before::class;
    }

    public function handle( WPOS_Event $event ): void {
        /** @var WPOS_Event_Query_Before $event */

        $ctx = $event->query_context;

        // ------------------------------------------------------------------
        // 1. Row-level security — inject WHERE conditions.
        //    Skipped in Phase 3 when WPOS_Permission_Engine is not yet wired.
        // ------------------------------------------------------------------
        if ( null !== $this->permissions && method_exists( $this->permissions, 'apply_row_filter' ) ) {
            $ctx = $this->permissions->apply_row_filter(
                $event->entity_set,
                $event->user,
                $ctx
            );
        }

        // ------------------------------------------------------------------
        // 2. Public WP filter — external plugins can further modify the
        //    query context before it is handed to the adapter.
        // ------------------------------------------------------------------
        $ctx = $this->bridge->filter(
            'wpos_query_context',
            $ctx,
            [ $event->entity_set, $event->user ]
        );

        // ------------------------------------------------------------------
        // 3. Write modified context back to event.
        // ------------------------------------------------------------------
        $event->query_context = $ctx;
    }
}
