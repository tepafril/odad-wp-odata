# Task 3.2 — Filter Compiler: AST → SQL WHERE

## Dependencies
- Task 3.1 (filter parser + AST node classes)
- Task 2.1 (ODAD_Query_Context — for column mapping)
- Task 2.2–2.5 (adapters — for property → column name mapping)

## Goal
Walk the AST produced by `ODAD_Filter_Parser` and compile it into a parameterized
SQL `WHERE` clause using `$wpdb->prepare()`. This is a security-critical component:
**never use string interpolation for user-supplied values**.

---

## File

### `src/query/class-wpos-filter-compiler.php`

```php
class ODAD_Filter_Compiler {

    /**
     * Compile an AST into a SQL WHERE fragment.
     *
     * @param ODAD_AST_Node        $ast         Root node from ODAD_Filter_Parser
     * @param array<string,string> $column_map  OData property name → SQL column name
     * @return array{sql: string, params: array}
     */
    public function compile( ODAD_AST_Node $ast, array $column_map ): array;
}
```

Returns an array with two keys:
- `sql` — SQL fragment with `%s`, `%d`, `%f` placeholders safe for `$wpdb->prepare()`
- `params` — ordered array of parameter values

**Usage pattern:**
```php
[ 'sql' => $sql, 'params' => $params ] = $compiler->compile( $ast, $column_map );
$safe_sql = $wpdb->prepare( $sql, ...$params );
```

---

## Compilation Rules

### Binary Operators

| AST op | SQL | Notes |
|---|---|---|
| `eq` | `col = %s` | Use `IS NULL` when value is null |
| `ne` | `col != %s` | Use `IS NOT NULL` when value is null |
| `lt` | `col < %s` | |
| `le` | `col <= %s` | |
| `gt` | `col > %s` | |
| `ge` | `col >= %s` | |
| `and` | `(left AND right)` | |
| `or` | `(left OR right)` | |
| `add` | `(left + right)` | Arithmetic in SELECT context |
| `sub` | `(left - right)` | |
| `mul` | `(left * right)` | |
| `div` | `(left DIV right)` | Integer division |
| `divby` | `(left / right)` | Float division |
| `mod` | `(left MOD right)` | |

### Unary Operators

| AST op | SQL |
|---|---|
| `not` | `NOT (expr)` |
| `-` | `-(expr)` |

### `in` Operator

```sql
col IN (%s, %s, %s)
```

Generate one placeholder per value.

### String Functions → SQL

| OData Function | MySQL equivalent |
|---|---|
| `contains(col, val)` | `col LIKE CONCAT('%', %s, '%')` |
| `startswith(col, val)` | `col LIKE CONCAT(%s, '%')` |
| `endswith(col, val)` | `col LIKE CONCAT('%', %s)` |
| `length(col)` | `CHAR_LENGTH(col)` |
| `indexof(col, val)` | `LOCATE(%s, col) - 1` |
| `substring(col, start)` | `SUBSTRING(col, start+1)` |
| `substring(col, start, len)` | `SUBSTRING(col, start+1, len)` |
| `tolower(col)` | `LOWER(col)` |
| `toupper(col)` | `UPPER(col)` |
| `trim(col)` | `TRIM(col)` |
| `concat(a, b)` | `CONCAT(a, b)` |
| `matchesPattern(col, pattern)` | `col REGEXP %s` |

### Date Functions → SQL

| OData Function | MySQL equivalent |
|---|---|
| `year(col)` | `YEAR(col)` |
| `month(col)` | `MONTH(col)` |
| `day(col)` | `DAY(col)` |
| `hour(col)` | `HOUR(col)` |
| `minute(col)` | `MINUTE(col)` |
| `second(col)` | `SECOND(col)` |
| `now()` | `NOW()` |
| `date(col)` | `DATE(col)` |

### Math Functions

| OData | MySQL |
|---|---|
| `round(col)` | `ROUND(col)` |
| `floor(col)` | `FLOOR(col)` |
| `ceiling(col)` | `CEIL(col)` |

---

## Property → Column Mapping

The `$column_map` array is provided by the caller (built from the adapter's
`get_entity_type_definition()`). Example:
```php
[
    'ID'            => 'p.ID',
    'Title'         => 'p.post_title',
    'Status'        => 'p.post_status',
    'PublishedDate' => 'p.post_date_gmt',
    'AuthorID'      => 'p.post_author',
]
```

If a property in the filter is NOT in the column map, throw
`ODAD_Filter_Compile_Exception` (create this class) with message
`"Unknown property: {property_name}"`.

**Never allow arbitrary column names** — they must exist in the column map.
This prevents SQL injection via property name injection.

---

## `$wpdb->prepare()` Placeholder Selection

- String literals → `%s`
- Integer literals → `%d`
- Float literals → `%f`
- Boolean literals → `%d` (1/0)
- Null → emit `IS NULL` / `IS NOT NULL` directly (no placeholder)

---

## `ODAD_filter_sql` Filter

After compiling the full WHERE clause, the subscriber (Task 3.6) applies the
`ODAD_filter_sql` WP filter. The compiler itself does NOT call `apply_filters` —
that is the subscriber's job. The compiler just returns the raw SQL + params array.

---

## Acceptance Criteria

- `compile(AST(Title eq 'Hello'))` returns `['sql' => 'p.post_title = %s', 'params' => ['Hello']]`.
- `compile(AST(Status in ('draft', 'publish')))` returns correct `IN (%s, %s)` with params.
- `compile(AST(contains(Title, 'news')))` returns `p.post_title LIKE CONCAT('%', %s, '%')`.
- Null comparison `Title eq null` produces `p.post_title IS NULL` with no placeholder.
- Unknown property throws `ODAD_Filter_Compile_Exception`.
- Passing the returned `['sql', 'params']` through `$wpdb->prepare($sql, ...$params)` produces valid SQL with no injection risk.
- No WordPress calls in this file (no `$wpdb` access — that is done by callers).
