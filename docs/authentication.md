# Authentication

The WP-OData Suite uses **JWT (JSON Web Token)** authentication. There are two token types:

- **Access token** — short-lived (15 min), sent with every API request in the `Authorization` header.
- **Refresh token** — long-lived (30 days), used only to obtain a new access token. Stored securely; revocable per device or globally.

---

## Quick Start

### 1. Login

```http
POST /wp-json/odad/v1/auth/login
Content-Type: application/json

{
  "username": "john",
  "password": "secret",
  "device": "My App on iPhone 15"
}
```

**Response `200 OK`:**

```json
{
  "access_token":  "<jwt>",
  "refresh_token": "<64-hex-string>",
  "expires_in":    900,
  "user": {
    "id":           42,
    "login":        "john",
    "email":        "john@example.com",
    "display_name": "John",
    "roles":        ["subscriber"]
  }
}
```

Store both tokens. Keep the **refresh token private** — treat it like a password.

---

### 2. Call the API

Attach the access token to every request:

```http
GET /wp-json/odad/v1/Posts
Authorization: Bearer <access_token>
```

---

### 3. Refresh when expired

Access tokens expire after 15 minutes. When a request returns `401`, use the refresh token to get a new access token:

```http
POST /wp-json/odad/v1/auth/refresh
Content-Type: application/json

{
  "refresh_token": "<refresh_token>"
}
```

**Response `200 OK`:**

```json
{
  "access_token": "<new_jwt>",
  "expires_in":   900
}
```

> The refresh token is **rotated** on each use — the old one is deleted and you must use the new one on the next refresh. Store the new refresh token each time you call `/auth/refresh`.

Wait — `/auth/refresh` does **not** return a new refresh token. The same refresh token remains valid for 30 days from when it was originally issued. Only the access token is renewed.

---

### 4. Logout

**Revoke a single device:**

```http
POST /wp-json/odad/v1/auth/logout
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "refresh_token": "<refresh_token>"
}
```

**Revoke all devices (logout everywhere):**

```http
POST /wp-json/odad/v1/auth/logout
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "all_devices": true
}
```

Both return `204 No Content` on success.

---

## Token Lifecycle

```
Login
  └─► access_token (15 min) + refresh_token (30 days)
         │
         │  [access_token expires]
         │
         └─► POST /auth/refresh ──► new access_token
                                         │
                                   [refresh_token expires after 30 days]
                                         │
                                   Login again
```

---

## Error Reference

| Status | Code | When |
|--------|------|------|
| `401` | `invalid_credentials` | Wrong username or password |
| `401` | `invalid_refresh_token` | Refresh token not found, expired, or already used |
| `429` | `too_many_attempts` | 5 or more failed logins from the same IP in 15 minutes |

All errors return this shape:

```json
{
  "code":    "error_code",
  "message": "Human-readable description."
}
```

---

## Security Notes

- **Never** send the refresh token in `Authorization` headers. It belongs only in the `refresh_token` request body field.
- The `device` field in login is optional but recommended — it labels the token so users can recognise which sessions are active.
- Access tokens are stateless and cannot be revoked individually. If a token is compromised, it remains valid until it expires (max 15 min). Revoke the associated refresh token to prevent renewal.
- Rate limiting blocks an IP after 5 failed login attempts for 15 minutes.
- On servers where Apache strips the `Authorization` header, make sure `.htaccess` passes it through:
  ```apache
  RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
  ```
