<?php
/**
 * Capability Map — maps OData entity sets to WordPress capabilities per operation.
 *
 * Provides:
 *   - Defaults for built-in WordPress entity sets (Posts, Pages, Users, etc.)
 *   - Custom registration for CPTs and custom tables via register()
 *   - Convention-based fallback for unregistered entity sets:
 *     ODAD_{entity_set_lowercase}_{operation}
 *
 * Pure domain service — no WP hook calls.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Capability_Map {

    /**
     * Default capability map for built-in entity sets.
     *
     * @var array<string, array<string, string>>
     */
    private array $defaults = [
        'Posts'       => [ 'read' => 'read',              'insert' => 'edit_posts',         'update' => 'edit_posts',         'delete' => 'delete_posts' ],
        'Pages'       => [ 'read' => 'read',              'insert' => 'edit_pages',         'update' => 'edit_pages',         'delete' => 'delete_pages' ],
        'Users'       => [ 'read' => 'list_users',        'insert' => 'create_users',       'update' => 'edit_users',         'delete' => 'delete_users' ],
        'Categories'  => [ 'read' => 'read',              'insert' => 'manage_categories',  'update' => 'manage_categories',  'delete' => 'manage_categories' ],
        'Tags'        => [ 'read' => 'read',              'insert' => 'manage_categories',  'update' => 'manage_categories',  'delete' => 'manage_categories' ],
        'Comments'    => [ 'read' => 'read',              'insert' => 'read',               'update' => 'edit_comment',       'delete' => 'delete_comment' ],
        'Attachments' => [ 'read' => 'read',              'insert' => 'upload_files',       'update' => 'upload_files',       'delete' => 'delete_posts' ],
    ];

    /**
     * Custom capability map for CPT/custom tables.
     * Populated via register().
     *
     * @var array<string, array<string, string>>
     */
    private array $custom = [];

    /**
     * Admin-configured role overrides: [ entity_set => [ role => [ op => bool ] ] ]
     * When populated, these take precedence over WP capability checks.
     *
     * @var array<string, array<string, array<string, bool>>>
     */
    private array $role_overrides = [];

    /**
     * Register capability rules for a custom entity set.
     *
     * Intended to be called from within a handler for the ODAD_register_permissions
     * WP action (the action itself is fired by a subscriber — not by this class).
     *
     * @param string               $entity_set  The OData entity-set name (e.g. 'Employees').
     * @param array<string,string> $operations  Map of operation => capability string.
     *                                          Supported operations: read, insert, update, delete.
     */
    public function register( string $entity_set, array $operations ): void {
        $this->custom[ $entity_set ] = $operations;
    }

    /**
     * Register admin-configured role overrides for an entity set.
     *
     * Called on plugin init (from ODAD_Subscriber_Schema_Init) to load saved
     * permissions, and immediately after saving from ODAD_Admin_Permission_Config.
     *
     * @param string $entity_set  OData entity-set name.
     * @param array  $role_perms  [ role_slug => [ operation => bool ] ]
     */
    public function register_role_overrides( string $entity_set, array $role_perms ): void {
        $this->role_overrides[ $entity_set ] = $role_perms;
    }

    /**
     * Check whether admin role overrides grant or deny an operation.
     *
     * Resolution:
     *  - If any of the user's roles explicitly grants the operation → true.
     *  - If all matching roles deny it → false.
     *  - If the entity set has no overrides, or none of the user's roles
     *    appear in the overrides → null (fall through to WP capability check).
     *
     * @param string   $entity_set
     * @param string   $operation
     * @param \WP_User $user
     * @return bool|null  true = granted, false = denied, null = no override applies
     */
    public function check_role_override( string $entity_set, string $operation, \WP_User $user ): ?bool {
        if ( ! isset( $this->role_overrides[ $entity_set ] ) ) {
            return null;
        }

        $overrides         = $this->role_overrides[ $entity_set ];
        $has_matching_role = false;

        foreach ( (array) $user->roles as $role ) {
            if ( ! isset( $overrides[ $role ] ) ) {
                continue;
            }
            $has_matching_role = true;
            if ( ! empty( $overrides[ $role ][ $operation ] ) ) {
                return true; // Any granted role is sufficient.
            }
        }

        // All matched roles denied the operation.
        if ( $has_matching_role ) {
            return false;
        }

        return null; // No matching role — fall through to WP capability check.
    }

    /**
     * Get the required capability for an entity set and operation.
     *
     * Resolution order:
     *   1. Custom map (registered via register())
     *   2. Built-in defaults
     *   3. Convention: ODAD_{entity_set_lowercase}_{operation}
     *
     * @param string $entity_set OData entity-set name (e.g. 'Posts', 'Employees').
     * @param string $operation  One of: read, insert, update, delete.
     * @return string WordPress capability string.
     */
    public function get_capability( string $entity_set, string $operation ): string {
        if ( isset( $this->custom[ $entity_set ][ $operation ] ) ) {
            return $this->custom[ $entity_set ][ $operation ];
        }

        if ( isset( $this->defaults[ $entity_set ][ $operation ] ) ) {
            return $this->defaults[ $entity_set ][ $operation ];
        }

        // Convention-based fallback for unregistered entity sets.
        // e.g. entity_set='Employees', operation='read' → 'ODAD_employees_read'
        return 'ODAD_' . strtolower( $entity_set ) . '_' . $operation;
    }
}
