# Task 3.3 — Select, OrderBy, Search, Compute Compilers

## Dependencies
- Task 2.1 (ODAD_Query_Context stub)
- Task 3.1 (AST node classes — Compute compiler may reuse AST)

## Goal
Build four pure-PHP compiler classes that translate OData query options into
SQL fragments. Each compiler handles one OData system query option.

---

## File 1: `src/query/class-odad-select-compiler.php`

Translates `$select=Title,Status,AuthorID` into a SQL column list.

```php
class ODAD_Select_Compiler {

    /**
     * @param string[]             $properties  OData property names from $select
     * @param array<string,string> $column_map  property → SQL column
     * @return string  SQL column list, e.g. "p.ID, p.post_title, p.post_status"
     */
    public function compile( array $properties, array $column_map ): string;
}
```

Rules:
- If `$properties` is empty, return `*` or the full column list (all mapped columns).
- Validate each property name against the column map. Throw `ODAD_Select_Exception` for unknown properties.
- Never allow raw SQL injection via property names — always look up in `$column_map`.
- The key property (e.g. `ID`) is always included even if not in `$select`.

---

## File 2: `src/query/class-odad-orderby-compiler.php`

Translates `$orderby=PublishedDate desc,Title` into a SQL ORDER BY clause.

```php
class ODAD_Orderby_Compiler {

    /**
     * @param string               $orderby    Raw $orderby string, e.g. "PublishedDate desc,Title"
     * @param array<string,string> $column_map property → SQL column
     * @return string  SQL ORDER BY clause, e.g. "p.post_date_gmt DESC, p.post_title ASC"
     */
    public function compile( string $orderby, array $column_map ): string;
}
```

Rules:
- Parse comma-separated list of `property [asc|desc]` tokens.
- Default direction is `ASC` if not specified.
- Validate each property against `$column_map`. Throw `ODAD_Orderby_Exception` for unknown properties.
- Return empty string if `$orderby` is empty.
- Direction must be exactly `ASC` or `DESC` — never interpolated from input.

---

## File 3: `src/query/class-odad-search-compiler.php`

Translates `$search=keyword` into a SQL LIKE expression for a basic full-text search.

```php
class ODAD_Search_Compiler {

    /**
     * @param string   $search_term   Raw search term from $search
     * @param string[] $search_columns SQL column names to search across
     * @return array{sql: string, params: array}
     */
    public function compile( string $search_term, array $search_columns ): array;
}
```

Rules:
- Produces: `(col1 LIKE %s OR col2 LIKE %s OR ...)` with `%keyword%` for each column.
- Escape `%` and `_` in the search term before using in LIKE.
- Return empty `['sql' => '', 'params' => []]` if `$search_term` is blank.
- This is a basic implementation; full-text relevance scoring is out of scope for v1.

---

## File 4: `src/query/class-odad-compute-compiler.php`

Translates `$compute=Price mul Quantity as Total` into SQL computed columns.

```php
class ODAD_Compute_Compiler {

    /**
     * @param string               $compute    Raw $compute string
     * @param array<string,string> $column_map property → SQL column
     * @return array  [
     *     'columns' => ['p.price * p.quantity AS Total'],  // SQL column expressions
     *     'aliases' => ['Total'],                           // new property names added
     * ]
     */
    public function compile( string $compute, array $column_map ): array;
}
```

Rules:
- Parse `expression as alias` clauses separated by commas.
- Expressions can include arithmetic operators (`add`, `sub`, `mul`, `divby`).
- Validate all property names against `$column_map`.
- Return empty arrays if `$compute` is blank.
- The computed alias becomes a new property available in `$select` and `$orderby`.

---

## `ODAD_Query_Context` Update

Update the `ODAD_Query_Context` stub from Task 2.1 to add proper typed properties
for parsed query options. Replace the `string` properties with structured types:

```php
class ODAD_Query_Context {
    public ?string $filter   = null;     // raw $filter string
    public ?array  $select   = null;     // parsed property names
    public ?array  $orderby  = null;     // [ ['property'=>'Title', 'dir'=>'asc'], ... ]
    public int     $top      = 100;
    public int     $skip     = 0;
    public bool    $count    = false;
    public ?string $expand   = null;     // raw $expand string
    public ?string $search   = null;     // raw $search string
    public ?string $compute  = null;     // raw $compute string
    public ?string $filter_sql = null;   // compiled SQL WHERE (set by compiler)
    public array   $filter_params = [];  // compiled params for $wpdb->prepare()
    public array   $extra_conditions = []; // additional WHERE fragments (row-level security)
}
```

---

## Acceptance Criteria

- `SelectCompiler::compile(['Title', 'Status'], $map)` returns `'p.post_title, p.post_status'`.
- Key property is always included in SELECT output.
- Unknown property in `$select` throws the appropriate exception.
- `OrderbyCompiler::compile('PublishedDate desc,Title', $map)` returns `'p.post_date_gmt DESC, p.post_title ASC'`.
- Unknown property in `$orderby` throws exception.
- `SearchCompiler::compile('news', ['p.post_title', 'p.post_content'])` returns LIKE query with `%news%`.
- `LIKE` special chars `%` and `_` in search term are escaped.
- `ComputeCompiler::compile('Price mul Quantity as Total', $map)` returns correct SQL expression.
- No WordPress calls in any of these files.
