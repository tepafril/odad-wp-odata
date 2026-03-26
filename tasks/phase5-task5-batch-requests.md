# Task 5.5 — Batch Requests (Multipart MIME + JSON Batch)

## Dependencies
- Task 1.4 (HTTP layer — WPOS_Request, WPOS_Response, router)
- Task 3.5 (query engine — re-used for individual batch item execution)
- Task 5.1–5.3 (write handlers — re-used for batch writes)

## Goal
Implement the `$batch` endpoint supporting both:
- **Multipart MIME batch** (OData v4.0 format)
- **JSON batch** (OData v4.01 format)

---

## Endpoint

```
POST /odata/v4/$batch
```

Content-Type determines format:
- `multipart/mixed; boundary=...` → multipart MIME
- `application/json` → JSON batch

---

## File

### `src/http/class-wpos-batch-handler.php`

```php
class WPOS_Batch_Handler {

    public function __construct(
        private WPOS_Router          $router,
        private WPOS_Permission_Engine $permissions,
    ) {}

    /**
     * Handle a $batch request.
     * Detects format from Content-Type and delegates.
     */
    public function handle( WPOS_Request $request, \WP_User $user ): WP_REST_Response;

    /** Parse and execute multipart MIME batch */
    private function handle_multipart( string $body, string $boundary, \WP_User $user ): WP_REST_Response;

    /** Parse and execute JSON batch */
    private function handle_json( array $batch_body, \WP_User $user ): WP_REST_Response;
}
```

---

## JSON Batch Format (v4.01)

Request body:
```json
{
  "requests": [
    {
      "id": "1",
      "method": "GET",
      "url": "Posts?$filter=Status eq 'publish'&$top=5"
    },
    {
      "id": "2",
      "method": "POST",
      "url": "Posts",
      "headers": { "Content-Type": "application/json" },
      "body": { "Title": "New Post", "Status": "draft" }
    },
    {
      "id": "3",
      "dependsOn": ["2"],
      "method": "DELETE",
      "url": "Posts(${'2'})"
    }
  ]
}
```

Response body:
```json
{
  "responses": [
    { "id": "1", "status": 200, "headers": { ... }, "body": { ... } },
    { "id": "2", "status": 201, "headers": { ... }, "body": { ... } },
    { "id": "3", "status": 204 }
  ]
}
```

---

## Multipart MIME Format (v4.0)

Request:
```
POST /odata/v4/$batch
Content-Type: multipart/mixed; boundary=batch_abc

--batch_abc
Content-Type: application/http

GET /odata/v4/Posts?$top=2 HTTP/1.1

--batch_abc
Content-Type: multipart/mixed; boundary=changeset_xyz

  --changeset_xyz
  Content-Type: application/http

  POST /odata/v4/Posts HTTP/1.1
  Content-Type: application/json

  {"Title":"New Post"}

  --changeset_xyz--
--batch_abc--
```

**Changesets** within multipart batch are atomic: all succeed or all fail.
Individual GET requests outside changesets are independent.

---

## Execution Strategy

For each batch item:
1. Construct an internal `WPOS_Request` from the batch item's method + URL + body.
2. Dispatch through the existing router/query engine/write handler pipeline.
3. Capture the response (status + headers + body).
4. Include in batch response array.

**`dependsOn` (JSON batch):** Execute dependent requests after their dependencies complete.
Replace `${'id'}` references in URLs with the response key from the referenced request.

---

## Limits

- Maximum 100 requests per batch (configurable).
- Maximum changeset size: 50 operations.
- Return 400 if limits exceeded.

---

## Acceptance Criteria

- `POST /odata/v4/$batch` with JSON body executes all requests and returns responses array.
- Response IDs match request IDs.
- `dependsOn` references are resolved and substituted before execution.
- Multipart MIME batch parses correctly and executes each part.
- Changeset in multipart batch is atomic: if one operation fails, all in the changeset are rolled back.
- Independent GET requests in batch are processed independently.
- Batch with > 100 requests returns 400.
