# Task 3.4 — Expand Compiler

## Dependencies
- Task 2.1 (adapter interface — for navigation property resolution)
- Task 3.3 (ODAD_Query_Context updated structure)

## Goal
Implement the `$expand` compiler that resolves navigation properties and
loads related entities. Supports single-level and nested expansion.
E.g. `$expand=Author,Tags` or `$expand=Author($select=DisplayName)`.

---

## File

### `src/query/class-wpos-expand-compiler.php`

```php
class ODAD_Expand_Compiler {

    public function __construct(
        private ODAD_Adapter_Resolver $adapter_resolver,
    ) {}

    /**
     * Parse the $expand string into a structured expand plan.
     *
     * @param string $expand   Raw $expand string, e.g. "Author,Tags($select=Name)"
     * @return array  Expand plan:
     * [
     *   [
     *     'nav_property'  => 'Author',
     *     'entity_set'    => 'Users',       // resolved from nav property definition
     *     'is_collection' => false,
     *     'nested_select' => ['DisplayName'], // from nested $select
     *     'nested_filter' => null,
     *     'nested_expand' => null,
     *     'nested_top'    => null,
     *     'nested_skip'   => null,
     *     'nested_orderby'=> null,
     *   ],
     *   ...
     * ]
     */
    public function parse( string $expand, array $nav_property_map ): array;

    /**
     * Execute the expand plan: for each entity in $rows, load related entities.
     *
     * @param array $rows             Base entity rows (OData property names)
     * @param array $expand_plan      Output of parse()
     * @param string $base_entity_set Name of the base entity set
     * @return array  $rows with navigation properties populated inline
     */
    public function execute( array $rows, array $expand_plan, string $base_entity_set ): array;
}
```

---

## $expand Syntax to Support

### Simple
```
$expand=Author
$expand=Author,Tags,Categories
```

### With nested query options
```
$expand=Author($select=DisplayName,Email)
$expand=Tags($filter=Name eq 'php')
$expand=Author($select=DisplayName;$expand=Posts($select=Title;$top=5))
```

Note: Inside `$expand(...)`, options are separated by `;` not `&`.

---

## Navigation Property Map

The caller provides a `$nav_property_map` from the adapter's `get_entity_type_definition()`:
```php
[
    'Author'     => [ 'type' => 'Users',      'collection' => false, 'fk' => 'AuthorID' ],
    'Tags'       => [ 'type' => 'Tags',        'collection' => true,  'fk' => 'ID', 'join_table' => 'wp_term_relationships' ],
    'Categories' => [ 'type' => 'Categories',  'collection' => true,  'fk' => 'ID', 'join_table' => 'wp_term_relationships' ],
    'Meta'       => [ 'type' => 'PostMeta',    'collection' => true,  'fk' => 'ID', 'remote_fk' => 'post_id' ],
]
```

---

## Execution Strategy

For each nav property in the expand plan:

**Single entity (e.g. Author):**
```
For each row, extract the FK value (e.g. AuthorID),
batch all FK values into one query,
load all matching Users,
attach each User to the corresponding row.
```
Use batched loading (one query for all rows' Author), not N+1 queries.

**Collection (e.g. Tags):**
```
For each row, extract the ID,
batch all IDs,
load all matching Tags for all IDs in one query,
group results by parent ID,
attach Tag arrays to corresponding rows.
```

---

## Acceptance Criteria

- `parse('Author,Tags', $nav_map)` returns two entries with correct `entity_set` and `is_collection`.
- `parse('Author($select=DisplayName)', $nav_map)` returns `nested_select: ['DisplayName']`.
- `execute()` produces no N+1 queries — related entities are loaded in batches.
- Unknown navigation property in `$expand` throws `ODAD_Expand_Exception`.
- Nested `$expand` (depth 2+) is supported.
- No WordPress calls in this file (uses `ODAD_Adapter_Resolver::resolve()` to get adapters, which are WP-aware but accessed through the interface).
