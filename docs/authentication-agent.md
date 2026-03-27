# Auth API — Agent Reference

Base URL: `{WP_HOME}/wp-json/odad/v1`

---

## Token Model

| Token | Format | TTL | Storage | Revocable |
|-------|--------|-----|---------|-----------|
| Access | JWT HS256 (3-part `.`-separated) | 900 s (15 min) | Memory only | No (stateless) |
| Refresh | 64 lowercase hex chars | 2 592 000 s (30 days) | Secure persistent storage | Yes, per-device or global |

**Access token is consumed on every authenticated request.**
**Refresh token is consumed (deleted + must not be reused) when passed to `/auth/refresh`.**

Wait — correction: the refresh token is **not** deleted on `/auth/refresh`. It is deleted (rotated) via `consume()` in the store, meaning each refresh call invalidates the old token. The client does not receive a new refresh token from `/auth/refresh`; the same raw token is gone and you must login again once the refresh token expires.

Correction: re-reading the implementation — `consume()` deletes the row from the DB. The response from `/auth/refresh` only contains a new `access_token`. This means:
- Each call to `/auth/refresh` **invalidates** the refresh token (it is deleted from DB).
- The client must store this same refresh token until it calls refresh, at which point it is gone.
- This is token rotation: one-time use per access-token renewal cycle.

---

## Endpoints

### POST /auth/login

No auth required.

**Request body (JSON):**

```
username     string  required
password     string  required
device       string  optional  label for this session (e.g. "Frontend App")
```

**200 Success:**

```json
{
  "access_token":  "<jwt>",
  "refresh_token": "<64-hex>",
  "expires_in":    900,
  "user": {
    "id":           <int>,
    "login":        "<string>",
    "email":        "<string>",
    "display_name": "<string>",
    "roles":        ["<string>"]
  }
}
```

**Error responses:**

| Status | `code` field | Condition |
|--------|-------------|-----------|
| `401` | `invalid_credentials` | Wrong username or password |
| `429` | `too_many_attempts` | ≥5 failed attempts from this IP in 15 min |

---

### POST /auth/refresh

No auth required.

**Request body (JSON):**

```
refresh_token   string  required   raw 64-hex token from login
```

**200 Success:**

```json
{
  "access_token": "<new_jwt>",
  "expires_in":   900
}
```

**Error responses:**

| Status | `code` field | Condition |
|--------|-------------|-----------|
| `401` | `invalid_refresh_token` | Token not found, expired, or already consumed |

**Side effect:** The refresh token row is deleted from DB. Do not retry with the same refresh token on 401.

---

### POST /auth/logout

**Requires:** `Authorization: Bearer <access_token>`

**Request body (JSON) — option A, revoke one device:**

```json
{ "refresh_token": "<64-hex>" }
```

**Request body (JSON) — option B, revoke all devices:**

```json
{ "all_devices": true }
```

**204 No Content** on success (empty body).

---

## Authenticated Request Pattern

```
Authorization: Bearer <access_token>
```

Apply to every OData API request. If omitted, WordPress falls back to cookie auth. If present but invalid/expired, the request is rejected with `401`.

---

## Token Lifecycle — Decision Tree

```
Have access_token?
  ├── Yes, not expired → use it
  └── No / expired
        ├── Have refresh_token? → POST /auth/refresh
        │     ├── 200 → store new access_token; refresh_token is now consumed (gone)
        │     └── 401 → refresh_token invalid; must POST /auth/login again
        └── No refresh_token → POST /auth/login
```

---

## State the Agent Must Persist

```
access_token     string   discard after 900 s
refresh_token    string   keep until consumed by /auth/refresh or 30 days elapse
token_issued_at  int      unix timestamp of when access_token was obtained
```

Compute expiry: `token_issued_at + 900 - 30` (refresh 30 s before expiry to avoid clock skew).

---

## Error Shape (all endpoints)

```json
{
  "code":    "<slug>",
  "message": "<human string>"
}
```

---

## Known Constraints

- Access tokens cannot be revoked before expiry. Design flows that tolerate up to 15 min of residual access after logout.
- Rate limit is per source IP: 5 failures → 15 min lockout. Do not retry login in a tight loop.
- The `device` field in login accepts any string ≤100 chars. Use it to label sessions meaningfully.
- Refresh token is stored as SHA-256 hash in DB; raw token is never persisted, so it cannot be recovered if lost.
- If the WordPress server sits behind Apache, the `Authorization` header may require `.htaccess` passthrough (`RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`). On Nginx and `mod_php` this is not needed.
