<?php
/**
 * Subscriber: Query After — fires after an OData query has completed.
 *
 * Responsibilities:
 *   1. Field ACL stripping: remove fields the current user is not permitted
 *      to see via ODAD_Field_ACL::apply() (Phase 4).
 *   2. Expose the public ODAD_query_results WP filter so external plugins can
 *      inspect or modify the final result set.
 *
 * Phase 3 stub behaviour:
 *   If $field_acl is null (not yet wired) the ACL stripping call is skipped
 *   and only the ODAD_query_results WP filter is applied.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Query_After implements ODAD_Event_Listener {

    /**
     * @param object|null    $field_acl ODAD_Field_ACL instance, or null in Phase 3.
     * @param ODAD_Hook_Bridge $bridge  Hook bridge for firing the ODAD_query_results filter.
     */
    public function __construct(
        private readonly mixed          $field_acl,
        private readonly ODAD_Hook_Bridge $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Query_After::class;
    }

    public function handle( ODAD_Event $event ): void {
        /** @var ODAD_Event_Query_After $event */

        $results = $event->results;

        // ------------------------------------------------------------------
        // 1. Field ACL stripping — remove fields the user may not see.
        //    Skipped in Phase 3 when ODAD_Field_ACL is not yet wired.
        // ------------------------------------------------------------------
        if ( null !== $this->field_acl && method_exists( $this->field_acl, 'apply' ) ) {
            $results = $this->field_acl->apply(
                $results,
                $event->entity_set,
                $event->user,
                'read'
            );
        }

        // ------------------------------------------------------------------
        // 2. Public WP filter — external plugins can modify the final results.
        //    Field ACL stripping runs first so plugins see already-stripped data.
        // ------------------------------------------------------------------
        $results = $this->bridge->filter(
            'ODAD_query_results',
            $results,
            [ $event->entity_set, $event->user ]
        );

        // ------------------------------------------------------------------
        // 3. Write modified results back to event.
        // ------------------------------------------------------------------
        $event->results = $results;
    }
}
