<?php
/**
 * Subscriber: Query Before — fires before an OData query is executed.
 *
 * Responsibilities:
 *   1. Row-level security: inject WHERE conditions into the query context via
 *      ODAD_Permission_Engine::apply_row_filter() (Phase 4).
 *   2. Expose the public ODAD_query_context WP filter so external plugins can
 *      modify the query context before it reaches the adapter.
 *
 * Phase 3 stub behaviour:
 *   If $permissions is null (not yet wired) the permission call is skipped and
 *   only the ODAD_query_context WP filter is applied.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Query_Before implements ODAD_Event_Listener {

    /**
     * @param object|null    $permissions ODAD_Permission_Engine instance, or null in Phase 3.
     * @param ODAD_Hook_Bridge $bridge    Hook bridge for firing the ODAD_query_context filter.
     */
    public function __construct(
        private readonly mixed          $permissions,
        private readonly ODAD_Hook_Bridge $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Query_Before::class;
    }

    public function handle( ODAD_Event $event ): void {
        /** @var ODAD_Event_Query_Before $event */

        $ctx = $event->query_context;

        // ------------------------------------------------------------------
        // 1. Row-level security — inject WHERE conditions.
        //    Skipped in Phase 3 when ODAD_Permission_Engine is not yet wired.
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
            'ODAD_query_context',
            $ctx,
            [ $event->entity_set, $event->user ]
        );

        // ------------------------------------------------------------------
        // 3. Write modified context back to event.
        // ------------------------------------------------------------------
        $event->query_context = $ctx;
    }
}
