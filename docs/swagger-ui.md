# API Documentation (Swagger UI)

WP-OData Suite ships an embedded Swagger UI that reflects your live schema.
Every entity set you register appears automatically — no manual spec maintenance.

---

## Access

### In the WordPress admin

**WP Admin → WP-OData Suite → API Docs**

The page loads Swagger UI pre-authenticated with your current admin session.
"Try it out" works immediately — no token entry needed.

### Raw OpenAPI JSON

```
GET /wp-json/odata/v4/openapi.json
```

Public endpoint, no authentication required. Returns the full OpenAPI 3.0 spec.

---

## What's documented

| Section | Paths |
|---|---|
| Each registered entity set | `GET/POST /odata/v4/{EntitySet}` and `GET/PATCH/PUT/DELETE /odata/v4/{EntitySet}({key})` |
| OData system endpoints | `/odata/v4/` (service document), `/odata/v4/$metadata`, `/odata/v4/$batch` |
| Authentication | `/odad/v1/auth/login`, `/odad/v1/auth/refresh`, `/odad/v1/auth/logout` |

All OData query parameters (`$filter`, `$select`, `$orderby`, `$top`, `$skip`, `$count`, `$expand`, `$search`) are documented as reusable components and appear on every collection `GET`.

---

## Using "Try it out" in Swagger UI

1. Open **WP Admin → WP-OData Suite → API Docs**.
2. Click any endpoint to expand it.
3. Click **Try it out**, fill in parameters, then **Execute**.

Your WordPress admin session cookie is used automatically. The nonce (`X-WP-Nonce`) is injected into every request by the page — you do not need to authenticate manually.

To test as a different user or test JWT auth, use Postman or curl instead (see below).

---

## Authentication in Swagger UI

Two schemes are documented:

| Scheme | When to use |
|---|---|
| **WpNonce** (`X-WP-Nonce` header) | Admin UI, same-origin requests. Auto-injected by the page. |
| **BearerAuth** (JWT) | Mobile apps, external clients, server-to-server. |

To test JWT in Swagger UI:
1. Use "Try it out" on `POST /odad/v1/auth/login` to get an `access_token`.
2. Click the **Authorize** button (lock icon, top right).
3. Paste the token into the **BearerAuth** field → **Authorize**.
4. Subsequent requests will include `Authorization: Bearer <token>`.

---

## Import into Postman or Insomnia

1. Copy the spec URL: `/wp-json/odata/v4/openapi.json`
2. In Postman: **Import → Link** → paste the URL → **Continue**.
3. In Insomnia: **Create → Import from URL** → paste the URL.

Both tools will generate a full collection with every endpoint, parameter, and request body pre-filled.

---

## Import into code generators

The spec is standard OpenAPI 3.0.3, compatible with any generator:

```bash
# Generate a TypeScript client (openapi-generator-cli)
openapi-generator-cli generate \
  -i https://yoursite.com/wp-json/odata/v4/openapi.json \
  -g typescript-fetch \
  -o ./src/api-client

# Generate a Python client
openapi-generator-cli generate \
  -i https://yoursite.com/wp-json/odata/v4/openapi.json \
  -g python \
  -o ./api-client
```

---

## Cache

The spec is cached as a WordPress transient (`ODAD_openapi_json`) for 24 hours.
It is busted automatically whenever the schema changes — the same event that
invalidates the `$metadata` CSDL cache. You never need to clear it manually.

To force a refresh during development, delete the transient:

```php
delete_transient( 'ODAD_openapi_json' );
```

Or use WP-CLI:

```bash
wp transient delete ODAD_openapi_json
```

---

## Schemas

For each entity set the spec includes:

| Schema name | Contents |
|---|---|
| `{EntitySet}` | All properties (read schema) |
| `{EntitySet}Write` | Writable properties only — the key property is excluded |
| `{EntitySet}Collection` | OData collection wrapper: `@odata.context`, `@odata.count`, `@odata.nextLink`, `value[]` |

Properties marked `nullable: false` in the entity definition carry that constraint into the schema.

---

## Troubleshooting

**Swagger UI page is blank / script error**

The Swagger UI JS bundle lives at `assets/swagger-ui/swagger-ui-bundle.js`.
Confirm it exists and your server serves files from the plugin directory.

**`openapi.json` returns 404**

Flush WordPress rewrite rules: **Settings → Permalinks → Save Changes**.

**"Try it out" returns 401**

Your admin session may have expired. Reload the API Docs page to get a fresh nonce.

**Entity set missing from the spec**

The spec is built from the live schema registry. If an entity set was registered
after the cache was last populated, bust the cache:

```bash
wp transient delete ODAD_openapi_json
```
