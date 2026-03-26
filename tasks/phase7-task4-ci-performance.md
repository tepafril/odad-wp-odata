# Task 7.4 — GitHub Actions CI Matrix + Performance Tuning

## Dependencies
- Task 7.1 (unit tests)
- Task 7.2 (integration tests)

## Goal
Set up a GitHub Actions CI pipeline that tests the plugin across the PHP/WordPress
matrix, and implement performance optimizations identified in the master plan.

---

## CI Configuration

### `.github/workflows/tests.yml`

```yaml
name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  unit-tests:
    name: Unit Tests (PHP ${{ matrix.php }})
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.1', '8.2', '8.3']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: xdebug
      - run: composer install --prefer-dist
      - run: ./vendor/bin/phpunit --testsuite Unit --coverage-text

  integration-tests:
    name: Integration Tests (PHP ${{ matrix.php }}, WP ${{ matrix.wp }})
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.1', '8.2', '8.3']
        wp:  ['6.3', '6.4', '6.5']
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        options: --health-cmd="mysqladmin ping" --health-interval=10s
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
      - run: composer install --prefer-dist
      - name: Install WP test suite
        run: |
          bash tests/bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 ${{ matrix.wp }}
      - run: ./vendor/bin/phpunit --configuration phpunit-integration.xml

  php-cs-fixer:
    name: Code Style
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --prefer-dist
      - run: ./vendor/bin/php-cs-fixer fix --dry-run --diff
```

### `tests/bin/install-wp-tests.sh`

Standard WP test suite installer script (download from WordPress develop repo).

---

## Performance Tuning

### 1. Query Analysis

Add a development-mode query logger. When `WP_DEBUG` is true, log all adapter queries
with execution time to a debug log. This helps identify slow queries.

```php
// In WPOS_Adapter_WP_Posts::get_collection():
if ( defined('WP_DEBUG') && WP_DEBUG ) {
    $start = microtime(true);
    // ... query ...
    $time = microtime(true) - $start;
    if ( $time > 0.1 ) {
        error_log("WPOS slow query ({$time}s): {$sql}");
    }
}
```

### 2. Index Recommendations

Document recommended database indexes in `docs/performance.md`:

| Table | Index | When needed |
|---|---|---|
| `wp_posts` | `(post_type, post_status)` | Any Posts/Pages query |
| `wp_posts` | `(post_author, post_type)` | Author-filtered queries |
| `wp_posts` | `(post_date_gmt)` | Date-range filters |
| `wp_postmeta` | `(post_id, meta_key)` | Meta expansion |
| `wp_usermeta` | `(user_id, meta_key)` | User meta expansion |
| `wp_term_relationships` | `(term_taxonomy_id)` | Tag/Category queries |

### 3. `$top` Cap

Default: 100. Maximum: 1000 (already enforced in `WPOS_Request`).
Make these configurable via WP options:

```php
$default_top = (int) get_option('wpos_default_top', 100);
$max_top     = (int) get_option('wpos_max_top',     1000);
```

Allow admins to configure these on the dashboard page (Task 6.1).

### 4. Metadata Cache Tuning

Default TTL: `DAY_IN_SECONDS`. Configurable via option `wpos_metadata_cache_ttl`.
On sites with many CPTs, metadata rebuilds are expensive — a longer TTL is better.

### 5. `$expand` Batch Loading

Ensure expand compiler uses batched queries (verified in Task 3.4).
Add a slow-query test: `$expand=Author` on 100 posts should produce exactly 2 queries
(one for posts, one for authors), not 101.

### 6. Opcache / WP Object Cache

Document that the plugin benefits from:
- PHP opcache (autoloaded files are large)
- A persistent object cache (Memcached/Redis) for metadata transients

---

## Composer Scripts

Add to `composer.json`:
```json
{
  "scripts": {
    "test:unit":        "./vendor/bin/phpunit --testsuite Unit",
    "test:integration": "./vendor/bin/phpunit --configuration phpunit-integration.xml",
    "test:all":         ["@test:unit", "@test:integration"],
    "cs:check":         "./vendor/bin/php-cs-fixer fix --dry-run --diff",
    "cs:fix":           "./vendor/bin/php-cs-fixer fix"
  }
}
```

---

## Acceptance Criteria

- CI runs on every push/PR to `main` and `develop`.
- All 9 matrix combinations (3 PHP × 3 WP) pass in CI.
- Unit tests run in < 10 seconds.
- Integration tests run in < 120 seconds.
- `$expand=Author` on a 100-row collection uses exactly 2 DB queries (verified by test).
- Code style check passes (PHP-CS-Fixer with WordPress standards).
