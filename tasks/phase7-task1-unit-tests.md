# Task 7.1 — PHPUnit Unit Tests (No WP Bootstrap)

## Dependencies
- All Phase 1–5 domain service implementations complete.

## Goal
Write PHPUnit unit tests for all pure PHP domain services.
These tests run WITHOUT WordPress bootstrap — they test domain logic in isolation.

---

## Test Setup

### `composer.json`

```json
{
  "require-dev": {
    "phpunit/phpunit": "^10.0"
  },
  "autoload-dev": {
    "classmap": ["tests/"]
  }
}
```

### `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/unit/bootstrap.php"
         testSuites="Unit"
         colors="true">
  <testsuites>
    <testsuite name="Unit">
      <directory>tests/unit</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

### `tests/unit/bootstrap.php`

```php
<?php
// No WordPress. Just load the plugin classes.
define('ABSPATH', '/');
define('DAY_IN_SECONDS', 86400);

// Load autoloader
require_once dirname(__DIR__, 2) . '/wp-odata-suite.php';
// Or manually include the autoloader without WordPress bootstrap
```

---

## Tests to Write

### Event Bus (`tests/unit/events/`)

**`EventBusTest.php`**
- Listener is called when matching event is dispatched
- Listener is NOT called for different event type
- Stoppable event stops dispatch when `stop_propagation()` called
- Multiple listeners called in registration order

---

### Filter Parser (`tests/unit/query/FilterParserTest.php`)

- Simple `eq` → correct binary AST
- `ne`, `lt`, `le`, `gt`, `ge` operators
- `and` / `or` precedence: `a eq 1 or b eq 2 and c eq 3` → correct tree
- `not` unary
- `in` operator
- String function: `contains(Title, 'foo')`
- Date function: `year(PublishedDate) gt 2020`
- `null` literal
- DateTime literal
- Nested parens
- Invalid expression → `ODAD_Filter_Parse_Exception`
- Parse exception includes position

---

### Filter Compiler (`tests/unit/query/FilterCompilerTest.php`)

- `eq` → SQL `= %s` with correct param
- `null` comparison → `IS NULL` (no placeholder)
- `in` → `IN (%s, %s)` with all params
- `contains` → `LIKE CONCAT('%', %s, '%')`
- `startswith` → `LIKE CONCAT(%s, '%')`
- `and` / `or` → parenthesized SQL
- Unknown property → `ODAD_Filter_Compile_Exception`
- Output is safe for `$wpdb->prepare()` (no unescaped values)

---

### Select Compiler (`tests/unit/query/SelectCompilerTest.php`)

- Selected properties map to correct column names
- Key property always included
- Unknown property → exception
- Empty select → all columns

---

### Orderby Compiler (`tests/unit/query/OrderbyCompilerTest.php`)

- `Title` → `col ASC`
- `Title desc` → `col DESC`
- `Title,Status desc` → two columns with correct directions
- Unknown property → exception
- Empty string → empty output

---

### Permission Engine (`tests/unit/permissions/PermissionEngineTest.php`)

Create a mock/stub `WP_User` for these tests:
```php
class MockUser {
    public int $ID;
    private array $caps;
    public function has_cap(string $cap): bool { return in_array($cap, $this->caps); }
}
```

Tests:
- Admin user can read Posts
- Subscriber cannot insert Posts
- Custom capability convention for unknown entity sets
- `apply_row_filter` adds conditions for non-admin Posts read
- `apply_row_filter` adds no conditions for admin

---

### Field ACL (`tests/unit/permissions/FieldACLTest.php`)

- `apply()` strips `Email` from Users result for user without `list_users`
- `apply()` keeps `Email` for user with `list_users`
- `apply()` never strips the key property
- `validate_write()` throws on read-only field in payload
- `validate_write()` accepts valid payload

---

### Metadata Builder (`tests/unit/metadata/MetadataBuilderTest.php`)

Use a fake/in-memory `ODAD_Metadata_Cache` and `ODAD_Schema_Registry`:
- Returns cached XML on second call (cache hit)
- Cache is busted when `ODAD_Event_Schema_Changed` dispatched
- XML output contains registered entity type names
- `ODAD_metadata_entity_types` filter result is used in output

---

### DI Container (`tests/unit/bootstrap/ContainerTest.php`)

- `get()` returns same instance for singleton
- `get()` throws `RuntimeException` for unregistered service
- Lazy initialization: factory not called until first `get()`

---

## Running Tests

```bash
cd wp-odata-suite
composer install
./vendor/bin/phpunit --testsuite Unit
```

---

## Acceptance Criteria

- All unit tests pass without a WordPress installation.
- Test coverage ≥ 80% for files in `src/query/`, `src/events/`, `src/permissions/`, `src/metadata/`.
- No `add_action`, `apply_filters`, or `$wpdb` calls used in tests.
- Tests run in < 5 seconds (no DB or HTTP).
