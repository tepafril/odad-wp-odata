# WP-OData Suite — JWT Authentication Layer Plan

> **Scope:** Add stateless JWT auth endpoints directly into the existing `wp-odata-suite` plugin.
> No new plugin. No external dependencies.

---

## Overview

JWT authentication built into `wp-odata-suite`:

- `POST /wp-json/odad/v1/auth/login`
- `POST /wp-json/odad/v1/auth/refresh`
- `POST /wp-json/odad/v1/auth/logout`
- Bearer token validation hooked into WordPress's `determine_current_user` filter

**REST namespace:** `odad/v1`
**Class prefix:** `ODAD_` (existing convention)
**Option prefix:** `odad_`
**DB table:** `{prefix}odad_refresh_tokens`

---

## Architecture

```
Mobile App
    │
    ├── POST /wp-json/odad/v1/auth/login    → ODAD_Auth_Controller::login()
    ├── POST /wp-json/odad/v1/auth/refresh  → ODAD_Auth_Controller::refresh()
    └── POST /wp-json/odad/v1/auth/logout   → ODAD_Auth_Controller::logout()

Any REST endpoint:
    └── Authorization: Bearer <access_token>
            → ODAD_JWT_Auth_Handler::resolve_user()  (determine_current_user, priority 20)
            → Returns WP user ID to WordPress
```

### Key design rules

1. JWT signing: `hash_hmac('sha256', ...)` — no Composer library needed.
2. Signing secret: generated on first activation, stored as `odad_jwt_secret` in `wp_options`.
3. Access tokens: stateless, 15-minute default lifetime (`odad_access_token_ttl`).
4. Refresh tokens: 64 hex chars, stored hashed in DB, 30-day default (`odad_refresh_token_ttl`), revocable.
5. Rate limiting: 5 failed logins per IP per 15 minutes → 429.
6. Token cleanup: daily WP-Cron event `odad_purge_expired_tokens`.

---

## Database

```sql
CREATE TABLE {prefix}odad_refresh_tokens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64)     NOT NULL,   -- SHA-256 of the raw token
    device_name VARCHAR(100)    NOT NULL DEFAULT '',
    expires_at  DATETIME        NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token  (token_hash),
    KEY idx_user    (user_id),
    KEY idx_expires (expires_at)
);
```

---

## Token Structure

**Access token — JWT HS256:**
```json
{ "sub": 42, "iat": 1711500000, "exp": 1711500900, "type": "access" }
```

**Refresh token:** `bin2hex(random_bytes(32))` → 64 hex chars. Stored as `hash('sha256', $raw)`.

**`wp_options` keys:**

| Key | Default | Purpose |
|---|---|---|
| `odad_jwt_secret` | generated on activation | HMAC signing secret |
| `odad_access_token_ttl` | 900 | Access token lifetime in seconds |
| `odad_refresh_token_ttl` | 2592000 | Refresh token lifetime in seconds |

---

## What Changes in Existing Files

### `wp-odata-suite.php` — two additions

**1. Add `src/auth/` to the autoloader path list:**
```php
// add this line inside the $paths array:
ODAD_PLUGIN_DIR . "src/auth/class-odad-{$filename}.php",
```

**2. Add activation / deactivation hooks:**
```php
register_activation_hook( __FILE__,   [ 'ODAD_Auth_Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'ODAD_Auth_Installer', 'deactivate' ] );
```

### `src/bootstrap/class-odad-bootstrapper.php` — three additions

At the end of `build()`, register auth singletons and WP hooks:

```php
// ── Auth layer ────────────────────────────────────────────────────────────
$container->singleton( ODAD_JWT::class,             fn() => new ODAD_JWT() );
$container->singleton( ODAD_Token_Store::class,     fn() => new ODAD_Token_Store() );
$container->singleton( ODAD_Auth_Controller::class, fn( $c ) => new ODAD_Auth_Controller(
    $c->get( ODAD_JWT::class ),
    $c->get( ODAD_Token_Store::class ),
) );

add_action( 'rest_api_init',
    fn() => ODAD_container()->get( ODAD_Auth_Controller::class )->register_routes()
);
add_filter( 'determine_current_user',
    [ ODAD_JWT_Auth_Handler::class, 'resolve_user' ], 20
);
add_action( 'odad_purge_expired_tokens',
    fn() => ODAD_container()->get( ODAD_Token_Store::class )->purge_expired()
);
```

---

## New Files to Create

```
wp-odata-suite/
└── src/
    └── auth/                                     ← new directory
        ├── class-odad-auth-installer.php         — DB table, JWT secret, cron
        ├── class-odad-jwt.php                    — issue + verify access tokens
        ├── class-odad-token-store.php            — refresh token CRUD
        ├── class-odad-jwt-auth-handler.php       — determine_current_user filter
        └── class-odad-token-exception.php        — exception with $code_slug
    └── http/
        └── class-odad-auth-controller.php        — REST endpoints (new file)
```

---

## Phase 1 — Foundation Changes

### Task 1.1 — Auth Installer

**File:** `src/auth/class-odad-auth-installer.php`

```php
class ODAD_Auth_Installer {

    public static function activate(): void {
        self::create_table();
        self::generate_jwt_secret();
        self::schedule_cron();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'odad_purge_expired_tokens' );
        flush_rewrite_rules();
    }

    private static function create_table(): void;
    // dbDelta() for {prefix}odad_refresh_tokens

    private static function generate_jwt_secret(): void;
    // if ( ! get_option('odad_jwt_secret') )
    //     update_option('odad_jwt_secret', wp_generate_password(64, true, true), false)

    private static function schedule_cron(): void;
    // if ( ! wp_next_scheduled('odad_purge_expired_tokens') )
    //     wp_schedule_event(time(), 'daily', 'odad_purge_expired_tokens')
}
```

### Task 1.2 — Autoloader + hooks in `wp-odata-suite.php`

Add `src/auth/` path to the existing `$paths` array in the `spl_autoload_register` callback.
Add `register_activation_hook` and `register_deactivation_hook` pointing to `ODAD_Auth_Installer`.

### Task 1.3 — Bootstrapper additions

Add auth singletons and three WP hooks to the end of `ODAD_Bootstrapper::build()` as shown above.

---

## Phase 2 — Auth Classes

### Task 2.1 — JWT utility

**File:** `src/auth/class-odad-jwt.php`

```php
class ODAD_JWT {
    private string $secret;       // get_option('odad_jwt_secret')
    private int    $access_ttl;   // get_option('odad_access_token_ttl', 900)
    private int    $refresh_ttl;  // get_option('odad_refresh_token_ttl', 2592000)

    public function issue_access_token( int $user_id ): string;
    public function verify_access_token( string $token ): object; // throws ODAD_Token_Exception
    public function generate_refresh_token(): string;             // bin2hex(random_bytes(32))
    public function get_access_ttl(): int;
    public function get_refresh_ttl(): int;
}
```

Implementation:
- Static header: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9` (base64url of `{"alg":"HS256","typ":"JWT"}`)
- Payload: `base64url(json_encode(['sub'=>$id,'iat'=>time(),'exp'=>time()+$ttl,'type'=>'access']))`
- Signature: `base64url(hash_hmac('sha256', "$header.$payload", $secret, true))`
- `verify_access_token`: split by `.` (must be 3 parts), verify HMAC, check `exp`, check `type === 'access'`

### Task 2.2 — Refresh token store

**File:** `src/auth/class-odad-token-store.php`

```php
class ODAD_Token_Store {
    public function store( int $user_id, string $raw_token, int $ttl, string $device = '' ): void;
    public function consume( string $raw_token ): int;   // returns user_id; throws ODAD_Token_Exception
    public function revoke( string $raw_token ): void;
    public function revoke_all( int $user_id ): void;
    public function purge_expired(): void;               // DELETE WHERE expires_at < NOW()
    private function hash( string $raw ): string;        // hash('sha256', $raw)
}
```

`consume()` flow: look up `token_hash` → not found → throw `ODAD_Token_Exception('token_not_found')` →
check `expires_at > NOW()` → expired → throw `ODAD_Token_Exception('token_expired')` → return `(int) $row->user_id`.

### Task 2.3 — Auth REST controller

**File:** `src/http/class-odad-auth-controller.php`

```php
class ODAD_Auth_Controller {
    public function __construct(
        private ODAD_JWT         $jwt,
        private ODAD_Token_Store $store,
    ) {}

    public function register_routes(): void; // called on rest_api_init
}
```

| Route | Method | `permission_callback` | Body params |
|---|---|---|---|
| `odad/v1/auth/login` | POST | `__return_true` | `username`*, `password`*, `device` |
| `odad/v1/auth/refresh` | POST | `__return_true` | `refresh_token`* |
| `odad/v1/auth/logout` | POST | `is_user_logged_in` | `refresh_token`, `all_devices` |

**`login()` flow:**
1. Transient `odad_login_fails_{md5(ip)}` ≥ 5 → 429 `too_many_attempts`
2. `wp_authenticate($username, $password)` → `WP_Error` → increment transient → 401 `invalid_credentials`
3. Success: delete transient, issue tokens, call `$store->store()`
4. Return 200: `{ access_token, refresh_token, expires_in, user: {id, login, email, display_name, roles} }`

**`refresh()` flow:**
1. `$store->consume($refresh_token)` → throws → 401 `invalid_refresh_token`
2. Return 200: `{ access_token, expires_in }`

**`logout()` flow:**
1. `all_devices === true` or no `refresh_token` → `$store->revoke_all(get_current_user_id())`
2. Otherwise → `$store->revoke($refresh_token)`
3. Return 204

### Task 2.4 — JWT auth handler

**File:** `src/auth/class-odad-jwt-auth-handler.php`

```php
class ODAD_JWT_Auth_Handler {
    public static function resolve_user( int|false $user_id ): int|false {
        $token = self::extract_bearer_token();
        if ( null === $token ) return $user_id;
        try {
            $payload = ODAD_container()->get( ODAD_JWT::class )->verify_access_token( $token );
            return (int) $payload->sub;
        } catch ( ODAD_Token_Exception ) {
            return false;
        }
    }

    private static function extract_bearer_token(): ?string {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;
        return ( $header && str_starts_with( $header, 'Bearer ' ) )
            ? substr( $header, 7 )
            : null;
    }
}
```

### Task 2.5 — Token exception

**File:** `src/auth/class-odad-token-exception.php`

```php
class ODAD_Token_Exception extends \RuntimeException {
    public function __construct(
        string $message,
        public readonly string $code_slug, // token_expired|token_invalid|token_not_found|token_revoked
    ) {
        parent::__construct( $message );
    }
}
```

---

## Phase 3 — Security

### Task 3.1 — Rate limiting ✅
Inline in `ODAD_Auth_Controller::login()`. Transient key: `odad_login_fails_{md5(REMOTE_ADDR)}`.

### Task 3.2 — Token cleanup cron ✅
Scheduled in `ODAD_Auth_Installer::activate()`. Handler registered in `ODAD_Bootstrapper::build()`.

### Task 3.3 — Unit tests

**Files:** `tests/unit/auth/`

Tests run without WordPress (stub `get_option`, `update_option`, etc. in bootstrap):

| File | Covers |
|---|---|
| `OdadJwtTest.php` | issue+verify round-trip, tampered sig throws, expired throws, wrong type throws, refresh token is 64 hex |
| `OdadTokenStoreTest.php` | store+consume, unknown throws, revoke, revoke_all |
| `OdadAuthControllerTest.php` | login 200, login 401, login 429, refresh 200, logout 204 |

---

## Implementation Order

```
Phase 1 (sequential)
  1.1 Auth Installer → 1.2 Autoloader/hooks → 1.3 Bootstrapper additions

Phase 2 (sequential — each class used by the next)
  2.5 Exception → 2.1 JWT → 2.2 Token Store → 2.4 JWT Handler → 2.3 Auth Controller

Phase 3 (parallel)
  [ 3.1 inline ∥ 3.2 inline ∥ 3.3 unit tests ]
```

---

## Implementation Status

| Task | File | Status |
|---|---|---|
| 1.1 Auth Installer | `src/auth/class-odad-auth-installer.php` | ⬜ Todo |
| 1.2 Autoloader + hooks | `wp-odata-suite.php` | ⬜ Todo |
| 1.3 Bootstrapper additions | `src/bootstrap/class-odad-bootstrapper.php` | ⬜ Todo |
| 2.1 JWT | `src/auth/class-odad-jwt.php` | ⬜ Todo |
| 2.2 Token Store | `src/auth/class-odad-token-store.php` | ⬜ Todo |
| 2.3 Auth Controller | `src/http/class-odad-auth-controller.php` | ⬜ Todo |
| 2.4 JWT Handler | `src/auth/class-odad-jwt-auth-handler.php` | ⬜ Todo |
| 2.5 Token Exception | `src/auth/class-odad-token-exception.php` | ⬜ Todo |
| 3.3 Unit tests | `tests/unit/auth/` | ⬜ Todo |

---

## REST API Reference

### POST `/wp-json/odad/v1/auth/login`

```json
// Request
{ "username": "john", "password": "secret", "device": "iPhone 15" }

// 200 OK
{
  "access_token":  "<jwt>",
  "refresh_token": "<64-hex>",
  "expires_in":    900,
  "user": { "id": 42, "login": "john", "email": "john@example.com", "display_name": "John", "roles": ["subscriber"] }
}

// 401  { "code": "invalid_credentials", "message": "Invalid username or password." }
// 429  { "code": "too_many_attempts",   "message": "Too many login attempts. Try again later." }
```

### POST `/wp-json/odad/v1/auth/refresh`

```json
// Request
{ "refresh_token": "<64-hex>" }

// 200 OK
{ "access_token": "<jwt>", "expires_in": 900 }

// 401  { "code": "invalid_refresh_token", "message": "..." }
```

### POST `/wp-json/odad/v1/auth/logout`

```
Authorization: Bearer <access_token>
```
```json
{ "refresh_token": "<64-hex>" }   // revoke one device
{ "all_devices": true }            // revoke all

// 204 No Content
```
