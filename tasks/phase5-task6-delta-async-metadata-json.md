# Task 5.6 — Delta Responses + Async Responses + $metadata JSON CSDL

## Dependencies
- Task 1.5 (metadata builder + cache)
- Task 1.4 (HTTP layer — ODAD_Response)
- Phase 2 adapters (for $metadata entity type output)

## Goal
Three related advanced features that complete Phase 5:
1. Delta responses (`@odata.deltaLink`) — let clients fetch only changed entities
2. Async responses via WP-Cron (`Prefer: respond-async`)
3. JSON CSDL format for `$metadata` (in addition to existing XML)

---

## Feature 1: Delta Responses

### Concept
Client requests `?$deltatoken=...` or follows `@odata.deltaLink` to get only entities
changed since the last request.

### Implementation Approach

**Delta tracking strategy:** Use `post_modified_gmt` / `user_registered` timestamps.
(Full change-log table is out of scope for v1 — use timestamps.)

### `src/query/class-odad-delta-token.php`

```php
class ODAD_Delta_Token {

    /** Encode a delta token from a timestamp */
    public static function encode( \DateTimeInterface $since ): string;

    /** Decode a delta token back to a DateTime */
    public static function decode( string $token ): ?\DateTimeInterface;

    /** Generate a new delta token representing "now" */
    public static function now(): string;
}
```

Tokens are base64-encoded JSON: `{"since": "2024-01-15T10:30:00Z"}`.
Validate tokens to prevent injection.

### Delta Query Flow

When `$deltatoken` is present in the request:
1. Decode the token to get `$since` timestamp.
2. Inject `modified_after = $since` into `ODAD_Query_Context`.
3. Each adapter checks this field and adds a WHERE condition (e.g. `post_modified_gmt > %s`).
4. Include deleted entity stubs (`@removed` entries) in the response.
5. Include `@odata.deltaLink` in the response (new token = now).

### Response Format

```json
{
  "@odata.context": "...",
  "@odata.deltaLink": "/odata/v4/Posts?$deltatoken=eyJzaW5jZSI6...}",
  "value": [
    { "ID": 42, "Title": "Updated Post" },
    { "@removed": { "reason": "deleted" }, "ID": 99 }
  ]
}
```

---

## Feature 2: Async Responses (WP-Cron)

### Concept
Client sends `Prefer: respond-async`. Server accepts the request, queues it as a
WP-Cron job, and returns `202 Accepted` with a status URL.

### Files

**`src/http/class-odad-async-handler.php`**

```php
class ODAD_Async_Handler {

    /** Queue a request as a background job. Returns a job ID. */
    public function queue( ODAD_Request $request, \WP_User $user ): string;

    /** Get the status and result of a queued job. */
    public function get_status( string $job_id ): array;

    /** WP-Cron callback: execute the queued request and store result. */
    public function execute_job( string $job_id ): void;
}
```

**Flow:**
1. Detect `Prefer: respond-async` header in `ODAD_Router`.
2. Call `async_handler->queue($request, $user)` — stores request in a WP option/transient with a unique job ID.
3. Return `202 Accepted` with:
   - `Location: /odata/v4/$status/{job_id}` header
   - Body: `{"@odata.status": "queued"}`
4. WP-Cron fires `ODAD_async_job_{job_id}` hook.
5. `execute_job()` runs the actual request and stores the result in a transient.
6. Client polls `GET /odata/v4/$status/{job_id}` — returns result when ready, or 202 if still processing.

Add route: `GET /odata/v4/$status/(?P<job_id>[a-zA-Z0-9_-]+)`

---

## Feature 3: $metadata JSON CSDL

### Concept
`GET /odata/v4/$metadata?$format=application/json` or with `Accept: application/json`
returns the CSDL in JSON format instead of XML.

### JSON CSDL Format

```json
{
  "$Version": "4.01",
  "$EntityContainer": "WPOData.WPODataService",
  "WPOData": {
    "PostEntityType": {
      "$Kind": "EntityType",
      "$Key": ["ID"],
      "ID": { "$Type": "Edm.Int32", "$Nullable": false },
      "Title": { "$Type": "Edm.String" },
      "Author": {
        "$Kind": "NavigationProperty",
        "$Type": "WPOData.UserEntityType"
      }
    },
    "WPODataService": {
      "$Kind": "EntityContainer",
      "Posts": { "$Collection": true, "$Type": "WPOData.PostEntityType" }
    }
  }
}
```

### Implementation

In `ODAD_Metadata_Builder::get_json()`:
1. Check `$cache->get_json()`. Return if cached.
2. Convert the same internal schema representation to JSON CSDL format.
3. Apply same `ODAD_metadata_entity_types` / `ODAD_metadata_entity_sets` filters.
4. `json_encode()` with `JSON_PRETTY_PRINT`.
5. Cache and return.

The router detects `$format=application/json` or `Accept: application/json` and
calls `get_json()` instead of `get_xml()`.

---

## Acceptance Criteria

### Delta
- `GET /odata/v4/Posts?$deltatoken=...` returns only posts modified after the token's timestamp.
- Response includes `@odata.deltaLink` with a new token encoding the current time.
- Deleted entities appear as `{ "@removed": {...}, "ID": N }` entries.

### Async
- Request with `Prefer: respond-async` returns `202 Accepted` immediately.
- `GET /odata/v4/$status/{job_id}` returns the result once WP-Cron has processed the job.
- Expired or unknown job IDs return 404.

### JSON CSDL
- `GET /odata/v4/$metadata?$format=application/json` returns valid OData v4.01 JSON CSDL.
- JSON CSDL contains all entity types and entity sets registered in the schema.
- JSON CSDL is cached and busted by `ODAD_Event_Schema_Changed`.
