# Task 2.3 — WPOS_Adapter_WP_Users

## Dependencies
- Task 2.1 (adapter interface + resolver)

## Goal
Implement the WordPress Users adapter. This is a **security-sensitive** adapter:
`user_pass` must NEVER be exposed. `user_email` and `user_login` require `list_users`
capability. The adapter queries `wp_users` joined with `wp_usermeta` as needed.

---

## File

### `src/adapters/class-wpos-adapter-wp-users.php`

```php
class WPOS_Adapter_WP_Users implements WPOS_Adapter {
    // Entity set name: 'Users'
}
```

---

## Property Map

| OData Property | wp_users column | Edm Type | Visibility |
|---|---|---|---|
| `ID` | `ID` | `Edm.Int32` | Always visible |
| `DisplayName` | `display_name` | `Edm.String` | Always visible |
| `RegisteredDate` | `user_registered` | `Edm.DateTimeOffset` | Always visible |
| `Login` | `user_login` | `Edm.String` | Requires `list_users` capability |
| `Email` | `user_email` | `Edm.String` | Requires `list_users` capability |
| `Url` | `user_url` | `Edm.String` | Always visible |
| `NiceName` | `user_nicename` | `Edm.String` | Always visible |
| `Status` | `user_status` | `Edm.Int32` | Always visible |

**`user_pass` is PERMANENTLY excluded. Never return it under any circumstance.**

---

## Navigation Properties

| OData Nav Property | Target | Cardinality | How resolved |
|---|---|---|---|
| `Posts` | `Posts` | `*` | `wp_posts.post_author` |
| `Meta` | `UserMeta` | `*` | `wp_usermeta.user_id` |

---

## PII / Security Rules

1. Never select or return `user_pass` from any query.
2. `Login` and `Email` properties must only be included in results when the requesting
   user has `list_users` capability. The adapter itself does NOT perform this check —
   the `WPOS_Field_ACL` layer (Phase 4) strips them. However, the entity type definition
   must annotate these fields as requiring `list_users`.
3. In `get_entity_type_definition()`, mark `Login` and `Email` with:
   ```php
   'required_capability' => 'list_users'
   ```

---

## Implementation Notes

### `get_collection()`
Use `$wpdb->get_results()` on `wp_users`. Never select `user_pass`.
Sort by `ID` by default.

### `insert()`
Use `wp_insert_user()`. Map OData property names to the user data array.
Return the new user ID.

### `update()`
Use `wp_update_user()`. Return `true` on success.

### `delete()`
Use `wp_delete_user( $key )`. Return `true` on success.

---

## Bootstrapper Update

```php
$c->singleton( WPOS_Adapter_WP_Users::class, fn() => new WPOS_Adapter_WP_Users() );
```

---

## Acceptance Criteria

- `get_entity_type_definition()` contains exactly 8 properties (NOT `user_pass`).
- `user_pass` is never present in any array returned by any method.
- `Login` and `Email` property definitions include `'required_capability' => 'list_users'`.
- `insert()` uses `wp_insert_user()`, not raw `$wpdb`.
- `update()` uses `wp_update_user()`.
- `delete()` uses `wp_delete_user()`.
