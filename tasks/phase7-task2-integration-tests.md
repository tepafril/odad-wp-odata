# Task 7.2 — Integration Tests (Adapters, Hooks, Admin)

## Dependencies
- All Phase 1–6 implementations complete.
- Task 7.1 (unit test infrastructure — reuse bootstrap patterns)

## Goal
Write integration tests that require WordPress bootstrap + database.
These use `WP_UnitTestCase` (from `wp-phpunit/wp-phpunit`).

---

## Test Setup

### Install WP test suite

```bash
composer require --dev wp-phpunit/wp-phpunit yoast/phpunit-polyfills
```

### `phpunit-integration.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/integration/bootstrap.php"
         testSuites="Integration"
         colors="true">
  <testsuites>
    <testsuite name="Integration">
      <directory>tests/integration</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

### `tests/integration/bootstrap.php`

```php
<?php
$_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';
require_once $_tests_dir . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wp-odata-suite.php';
```

---

## Adapter Integration Tests (`tests/integration/adapters/`)

### `WPPostsAdapterTest.php`

```php
class WPPostsAdapterTest extends WP_UnitTestCase {
    private ODAD_Adapter_WP_Posts $adapter;

    public function setUp(): void {
        parent::setUp();
        $this->adapter = new ODAD_Adapter_WP_Posts('post', 'Posts');
    }
}
```

Tests:
- `insert(['Title' => 'Test', 'Status' => 'publish'])` creates a post; key is integer
- `get_entity($key, $ctx)` returns the inserted post with OData property names
- `get_collection($ctx)` returns array of rows
- `update($key, ['Title' => 'Updated'])` changes the title
- `delete($key)` removes the post
- `get_count($ctx)` returns correct count
- Properties use OData names (not raw column names)
- `user_pass` is never in any returned row

### `WPUsersAdapterTest.php`

- `insert(['DisplayName' => 'Test User', 'Login' => 'testuser_x', 'Email' => 'x@test.com'])`
- `user_pass` not in returned rows
- `get_collection()` does not include `user_pass`

### `WPTermsAdapterTest.php`

- Insert/get/update/delete terms
- `get_collection()` only returns terms for the configured taxonomy

### `CustomTableAdapterTest.php`

- Create a temporary test table via `$wpdb->query()`
- Test CRUD operations
- Schema auto-detection via DESCRIBE

---

## Hook Integration Tests (`tests/integration/hooks/`)

### `HookBridgeTest.php`

Tests that all public WP hooks fire correctly:

```php
class HookBridgeTest extends WP_UnitTestCase {

    public function test_ODAD_register_entity_sets_fires_on_init(): void {
        $called = false;
        add_action('ODAD_register_entity_sets', function() use (&$called) {
            $called = true;
        });
        do_action('init');
        $this->assertTrue($called);
    }

    public function test_ODAD_query_context_filter_modifies_context(): void {
        add_filter('ODAD_query_context', function($ctx) {
            $ctx->extra_conditions[] = '1=0'; // Return no results
            return $ctx;
        });
        // Execute a query and verify no results returned
    }

    public function test_ODAD_can_read_filter_denies_access(): void {
        add_filter('ODAD_can_read', '__return_false');
        // Make REST request, expect 403
    }

    public function test_ODAD_inserted_action_fires(): void { ... }
    public function test_ODAD_before_insert_filter_modifies_payload(): void { ... }
}
```

All 25+ public hooks from the canonical hook registry (Section 6 of master plan)
should have at least one integration test.

### `DeepInsertTest.php`

- POST with nested Tags array creates post + term relationships
- Nested entity permission denied → 403, no post created

### `SetOperationTest.php`

- PATCH/$each updates all matching posts
- Single SQL statement (verify via `$wpdb->num_queries`)
- DELETE/$each deletes matching posts

---

## Admin Integration Tests (`tests/integration/admin/`)

### `AdminEntityConfigTest.php`

- Saving entity config dispatches `ODAD_Event_Admin_Entity_Config_Saved`
- `ODAD_admin_entity_config_saved` WP action fires
- Metadata cache is busted after save

### `AdminPermissionConfigTest.php`

- Saving permission config dispatches `ODAD_Event_Admin_Permission_Saved`
- Permission check respects saved role overrides

---

## REST API End-to-End Tests (`tests/integration/rest/`)

Use `WP_REST_Server` test helpers:

```php
class ODataEndpointTest extends WP_UnitTestCase {
    private WP_REST_Server $server;

    public function setUp(): void {
        parent::setUp();
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');
    }

    public function test_service_document(): void {
        $request  = new WP_REST_Request('GET', '/odata/v4/');
        $response = $this->server->dispatch($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('@odata.context', $data);
        $this->assertArrayHasKey('value', $data);
    }

    public function test_metadata_xml(): void { ... }
    public function test_posts_collection(): void { ... }
    public function test_create_post(): void { ... }
    public function test_unauthenticated_returns_403(): void { ... }
    public function test_filter_eq(): void { ... }
    public function test_top_and_skip(): void { ... }
    public function test_count(): void { ... }
}
```

---

## Acceptance Criteria

- All integration tests pass against WordPress 6.3/6.4/6.5 with a real test DB.
- Adapter tests verify round-trip: insert → get → update → delete → verify deleted.
- Every public hook in the canonical hook registry has at least one test.
- `user_pass` never appears in any test assertion for Users.
- REST API tests verify correct HTTP status codes, OData headers, and response body shape.
