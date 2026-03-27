<?php
/**
 * Permission Engine — executes capability checks and injects row-level security.
 *
 * Two-layer permission model:
 *   1. Entity-level: can the user perform the operation at all?
 *      → Answered by can() via ODAD_Capability_Map + WP_User::has_cap().
 *   2. Row-level: which rows may the user see?
 *      → Answered by apply_row_filter(), which adds WHERE fragments to
 *        ODAD_Query_Context::extra_conditions.
 *
 * Pure domain service — no WP hook calls (apply_filters, add_filter).
 * WP filter overrides are the responsibility of ODAD_Subscriber_Permission_Check.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Permission_Engine {

    public function __construct(
        private readonly ODAD_Capability_Map $capability_map,
    ) {}

    // -------------------------------------------------------------------------
    // Entity-level checks
    // -------------------------------------------------------------------------

    /**
     * Check whether a user can perform an operation on an entity set.
     *
     * Uses WP_User::has_cap() — never current_user_can().
     * Does NOT call apply_filters — that is the subscriber's responsibility.
     *
     * @param string   $entity_set OData entity-set name (e.g. 'Posts').
     * @param string   $operation  One of: read, insert, update, delete.
     * @param \WP_User $user       The user to check.
     * @param mixed    $key        Optional primary-key value (reserved for future
     *                             object-level capability checks).
     * @return bool
     */
    public function can( string $entity_set, string $operation, \WP_User $user, mixed $key = null ): bool {
        // Admin-configured role overrides take precedence over WP capability checks.
        $override = $this->capability_map->check_role_override( $entity_set, $operation, $user );
        if ( $override !== null ) {
            return $override;
        }

        $capability = $this->capability_map->get_capability( $entity_set, $operation );

        return $user->has_cap( $capability );
    }

    /**
     * Convenience wrapper: check read permission.
     */
    public function can_read( string $entity_set, \WP_User $user ): bool {
        return $this->can( $entity_set, 'read', $user );
    }

    /**
     * Convenience wrapper: check insert permission.
     */
    public function can_insert( string $entity_set, \WP_User $user ): bool {
        return $this->can( $entity_set, 'insert', $user );
    }

    /**
     * Convenience wrapper: check update permission.
     *
     * @param mixed $key Optional primary-key value for object-level checks.
     */
    public function can_update( string $entity_set, \WP_User $user, mixed $key = null ): bool {
        return $this->can( $entity_set, 'update', $user, $key );
    }

    /**
     * Convenience wrapper: check delete permission.
     *
     * @param mixed $key Optional primary-key value for object-level checks.
     */
    public function can_delete( string $entity_set, \WP_User $user, mixed $key = null ): bool {
        return $this->can( $entity_set, 'delete', $user, $key );
    }

    // -------------------------------------------------------------------------
    // Row-level security
    // -------------------------------------------------------------------------

    /**
     * Inject row-level security filters into the query context.
     *
     * Modifies $ctx->extra_conditions with SQL fragments that limit which rows
     * the user may see. Admin users (has_cap('administrator')) receive no
     * additional restrictions.
     *
     * Built-in rules:
     *   Posts / Pages  — non-admin: post_status = 'publish' OR post_author = {user_id}
     *   Comments       — non-admin: comment_approved = 1    OR user_id = {user_id}
     *   Users          — no row filter (entity-level capability gates access)
     *   Everything else — no row filter
     *
     * @param string             $entity_set OData entity-set name.
     * @param \WP_User           $user       The requesting user.
     * @param ODAD_Query_Context $ctx        Mutable query context.
     * @return ODAD_Query_Context The (possibly modified) query context.
     */
    public function apply_row_filter(
        string             $entity_set,
        \WP_User           $user,
        ODAD_Query_Context $ctx
    ): ODAD_Query_Context {
        // Administrators see everything — no row-level restrictions.
        if ( $user->has_cap( 'administrator' ) ) {
            return $ctx;
        }

        $user_id = (int) $user->ID;

        switch ( $entity_set ) {
            case 'Posts':
            case 'Pages':
                // Non-admin may see published posts, or posts they authored.
                $ctx->extra_conditions[] = sprintf(
                    "(post_status IN ('publish') OR post_author = %d)",
                    $user_id
                );
                break;

            case 'Comments':
                // Non-admin may see approved comments, or comments they submitted.
                $ctx->extra_conditions[] = sprintf(
                    '(comment_approved = 1 OR user_id = %d)',
                    $user_id
                );
                break;

            // Users, Categories, Tags, Attachments, and custom entity sets:
            // no built-in row filter — entity-level capability already gates access.
            default:
                break;
        }

        return $ctx;
    }
}
