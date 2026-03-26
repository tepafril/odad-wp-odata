# Task 7.3 — Security Hardening

## Dependencies
- All Phase 1–6 implementations complete.
- Task 7.1 (unit tests — add security-specific assertions here)

## Goal
Audit and harden the plugin against the security risks identified in the master plan.
This task is a code review + targeted fixes pass, not new features.

---

## Security Audit Checklist

### 1. SQL Injection (Critical)

**Rule:** Every user-supplied value that reaches `$wpdb` must go through `$wpdb->prepare()`.

**Audit targets:**
- `WPOS_Filter_Compiler` — verify every literal value uses `%s`/`%d`/`%f` placeholder
- `WPOS_Adapter_Custom_Table` — verify all `insert()`, `update()`, `delete()` use `$wpdb->insert()` / `$wpdb->update()` / `$wpdb->delete()` (never raw `$wpdb->query()` with interpolated values)
- `WPOS_Set_Operations` — verify bulk SQL uses `$wpdb->prepare()` for all values
- `WPOS_Orderby_Compiler` — verify direction is only `ASC` or `DESC`, never from user input directly
- `WPOS_Select_Compiler` — verify column names always come from the column map, never raw input

**Add tests:**
- `$filter=Title eq '; DROP TABLE wp_posts; --'` — should produce a safe prepared query
- `$orderby=ID; DROP TABLE wp_posts` — should return a 400 error
- `$select=ID,'; DROP TABLE` — should return 400 error

---

### 2. Unauthorized Data Exposure

**Rule:** Entity-level AND field-level ACL enforced on every response.

**Audit targets:**
- `WPOS_Subscriber_Permission_Check` — verify it fires before every read/write
- `WPOS_Field_ACL::apply()` — verify it runs after every query result
- Users adapter — triple-check `user_pass` exclusion
- `$expand` — verify navigation expansion respects permission check on expanded entity set

**Add tests:**
- Authenticated user without `list_users` cannot see `Email` field on Users
- `$expand=Author` on Posts for an auth user without `list_users` — `Author.Email` must be stripped
- Unauthenticated request returns 403 unless `wpos_allow_public_access` explicitly returns true

---

### 3. PII Leakage

**Audit targets:**
- Error messages — never expose stack traces or internal table names in production
- `$metadata` response — does not expose `user_pass` in property list
- Batch response — each item's error message is sanitized

**Fix:**
In `WPOS_Error`, check `WP_DEBUG` before including detailed messages:
```php
public static function internal( string $message = '' ): WP_REST_Response {
    $detail = defined('WP_DEBUG') && WP_DEBUG ? $message : 'An internal error occurred.';
    return self::make(500, 'InternalError', $detail);
}
```

---

### 4. Over-Fetching / DoS Protection

**Rule:** `$top` default = 100, max = 1000 (enforced in `WPOS_Request`).

**Audit targets:**
- `WPOS_Request` — verify max `$top` enforcement
- Filter depth — add a max nesting depth to `WPOS_Filter_Parser` (default: 20 levels)
- URL length — WP REST API handles this, but document the 8KB limit
- Batch size — max 100 requests per batch (Task 5.5)

**Add `$top` cap test:**
```php
// $top=99999 should result in $top=1000 in the query
```

---

### 5. Privilege Escalation via Deep Insert

**Rule:** Each nested entity permission is checked individually.

**Audit target:** `WPOS_Deep_Insert` and `WPOS_Deep_Update` — verify
`WPOS_Event_Deep_Insert_Nested_Before` is dispatched for EVERY nested entity.

**Add test:**
- User with `edit_posts` but NOT `create_users` cannot deep-insert a Post with a new User
- The post itself should not be created if any nested entity is denied

---

### 6. CSRF on Write Operations

**Rule:** WP nonce required on all non-GET REST requests using cookie authentication.

**Audit target:** `WPOS_Router` — add nonce verification for cookie-auth requests.

```php
// In router, for POST/PATCH/PUT/DELETE:
if ( $this->is_cookie_auth( $request ) ) {
    $nonce = $request->get_header('X-WP-Nonce');
    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return WPOS_Error::forbidden('Invalid nonce.');
    }
}
```

Note: WP REST API already does nonce checking in `WP_REST_Server` for cookie auth.
Verify this is functioning correctly — don't double-implement.

---

### 7. Schema Disclosure

**Rule:** `$metadata` requires authentication unless `wpos_allow_public_access` returns true.

**Audit target:** `WPOS_Router::metadata()` — verify auth check before serving CSDL.

---

### 8. Input Validation

**Audit targets:**
- `WPOS_Request` — validate entity set name is alphanumeric + underscore only
- `WPOS_Request` — validate key value format matches entity type's key property type
- `WPOS_Batch_Handler` — validate batch item `url` does not reference external URLs

---

## Security Test File

Create `tests/unit/security/SecurityTest.php` with all SQL injection, PII, and privilege
escalation tests. These should be pure unit tests (no WP bootstrap needed for most).

---

## Acceptance Criteria

- No raw SQL string interpolation anywhere in the codebase (grep verify: `$wpdb->query(` must have `$wpdb->prepare(` wrapping or use `$wpdb->insert()`/`$wpdb->update()`).
- `user_pass` never appears in any response body (grep + test verify).
- `$top` > 1000 is silently capped at 1000.
- Deep insert denies the full operation if any nested entity permission is denied.
- Security test suite passes.
