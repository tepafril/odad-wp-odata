# Task 2.2 — ODAD_Adapter_WP_Posts

## Dependencies
- Task 2.1 (adapter interface + resolver + query context stub)

## Goal
Implement the WordPress Posts adapter. This adapter handles `wp_posts` data for:
- `Posts` (post_type = 'post')
- `Pages` (post_type = 'page')
- `Attachments` (post_type = 'attachment')
- `Comments` (via `wp_comments` + `wp_commentmeta`)

Each entity set is a separate configured instance of the same adapter class.

---

## File

### `src/adapters/class-odad-adapter-wp-posts.php`

```php
class ODAD_Adapter_WP_Posts implements ODAD_Adapter {

    public function __construct(
        private string  $post_type,        // 'post' | 'page' | 'attachment'
        private string  $entity_set_name,  // 'Posts' | 'Pages' | 'Attachments'
    ) {}
}
```

---

## Property Map

The adapter must map these OData properties to `wp_posts` columns:

| OData Property | wp_posts column | Edm Type | Notes |
|---|---|---|---|
| `ID` | `ID` | `Edm.Int32` | Key, not nullable |
| `Title` | `post_title` | `Edm.String` | |
| `Content` | `post_content` | `Edm.String` | |
| `Excerpt` | `post_excerpt` | `Edm.String` | |
| `Status` | `post_status` | `Edm.String` | 'publish','draft','private',etc. |
| `Slug` | `post_name` | `Edm.String` | |
| `PublishedDate` | `post_date_gmt` | `Edm.DateTimeOffset` | |
| `ModifiedDate` | `post_modified_gmt` | `Edm.DateTimeOffset` | |
| `AuthorID` | `post_author` | `Edm.Int32` | FK → Users |
| `ParentID` | `post_parent` | `Edm.Int32` | nullable |
| `MenuOrder` | `menu_order` | `Edm.Int32` | |
| `CommentCount` | `comment_count` | `Edm.Int32` | read-only |
| `Type` | `post_type` | `Edm.String` | |
| `GUID` | `guid` | `Edm.String` | |

---

## Navigation Properties

| OData Nav Property | Target Entity Set | Cardinality | How resolved |
|---|---|---|---|
| `Author` | `Users` | `0..1` | `post_author` → `wp_users.ID` |
| `Tags` | `Tags` | `*` | `wp_term_relationships` |
| `Categories` | `Categories` | `*` | `wp_term_relationships` |
| `Meta` | `PostMeta` | `*` | `wp_postmeta.post_id` |
| `Comments` | `Comments` | `*` | `wp_comments.comment_post_ID` |

(Full navigation expansion is implemented in Phase 3 `ODAD_Expand_Compiler`.)

---

## Implementation Notes

### `get_collection()`
Use `$wpdb->get_results()` with a `SELECT` built from `ODAD_Query_Context`.
At Phase 2, apply only `$top`, `$skip`, `post_type`, and `post_status != 'auto-draft'` filters.
Return rows as associative arrays with OData property names (not raw column names).

### `insert()`
Use `wp_insert_post()` for data integrity (fires WP hooks, runs sanitization).
Accept OData property names in `$data`, map to `wp_insert_post()` argument array.
Return the new `post_ID` on success. Throw on failure.

### `update()`
Use `wp_update_post()`. Map OData property names to post array. Return `true` on success.

### `delete()`
Use `wp_delete_post( $key, true )` (force delete bypasses trash).
Return `true` on success.

### `get_entity_type_definition()`
Return the full property map for the schema registry.

---

## Bootstrapper Update

Register three instances in `ODAD_Bootstrapper`:
```php
$c->singleton( 'adapter.posts', fn() => new ODAD_Adapter_WP_Posts('post',       'Posts') );
$c->singleton( 'adapter.pages', fn() => new ODAD_Adapter_WP_Posts('page',       'Pages') );
$c->singleton( 'adapter.attachments', fn() => new ODAD_Adapter_WP_Posts('attachment', 'Attachments') );
```

These are later registered into `ODAD_Adapter_Resolver` by the Schema Init subscriber (Task 2.6).

---

## Acceptance Criteria

- `get_entity_set_name()` returns `'Posts'`, `'Pages'`, or `'Attachments'` depending on construction.
- `get_entity_type_definition()` returns all 14 properties with correct Edm types.
- `get_collection()` returns an array of rows with OData property names (not raw DB column names).
- `insert()` uses `wp_insert_post()`, not raw `$wpdb`.
- `update()` uses `wp_update_post()`.
- `delete()` uses `wp_delete_post( $key, true )`.
- `get_count()` returns the correct integer count (no `$top`/`$skip` applied).
- No raw string interpolation into SQL — all values go through `$wpdb->prepare()` or WP API.
