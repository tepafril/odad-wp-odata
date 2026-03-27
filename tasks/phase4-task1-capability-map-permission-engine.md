# Task 4.1 — Capability Map + Permission Engine

## Dependencies
- Task 1.2 (ODAD_Event_Permission_Check event)
- Task 2.1 (ODAD_Query_Context)

## Goal
Build the two-layer permission system:
- `ODAD_Capability_Map` — maps entity sets to WordPress capabilities per operation
- `ODAD_Permission_Engine` — executes permission checks and injects row-level security

Both are pure PHP domain services. No WP hook calls here — those happen in subscribers.

---

## File 1: `src/permissions/class-odad-capability-map.php`

```php
class ODAD_Capability_Map {

    /** Default capability map for built-in entity sets */
    private array $defaults = [
        'Posts'       => [ 'read' => 'read',         'insert' => 'edit_posts',    'update' => 'edit_posts',    'delete' => 'delete_posts' ],
        'Pages'       => [ 'read' => 'read',         'insert' => 'edit_pages',    'update' => 'edit_pages',    'delete' => 'delete_pages' ],
        'Users'       => [ 'read' => 'list_users',   'insert' => 'create_users',  'update' => 'edit_users',    'delete' => 'delete_users' ],
        'Categories'  => [ 'read' => 'read',         'insert' => 'manage_categories', 'update' => 'manage_categories', 'delete' => 'manage_categories' ],
        'Tags'        => [ 'read' => 'read',         'insert' => 'manage_categories', 'update' => 'manage_categories', 'delete' => 'manage_categories' ],
        'Comments'    => [ 'read' => 'read',         'insert' => 'read',          'update' => 'edit_comment',  'delete' => 'delete_comment' ],
        'Attachments' => [ 'read' => 'read',         'insert' => 'upload_files',  'update' => 'upload_files',  'delete' => 'delete_posts' ],
    ];

    /** Custom capability map for CPT/custom tables */
    private array $custom = [];

    /**
     * Register capability rules for a custom entity set.
     * Called from the ODAD_register_permissions WP action.
     */
    public function register( string $entity_set, array $operations ): void;

    /**
     * Get the required capability for an entity set + operation.
     * Falls back to custom convention: ODAD_{entity_set_lower}_{operation}
     */
    public function get_capability( string $entity_set, string $operation ): string;
}
```

**Custom capability convention (for unregistered entity sets):**
```
ODAD_{entity_set_lowercase}_{operation}
e.g.  ODAD_employees_read
      ODAD_employees_insert
      ODAD_salary_read    ← field-level
```

---

## File 2: `src/permissions/class-odad-permission-engine.php`

```php
class ODAD_Permission_Engine {

    public function __construct(
        private ODAD_Capability_Map $capability_map,
    ) {}

    /**
     * Check if the user can perform the operation on the entity set.
     * Returns true/false based on WP capabilities.
     * Does NOT call apply_filters — that's the subscriber's job.
     */
    public function can( string $entity_set, string $operation, \WP_User $user, mixed $key = null ): bool;

    /**
     * Convenience wrappers
     */
    public function can_read( string $entity_set, \WP_User $user ): bool;
    public function can_insert( string $entity_set, \WP_User $user ): bool;
    public function can_update( string $entity_set, \WP_User $user, mixed $key = null ): bool;
    public function can_delete( string $entity_set, \WP_User $user, mixed $key = null ): bool;

    /**
     * Inject row-level security filters into the query context.
     * Example: non-admin users can only see their own posts.
     *
     * Modifies $ctx->extra_conditions to limit rows to what user can see.
     */
    public function apply_row_filter(
        string             $entity_set,
        \WP_User           $user,
        ODAD_Query_Context $ctx
    ): ODAD_Query_Context;
}
```

### Row-Level Security Rules (built-in)

| Entity Set | Rule for non-admin |
|---|---|
| `Posts` | `post_status IN ('publish')` OR `post_author = {user_id}` |
| `Pages` | Same as Posts |
| `Users` | No row filter — entity-level capability `list_users` gates access |
| `Comments` | `comment_approved = 1` OR `user_id = {user_id}` |

For CPT and custom tables: no built-in row filter (all rows visible if entity-level permission passes).

---

## Permission Request Flow

```
can() implementation:
  1. Get required capability from capability_map->get_capability()
  2. Check $user->has_cap($capability)
  3. Return result
  Note: The ODAD_can_* WP filter override happens in ODAD_Subscriber_Permission_Check
```

---

## Acceptance Criteria

- `can_read('Posts', $admin_user)` returns `true` (admin has `read`).
- `can_insert('Posts', $subscriber_user)` returns `false` (subscriber lacks `edit_posts`).
- `can_read('Employees', $user)` uses convention `ODAD_employees_read`.
- `apply_row_filter('Posts', $non_admin)` adds `extra_conditions` limiting to `publish` posts or own posts.
- `apply_row_filter('Posts', $admin)` adds no extra conditions.
- No WordPress hook calls (`apply_filters`, `add_filter`) anywhere in these files.
- Uses `$user->has_cap()` for capability checks (not `current_user_can()`).
