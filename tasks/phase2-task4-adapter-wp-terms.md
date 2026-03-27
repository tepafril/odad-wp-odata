# Task 2.4 — ODAD_Adapter_WP_Terms

## Dependencies
- Task 2.1 (adapter interface + resolver)

## Goal
Implement the WordPress Terms adapter for `wp_terms` + `wp_term_taxonomy`.
Handles `Categories` (taxonomy = 'category') and `Tags` (taxonomy = 'post_tag').
Each taxonomy is a separate configured instance.

---

## File

### `src/adapters/class-wpos-adapter-wp-terms.php`

```php
class ODAD_Adapter_WP_Terms implements ODAD_Adapter {

    public function __construct(
        private string $taxonomy,         // 'category' | 'post_tag'
        private string $entity_set_name,  // 'Categories' | 'Tags'
    ) {}
}
```

---

## Property Map

Join `wp_terms` + `wp_term_taxonomy` on `term_id`.

| OData Property | Source column | Edm Type |
|---|---|---|
| `ID` | `wp_terms.term_id` | `Edm.Int32` (Key) |
| `Name` | `wp_terms.name` | `Edm.String` |
| `Slug` | `wp_terms.slug` | `Edm.String` |
| `Description` | `wp_term_taxonomy.description` | `Edm.String` |
| `Count` | `wp_term_taxonomy.count` | `Edm.Int32` (read-only) |
| `ParentID` | `wp_term_taxonomy.parent` | `Edm.Int32` (nullable) |
| `Taxonomy` | `wp_term_taxonomy.taxonomy` | `Edm.String` |

---

## Navigation Properties

| OData Nav Property | Target | Cardinality |
|---|---|---|
| `Posts` | `Posts` | `*` (via `wp_term_relationships`) |
| `Parent` | Same entity set | `0..1` |
| `Children` | Same entity set | `*` |

---

## Implementation Notes

### `get_collection()`
Query:
```sql
SELECT t.term_id, t.name, t.slug, tt.description, tt.count, tt.parent, tt.taxonomy
FROM wp_terms t
JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
WHERE tt.taxonomy = %s
```
Apply `$top` / `$skip`. Return rows with OData property names.

### `insert()`
Use `wp_insert_term( $name, $taxonomy, $args )`. Return the new term ID.

### `update()`
Use `wp_update_term( $term_id, $taxonomy, $args )`. Return `true` on success.

### `delete()`
Use `wp_delete_term( $term_id, $taxonomy )`. Return `true` on success.

---

## Bootstrapper Update

```php
$c->singleton( 'adapter.categories', fn() => new ODAD_Adapter_WP_Terms('category', 'Categories') );
$c->singleton( 'adapter.tags',       fn() => new ODAD_Adapter_WP_Terms('post_tag',  'Tags') );
```

---

## Acceptance Criteria

- `get_entity_set_name()` returns `'Categories'` or `'Tags'` depending on construction.
- `get_collection()` only returns terms for the configured taxonomy.
- `insert()` uses `wp_insert_term()`.
- `update()` uses `wp_update_term()`.
- `delete()` uses `wp_delete_term()`.
- All SQL uses `$wpdb->prepare()` for the taxonomy parameter.
