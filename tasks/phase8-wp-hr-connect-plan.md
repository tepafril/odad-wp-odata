# WP HR Connect — Implementation Plan for AI Agents

## Overview

A WordPress plugin sold on CodeCanyon that provides:
- HR data management (employees, check-ins, leave requests)
- Built-in JWT authentication — no third-party auth plugin required
- Mobile app API via WP-OData Suite (must be installed as a dependency)
- WordPress admin UI for HR managers

**Plugin slug:** `wp-hr-connect`
**PHP namespace prefix:** `WPHR_`
**REST namespace:** `wp-hr/v1`
**OData entity sets:** `Employees`, `CheckIns`, `LeaveRequests`
**Requires:** PHP 8.1+, WordPress 6.3+, WP-OData Suite 0.1.0+
**Plugin root:** `wp-hr-connect/`

---

## Architecture

```
Mobile App
    │
    ├── POST /wp-json/wp-hr/v1/auth/login      → WPHR_Auth_Controller
    ├── POST /wp-json/wp-hr/v1/auth/refresh    → WPHR_Auth_Controller
    ├── POST /wp-json/wp-hr/v1/auth/logout     → WPHR_Auth_Controller
    │
    ├── GET  /wp-json/odata/v4/CheckIns        ─┐
    ├── POST /wp-json/odata/v4/CheckIns         ├─ WP-OData Suite
    ├── GET  /wp-json/odata/v4/LeaveRequests    │  (via WPHR adapters)
    ├── POST /wp-json/odata/v4/LeaveRequests    │
    └── GET  /wp-json/odata/v4/Employees       ─┘

WordPress Admin
    ├── HR Connect → Dashboard
    ├── HR Connect → Employees
    ├── HR Connect → Leave Requests (approval UI)
    └── HR Connect → Settings (token expiry, secret rotation)
```

### Key design rules

1. JWT signing uses `hash_hmac('SHA256', ...)` — no Composer library needed.
2. The signing secret is generated on activation and stored in `wp_options`.
3. Refresh tokens are stored in `{prefix}hr_refresh_tokens` (revocable).
4. A `determine_current_user` filter validates Bearer tokens before every REST request.
5. WP-OData Suite handles all OData protocol; this plugin only registers adapters.
6. Row-level security: employees see only their own check-ins and leave requests.
7. All admin-facing WP hooks go through `WPHR_Hook_Bridge` (same pattern as WPOS).

---

## Database Tables

```sql
-- Employees (links a WP user to an HR record)
CREATE TABLE {prefix}hr_employees (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,          -- FK to wp_users
    employee_no VARCHAR(50)     NOT NULL DEFAULT '',
    department  VARCHAR(100)    NOT NULL DEFAULT '',
    position    VARCHAR(100)    NOT NULL DEFAULT '',
    hired_on    DATE            NOT NULL,
    status      VARCHAR(20)     NOT NULL DEFAULT 'active', -- active|inactive
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user (user_id),
    KEY idx_dept (department),
    KEY idx_status (status)
);

-- Check-ins
CREATE TABLE {prefix}hr_checkins (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    checkin_at  DATETIME        NOT NULL,
    checkout_at DATETIME                 DEFAULT NULL,
    latitude    DECIMAL(10,7)            DEFAULT NULL,
    longitude   DECIMAL(10,7)            DEFAULT NULL,
    note        TEXT                     DEFAULT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_employee (employee_id),
    KEY idx_checkin_at (checkin_at)
);

-- Leave requests
CREATE TABLE {prefix}hr_leave_requests (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    type        VARCHAR(50)     NOT NULL,  -- annual|sick|unpaid|other
    start_date  DATE            NOT NULL,
    end_date    DATE            NOT NULL,
    reason      TEXT                     DEFAULT NULL,
    status      VARCHAR(20)     NOT NULL DEFAULT 'pending', -- pending|approved|rejected
    reviewed_by BIGINT UNSIGNED          DEFAULT NULL,      -- WP user ID of reviewer
    reviewed_at DATETIME                 DEFAULT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_employee (employee_id),
    KEY idx_status (status),
    KEY idx_dates (start_date, end_date)
);

-- Refresh tokens (revocable)
CREATE TABLE {prefix}hr_refresh_tokens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64)     NOT NULL,   -- SHA-256 of the raw token
    device_name VARCHAR(100)    NOT NULL DEFAULT '',
    expires_at  DATETIME        NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token_hash),
    KEY idx_user (user_id),
    KEY idx_expires (expires_at)
);
```

---

## Phase 1 — Plugin Foundation

**Goal:** Plugin entry point, autoloader, DI container, bootstrapper, DB installer.

### Tasks

#### Task 1.1 — Plugin entry point + autoloader

**File:** `wp-hr-connect.php`

```php
<?php
/**
 * Plugin Name: WP HR Connect
 * Plugin URI:  https://codecanyon.net/
 * Description: HR management with mobile API. Requires WP-OData Suite.
 * Version:     1.0.0
 * Requires at least: 6.3
 * Requires PHP: 8.1
 * Author:      Your Name
 * License:     GPL-2.0-or-later
 * Text Domain: wp-hr-connect
 */

defined( 'ABSPATH' ) || exit;

define( 'WPHR_VERSION',    '1.0.0' );
define( 'WPHR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPHR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader: WPHR_ prefix → src/ subdirectories
spl_autoload_register( function ( string $class ): void {
    if ( ! str_starts_with( $class, 'WPHR_' ) ) return;
    $suffix   = strtolower( substr( $class, 5 ) );
    $filename = str_replace( '_', '-', $suffix );
    $dirs = [ 'bootstrap', 'auth', 'adapters', 'admin', 'hooks', 'http' ];
    foreach ( $dirs as $dir ) {
        $path = WPHR_PLUGIN_DIR . "src/{$dir}/class-wphr-{$filename}.php";
        if ( file_exists( $path ) ) { require_once $path; return; }
    }
    // subscribers
    $path = WPHR_PLUGIN_DIR . "src/hooks/subscribers/class-wphr-{$filename}.php";
    if ( file_exists( $path ) ) require_once $path;
} );

// Activation / deactivation
register_activation_hook( __FILE__,   [ 'WPHR_Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'WPHR_Installer', 'deactivate' ] );

add_action( 'plugins_loaded', function (): void {
    // Abort gracefully if WP-OData Suite is not active.
    if ( ! function_exists( 'wpos_container' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'WP HR Connect requires WP-OData Suite to be installed and active.', 'wp-hr-connect' );
            echo '</p></div>';
        } );
        return;
    }
    WPHR_Bootstrapper::boot();
}, 15 ); // priority 15 — after WPOS (priority 5) is fully loaded

function wphr_container(): WPHR_Container {
    return $GLOBALS['wphr_container'];
}
```

#### Task 1.2 — DI container

**File:** `src/bootstrap/class-wphr-container.php`

Identical pattern to `WPOS_Container`. Methods: `singleton(string, Closure)`, `get(string)`, `has(string)`. Throws `\RuntimeException` on missing binding.

#### Task 1.3 — DB installer

**File:** `src/bootstrap/class-wphr-installer.php`

```php
class WPHR_Installer {
    public static function activate(): void {
        self::create_tables();
        self::generate_jwt_secret();
        self::create_hr_employee_role();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function create_tables(): void;   // dbDelta() all 4 tables
    private static function generate_jwt_secret(): void; // wp_generate_password(64) → option 'wphr_jwt_secret' (only if not set)
    private static function create_hr_employee_role(): void; // add_role('hr_employee', ...)
}
```

**`hr_employee` role capabilities:** `read`, `wphr_checkin`, `wphr_request_leave`

#### Task 1.4 — Bootstrapper

**File:** `src/bootstrap/class-wphr-bootstrapper.php`

```php
class WPHR_Bootstrapper {
    public static function boot(): void {
        $c = new WPHR_Container();

        $c->singleton( WPHR_JWT::class,             fn() => new WPHR_JWT() );
        $c->singleton( WPHR_Token_Store::class,     fn() => new WPHR_Token_Store() );
        $c->singleton( WPHR_Auth_Controller::class, fn($c) => new WPHR_Auth_Controller(
            $c->get(WPHR_JWT::class),
            $c->get(WPHR_Token_Store::class),
        ));
        $c->singleton( WPHR_Employee_Adapter::class,     fn() => new WPHR_Employee_Adapter() );
        $c->singleton( WPHR_Checkin_Adapter::class,      fn() => new WPHR_Checkin_Adapter() );
        $c->singleton( WPHR_Leave_Request_Adapter::class, fn() => new WPHR_Leave_Request_Adapter() );
        $c->singleton( WPHR_Admin::class,            fn($c) => new WPHR_Admin( $c ) );

        $GLOBALS['wphr_container'] = $c;

        // Register REST routes + WP-OData Suite hooks
        add_action( 'rest_api_init',        [ WPHR_Auth_Controller::class, 'register_routes' ] );
        add_filter( 'determine_current_user', [ WPHR_JWT_Auth_Handler::class, 'resolve_user' ], 20 );
        add_action( 'wpos_register_entity_sets', [ WPHR_OData_Registrar::class, 'register' ], 10, 2 );
        add_action( 'wpos_register_permissions', [ WPHR_OData_Registrar::class, 'register_permissions' ] );

        if ( is_admin() ) {
            add_action( 'admin_menu', fn() => wphr_container()->get( WPHR_Admin::class )->register_menu() );
        }
    }
}
```

---

## Phase 2 — JWT Authentication

**Goal:** Stateless access tokens (15 min) + revocable refresh tokens (30 days).

### Token structure

**Access token payload (JWT HS256):**
```json
{
  "sub": 42,
  "iat": 1711500000,
  "exp": 1711500900,
  "type": "access"
}
```

**Refresh token:** 32-byte random hex string. Stored as SHA-256 hash in DB.

### Tasks

#### Task 2.1 — JWT utility

**File:** `src/auth/class-wphr-jwt.php`

```php
class WPHR_JWT {
    private string $secret;
    private int    $access_ttl;   // seconds, default 900 (15 min)
    private int    $refresh_ttl;  // seconds, default 2592000 (30 days)

    public function __construct() {
        $this->secret      = get_option( 'wphr_jwt_secret', '' );
        $this->access_ttl  = (int) get_option( 'wphr_access_token_ttl',  900 );
        $this->refresh_ttl = (int) get_option( 'wphr_refresh_token_ttl', 2592000 );
    }

    /** Issue a signed access token for a WP user ID. */
    public function issue_access_token( int $user_id ): string;

    /**
     * Verify and decode an access token.
     * @throws WPHR_Token_Exception on invalid/expired token
     * @return object payload
     */
    public function verify_access_token( string $token ): object;

    /** Generate a cryptographically random refresh token (raw hex). */
    public function generate_refresh_token(): string;

    public function get_refresh_ttl(): int;

    // ── Private helpers ────────────────────────────────────────────────────

    private function base64url_encode( string $data ): string;
    private function base64url_decode( string $data ): string;
    private function sign( string $header_payload ): string; // hash_hmac('sha256', ...)
}
```

**Implementation notes:**
- Header: `{"alg":"HS256","typ":"JWT"}` (base64url-encoded, static)
- Payload: JSON-encode then base64url-encode
- Signature: `hash_hmac('sha256', "$header.$payload", $secret, true)` → base64url-encode
- `verify_access_token`: split by `.`, verify signature, check `exp`, check `type === 'access'`

#### Task 2.2 — Refresh token store

**File:** `src/auth/class-wphr-token-store.php`

```php
class WPHR_Token_Store {
    /** Persist a refresh token. Returns the raw token string. */
    public function store( int $user_id, string $raw_token, int $ttl, string $device = '' ): void;

    /** Verify raw token exists and is not expired. Returns user_id or throws. */
    public function consume( string $raw_token ): int;

    /** Revoke a single refresh token. */
    public function revoke( string $raw_token ): void;

    /** Revoke all refresh tokens for a user (logout everywhere). */
    public function revoke_all( int $user_id ): void;

    /** Delete expired tokens (called periodically). */
    public function purge_expired(): void;

    private function hash( string $raw_token ): string; // hash('sha256', $raw_token)
}
```

#### Task 2.3 — Auth REST controller

**File:** `src/http/class-wphr-auth-controller.php`

```php
class WPHR_Auth_Controller {

    public function __construct(
        private WPHR_JWT         $jwt,
        private WPHR_Token_Store $store,
    ) {}

    public static function register_routes(): void {
        register_rest_route( 'wp-hr/v1', '/auth/login',   [ 'methods' => 'POST', 'callback' => ..., 'permission_callback' => '__return_true' ] );
        register_rest_route( 'wp-hr/v1', '/auth/refresh', [ 'methods' => 'POST', 'callback' => ..., 'permission_callback' => '__return_true' ] );
        register_rest_route( 'wp-hr/v1', '/auth/logout',  [ 'methods' => 'POST', 'callback' => ..., 'permission_callback' => '__return_true' ] );
    }

    /**
     * POST /wp-hr/v1/auth/login
     * Body: { "username": "...", "password": "...", "device": "iPhone 15" }
     * Response 200: { "access_token", "refresh_token", "expires_in", "user" }
     * Response 401: { "code": "invalid_credentials", "message": "..." }
     */
    public function login( WP_REST_Request $request ): WP_REST_Response;

    /**
     * POST /wp-hr/v1/auth/refresh
     * Body: { "refresh_token": "..." }
     * Response 200: { "access_token", "expires_in" }
     * Response 401: { "code": "invalid_refresh_token", "message": "..." }
     */
    public function refresh( WP_REST_Request $request ): WP_REST_Response;

    /**
     * POST /wp-hr/v1/auth/logout
     * Body: { "refresh_token": "..." }   (optional — omit to logout current device only)
     * Header: Authorization: Bearer <access_token>
     * Response 204: No Content
     */
    public function logout( WP_REST_Request $request ): WP_REST_Response;

    private function build_user_payload( WP_User $user ): array; // { id, login, email, display_name, role }
}
```

**Login flow:**
1. `wp_authenticate( $username, $password )` — returns `WP_User` or `WP_Error`
2. If `WP_Error` → return 401
3. `$access_token  = $this->jwt->issue_access_token( $user->ID )`
4. `$refresh_token = $this->jwt->generate_refresh_token()`
5. `$this->store->store( $user->ID, $refresh_token, $this->jwt->get_refresh_ttl(), $device )`
6. Return 200 with both tokens

**Rate limiting:** Track failed login attempts per IP in a transient (`wphr_login_fails_{ip}`). After 5 failures within 15 minutes, return 429.

#### Task 2.4 — JWT authentication handler (WP integration)

**File:** `src/auth/class-wphr-jwt-auth-handler.php`

```php
class WPHR_JWT_Auth_Handler {

    /**
     * Hooked onto 'determine_current_user' at priority 20.
     * If a valid Bearer token is present, returns the WP user ID.
     * Otherwise passes $user_id through unchanged.
     */
    public static function resolve_user( int|false $user_id ): int|false {
        $token = self::extract_bearer_token();
        if ( null === $token ) return $user_id;

        try {
            $payload = wphr_container()->get( WPHR_JWT::class )->verify_access_token( $token );
            return (int) $payload->sub;
        } catch ( WPHR_Token_Exception $e ) {
            // Invalid token — let WordPress handle it (will result in 401)
            return false;
        }
    }

    private static function extract_bearer_token(): ?string {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;
        if ( $header && str_starts_with( $header, 'Bearer ' ) ) {
            return substr( $header, 7 );
        }
        return null;
    }
}
```

#### Task 2.5 — Token exception

**File:** `src/auth/class-wphr-token-exception.php`

Simple exception class. Properties: `$code_slug` (e.g. `'token_expired'`, `'token_invalid'`).

---

## Phase 3 — OData Adapters (HR Data)

**Goal:** Register `Employees`, `CheckIns`, `LeaveRequests` with WP-OData Suite.

### Tasks

#### Task 3.1 — OData registrar

**File:** `src/adapters/class-wphr-odata-registrar.php`

```php
class WPHR_OData_Registrar {

    public static function register(
        WPOS_Schema_Registry  $registry,
        WPOS_Adapter_Resolver $resolver,
    ): void {
        $adapters = [
            wphr_container()->get( WPHR_Employee_Adapter::class ),
            wphr_container()->get( WPHR_Checkin_Adapter::class ),
            wphr_container()->get( WPHR_Leave_Request_Adapter::class ),
        ];
        foreach ( $adapters as $adapter ) {
            $name = $adapter->get_entity_set_name();
            $resolver->register( $name, $adapter );
            $registry->register( $name, $adapter->get_entity_type_definition() );
        }
    }

    public static function register_permissions( WPOS_Capability_Map $map ): void {
        $map->register( 'Employees',     [ 'read' => 'read',               'insert' => 'manage_options', 'update' => 'manage_options', 'delete' => 'manage_options' ] );
        $map->register( 'CheckIns',      [ 'read' => 'wphr_checkin',       'insert' => 'wphr_checkin',   'update' => 'wphr_checkin',   'delete' => 'manage_options' ] );
        $map->register( 'LeaveRequests', [ 'read' => 'wphr_request_leave', 'insert' => 'wphr_request_leave', 'update' => 'wphr_request_leave', 'delete' => 'manage_options' ] );
    }
}
```

#### Task 3.2 — Employee adapter

**File:** `src/adapters/class-wphr-employee-adapter.php`

Implements `WPOS_Adapter`. Uses `WPOS_Adapter_Custom_Table` internally or implements directly against `{prefix}hr_employees`.

**Schema:**
```php
'key'        => 'ID',
'properties' => [
    'ID'         => [ 'column' => 'id',          'type' => 'Edm.Int64',          'read_only' => true ],
    'UserID'     => [ 'column' => 'user_id',      'type' => 'Edm.Int64' ],
    'EmployeeNo' => [ 'column' => 'employee_no',  'type' => 'Edm.String' ],
    'Department' => [ 'column' => 'department',   'type' => 'Edm.String' ],
    'Position'   => [ 'column' => 'position',     'type' => 'Edm.String' ],
    'HiredOn'    => [ 'column' => 'hired_on',     'type' => 'Edm.Date' ],
    'Status'     => [ 'column' => 'status',       'type' => 'Edm.String' ],
    'CreatedAt'  => [ 'column' => 'created_at',   'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
],
```

**Row-level security:** Non-admin users (`manage_options`) can only see their own record. Override `get_collection()` and `get_entity()` to inject `user_id = {current_user_id}` condition for non-admins.

#### Task 3.3 — Check-in adapter

**File:** `src/adapters/class-wphr-checkin-adapter.php`

**Schema:**
```php
'key'        => 'ID',
'properties' => [
    'ID'         => [ 'column' => 'id',          'type' => 'Edm.Int64',          'read_only' => true ],
    'EmployeeID' => [ 'column' => 'employee_id', 'type' => 'Edm.Int64' ],
    'CheckinAt'  => [ 'column' => 'checkin_at',  'type' => 'Edm.DateTimeOffset' ],
    'CheckoutAt' => [ 'column' => 'checkout_at', 'type' => 'Edm.DateTimeOffset' ],
    'Latitude'   => [ 'column' => 'latitude',    'type' => 'Edm.Decimal' ],
    'Longitude'  => [ 'column' => 'longitude',   'type' => 'Edm.Decimal' ],
    'Note'       => [ 'column' => 'note',        'type' => 'Edm.String' ],
    'CreatedAt'  => [ 'column' => 'created_at',  'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
],
```

**Row-level security:** Non-admins can only see their own check-ins (`employee_id` resolved from current user's HR record).

**Business rule in `insert()`:**
- Resolve `employee_id` from current user's HR record automatically (do not trust payload).
- Set `checkin_at` to `NOW()` if not provided.
- If employee already has an open check-in (no `checkout_at`), return a descriptive error.

#### Task 3.4 — Leave request adapter

**File:** `src/adapters/class-wphr-leave-request-adapter.php`

**Schema:**
```php
'key'        => 'ID',
'properties' => [
    'ID'         => [ 'column' => 'id',          'type' => 'Edm.Int64',          'read_only' => true ],
    'EmployeeID' => [ 'column' => 'employee_id', 'type' => 'Edm.Int64',          'read_only' => true ],
    'Type'       => [ 'column' => 'type',        'type' => 'Edm.String' ],        // annual|sick|unpaid|other
    'StartDate'  => [ 'column' => 'start_date',  'type' => 'Edm.Date' ],
    'EndDate'    => [ 'column' => 'end_date',     'type' => 'Edm.Date' ],
    'Reason'     => [ 'column' => 'reason',      'type' => 'Edm.String' ],
    'Status'     => [ 'column' => 'status',      'type' => 'Edm.String',          'read_only' => true ],
    'ReviewedBy' => [ 'column' => 'reviewed_by', 'type' => 'Edm.Int64',          'read_only' => true ],
    'ReviewedAt' => [ 'column' => 'reviewed_at', 'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
    'CreatedAt'  => [ 'column' => 'created_at',  'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
],
```

**Row-level security:** Employees see only their own; HR managers (`manage_options`) see all.

**Business rules:**
- `insert()`: set `employee_id` from current user's HR record; set `status = 'pending'`.
- `update()`: employees can only update `pending` requests; cannot change `status` (only managers can).

---

## Phase 4 — Admin UI

**Goal:** WordPress admin pages for HR managers.

### Tasks

#### Task 4.1 — Admin base

**File:** `src/admin/class-wphr-admin.php`

Menu structure:
```
WP HR Connect (dashicons-id-alt)
  ├── Dashboard       — stats (headcount, pending leaves, today's check-ins)
  ├── Employees       — list + add/edit employee records
  ├── Leave Requests  — pending approvals with approve/reject buttons
  └── Settings        — token TTL, secret rotation, dependency check
```

All pages require `manage_options`. No `apply_filters` / `do_action` directly — use `WPHR_Hook_Bridge` if needed.

#### Task 4.2 — Employees page

**File:** `src/admin/class-wphr-admin-employees.php`

Uses `WP_List_Table` to display employees. Columns: Employee No, Name (linked to WP user), Department, Position, Hired On, Status.

Actions: Add (links WP user to HR record), Edit, Deactivate.

Save flow: `admin_post_wphr_save_employee` → `$wpdb->insert()` / `$wpdb->update()` → redirect with `updated=1`.

#### Task 4.3 — Leave requests page

**File:** `src/admin/class-wphr-admin-leaves.php`

Lists leave requests with status filter (All / Pending / Approved / Rejected).

Approve/Reject via `admin_post_wphr_review_leave`:
```php
$wpdb->update( "{$wpdb->prefix}hr_leave_requests", [
    'status'      => $new_status,   // 'approved' | 'rejected'
    'reviewed_by' => get_current_user_id(),
    'reviewed_at' => current_time( 'mysql' ),
], [ 'id' => $leave_id ] );
```

#### Task 4.4 — Settings page

**File:** `src/admin/class-wphr-admin-settings.php`

WP Settings API. Options:

| Option key | Label | Default |
|---|---|---|
| `wphr_access_token_ttl` | Access token lifetime (seconds) | 900 |
| `wphr_refresh_token_ttl` | Refresh token lifetime (seconds) | 2592000 |

**Secret rotation button:** Generates a new `wphr_jwt_secret` (invalidates all existing access tokens). Shows confirmation warning.

**Dependency check:** Shows green/red status for WP-OData Suite version.

---

## Phase 5 — Security & Hardening

### Tasks

#### Task 5.1 — Rate limiting on login

In `WPHR_Auth_Controller::login()`:

```php
$ip_key  = 'wphr_login_fails_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
$fails   = (int) get_transient( $ip_key );
if ( $fails >= 5 ) {
    return new WP_REST_Response(
        [ 'code' => 'too_many_attempts', 'message' => 'Too many login attempts. Try again later.' ],
        429
    );
}
// On failure:
set_transient( $ip_key, $fails + 1, 15 * MINUTE_IN_SECONDS );
// On success:
delete_transient( $ip_key );
```

#### Task 5.2 — Expired token cleanup (WP-Cron)

Register a daily cron event on activation:

```php
wp_schedule_event( time(), 'daily', 'wphr_purge_expired_tokens' );
add_action( 'wphr_purge_expired_tokens', fn() =>
    wphr_container()->get( WPHR_Token_Store::class )->purge_expired()
);
```

Deregister on deactivation: `wp_clear_scheduled_hook('wphr_purge_expired_tokens')`.

#### Task 5.3 — Input validation

In all adapters, validate:
- `Type` for LeaveRequests: must be one of `annual`, `sick`, `unpaid`, `other`
- `start_date` / `end_date`: valid dates, end >= start
- `latitude` / `longitude`: valid numeric range (-90/90, -180/180)

#### Task 5.4 — Unit tests

**File:** `tests/unit/` (PHPUnit, no WP bootstrap)

Test: `WPHR_JWT` — issue + verify round-trip, expired token throws, tampered signature throws, wrong type throws.
Test: `WPHR_Token_Store` — store, consume, revoke, revoke_all (use `$wpdb` mock or in-memory stub).
Test: Rate limiter logic in login controller (mock transient functions).

---

## Phase 6 — Mobile Client Reference

This phase produces documentation only (no code).

#### Task 6.1 — Mobile API reference doc

**File:** `docs/mobile-api.md`

Document all endpoints with request/response examples:

- `POST /wp-hr/v1/auth/login`
- `POST /wp-hr/v1/auth/refresh`
- `POST /wp-hr/v1/auth/logout`
- `GET  /odata/v4/Employees`
- `GET  /odata/v4/CheckIns?$filter=EmployeeID eq {id}&$orderby=CheckinAt desc&$top=10`
- `POST /odata/v4/CheckIns` (check in)
- `PATCH /odata/v4/CheckIns({id})` (check out — set CheckoutAt)
- `GET  /odata/v4/LeaveRequests?$filter=EmployeeID eq {id}`
- `POST /odata/v4/LeaveRequests` (submit leave)

Include token storage advice (iOS Keychain, Android Keystore) and token refresh strategy (refresh when 401 received).

---

## Implementation Order for AI Agents

```
Phase 1 (sequential — foundation must be complete first)
  1.1 → 1.2 → 1.3 → 1.4

Phase 2 (sequential — each step depends on previous)
  2.1 → 2.2 → 2.3 → 2.4 → 2.5

Phase 3 (3.1 first, then 3.2 / 3.3 / 3.4 in parallel)
  3.1 → [ 3.2 ∥ 3.3 ∥ 3.4 ]

Phase 4 (4.1 first, then 4.2 / 4.3 / 4.4 in parallel)
  4.1 → [ 4.2 ∥ 4.3 ∥ 4.4 ]

Phase 5 (all in parallel, depends on Phase 2–3)
  [ 5.1 ∥ 5.2 ∥ 5.3 ∥ 5.4 ]

Phase 6 (independent)
  6.1
```

**Total estimated tasks:** 18
**Parallelizable:** Phases 3, 4, 5 each have parallel tasks
**External dependency:** WP-OData Suite must be installed in the same WordPress instance for integration tests

---

## File Structure

```
wp-hr-connect/
├── wp-hr-connect.php
├── composer.json
├── src/
│   ├── bootstrap/
│   │   ├── class-wphr-container.php
│   │   ├── class-wphr-bootstrapper.php
│   │   └── class-wphr-installer.php
│   ├── auth/
│   │   ├── class-wphr-jwt.php
│   │   ├── class-wphr-token-store.php
│   │   ├── class-wphr-jwt-auth-handler.php
│   │   └── class-wphr-token-exception.php
│   ├── http/
│   │   └── class-wphr-auth-controller.php
│   ├── adapters/
│   │   ├── class-wphr-odata-registrar.php
│   │   ├── class-wphr-employee-adapter.php
│   │   ├── class-wphr-checkin-adapter.php
│   │   └── class-wphr-leave-request-adapter.php
│   └── admin/
│       ├── class-wphr-admin.php
│       ├── class-wphr-admin-employees.php
│       ├── class-wphr-admin-leaves.php
│       └── class-wphr-admin-settings.php
├── assets/
│   ├── css/wphr-admin.css
│   └── js/wphr-admin.js
├── tests/
│   └── unit/
│       ├── bootstrap.php
│       ├── auth/JwtTest.php
│       ├── auth/TokenStoreTest.php
│       └── auth/AuthControllerTest.php
└── docs/
    └── mobile-api.md
```
