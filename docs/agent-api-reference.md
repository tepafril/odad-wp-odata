# WP-OData Suite — AI Agent API Reference

> **Purpose:** This document is the authoritative reference for an AI agent building a React.js frontend against this API. Every schema, URL pattern, request shape, and response envelope is exact. Do not infer — read this document and follow it literally.

---

## 0. Global Constants

```
BASE_URL      = https://{site}/wp-json/odata/v4
CONTENT_TYPE  = application/json
ODATA_VERSION = 4.01
DEFAULT_TOP   = 100       // rows returned when $top is omitted
MAX_TOP       = 1000      // hard server-side cap — never send higher
```

All request bodies must be `Content-Type: application/json`.
All responses are `Content-Type: application/json;odata.metadata=minimal`.

---

## 1. Authentication

The API uses WordPress REST API authentication. Two methods are supported:

### Cookie (browser, logged-in user)
Include `credentials: 'include'` in every `fetch()` call. Also pass a nonce:

```js
// Get nonce from wp_localize_script or REST API discovery
headers: {
  'X-WP-Nonce': wpApiSettings.nonce,
}
```

### Application Password (headless / API client)
Create an application password at: **WP Admin → Users → Profile → Application Passwords**

```js
headers: {
  'Authorization': 'Basic ' + btoa('username:app_password'),
}
```

### Anonymous access
Some entity sets allow unauthenticated reads (site-dependent). All write operations require authentication.

---

## 2. Response Envelopes

Every response follows one of these four shapes. Match on HTTP status code first.

### 2.1 Collection (GET /EntitySet)

```jsonc
{
  "@odata.context": "https://{site}/wp-json/odata/v4/$metadata#Posts",
  "@odata.count": 847,          // present only when ?$count=true
  "@odata.nextLink": "https://{site}/wp-json/odata/v4/Posts?$skip=100&$top=100",  // present only when more pages exist
  "value": [
    { /* entity object */ },
    { /* entity object */ }
  ]
}
```

### 2.2 Single Entity (GET /EntitySet(key))

```jsonc
{
  "@odata.context": "https://{site}/wp-json/odata/v4/$metadata#Posts/$entity",
  "ID": 42,
  "Title": "Hello World",
  // ... all other properties at top level (no "value" wrapper)
}
```

### 2.3 Created Entity (POST → 201)

```jsonc
// Status: 201 Created
// Header → Location: https://{site}/wp-json/odata/v4/Posts(42)
{
  "@odata.context": "...",
  "ID": 42,
  // full entity object
}
```

### 2.4 Empty Success (PATCH / PUT / DELETE → 204)

```
// Status: 204 No Content
// Body: empty
```

### 2.5 Error

```jsonc
// Status: 400 | 401 | 403 | 404 | 500
{
  "error": {
    "code": "BadRequest",        // string code
    "message": "Human-readable error description"
  }
}
```

**Error codes:**

| HTTP | `error.code` | When |
|---|---|---|
| 400 | `BadRequest` | Malformed filter, invalid property, bad body |
| 401 | `Unauthorized` | No credentials or session expired |
| 403 | `Forbidden` | Authenticated but lacks required capability |
| 404 | `NotFound` | Entity set or record does not exist |
| 405 | `MethodNotAllowed` | Write disabled for this entity set |
| 500 | `InternalServerError` | Unexpected server error |

---

## 3. Built-In Entity Sets & Schemas

All property names are **PascalCase** in JSON. Never use snake_case.

### 3.1 Posts

**Endpoint:** `/Posts`
**Key property:** `ID` (Edm.Int32)

```ts
interface Post {
  ID:           number;         // read-only, key
  Title:        string | null;
  Content:      string | null;
  Excerpt:      string | null;
  Status:       string | null;  // 'publish' | 'draft' | 'pending' | 'private' | 'trash'
  Slug:         string | null;
  PublishedDate:string | null;  // ISO 8601, e.g. "2024-06-15T10:00:00Z"
  ModifiedDate: string | null;  // ISO 8601 — read-only
  AuthorID:     number | null;
  ParentID:     number | null;
  MenuOrder:    number | null;
  CommentCount: number | null;  // read-only
  Type:         string | null;  // e.g. 'post'
  GUID:         string | null;
}
```

**Navigation properties (use in `$expand`):**

| Name | Returns | Cardinality |
|---|---|---|
| `Author` | User object | single |
| `Tags` | Tag[] | collection |
| `Categories` | Category[] | collection |
| `Comments` | Comment[] | collection |
| `Meta` | PostMeta[] | collection |

**Writable properties (safe to send in POST/PATCH body):**
`Title`, `Content`, `Excerpt`, `Status`, `Slug`, `PublishedDate`, `AuthorID`, `ParentID`, `MenuOrder`, `Type`, `GUID`

**Never send in write operations:** `ID`, `ModifiedDate`, `CommentCount`

---

### 3.2 Pages

**Endpoint:** `/Pages`
**Schema:** Identical to Posts. `Type` will be `'page'`.
**Navigation properties:** Same as Posts.

---

### 3.3 Attachments

**Endpoint:** `/Attachments`
**Schema:** Identical to Posts. `Type` will be `'attachment'`.

---

### 3.4 Users

**Endpoint:** `/Users`
**Key property:** `ID` (Edm.Int32)

```ts
interface User {
  ID:             number;        // read-only, key
  DisplayName:    string | null;
  RegisteredDate: string | null; // ISO 8601
  Login:          string | null; // requires list_users capability to see
  Email:          string | null; // requires list_users capability to see
  Url:            string | null;
  NiceName:       string | null;
  Status:         number | null; // 0 = active
}
```

**Navigation properties:**

| Name | Returns | Cardinality |
|---|---|---|
| `Posts` | Post[] | collection |
| `Meta` | UserMeta[] | collection |

**Writable properties:** `DisplayName`, `RegisteredDate`, `Login`, `Email`, `Url`, `NiceName`, `Status`
**Password:** Send `user_pass` in the body to set password on create/update. It is **never returned** in any read response.

---

### 3.5 Categories

**Endpoint:** `/Categories`
**Key property:** `ID` (Edm.Int32)

```ts
interface Category {
  ID:          number;        // read-only, key
  Name:        string;
  Slug:        string;
  Description: string | null;
  Count:       number;        // read-only
  ParentID:    number | null;
  Taxonomy:    string;        // 'category'
}
```

**Navigation properties:**

| Name | Returns | Cardinality |
|---|---|---|
| `Posts` | Post[] | collection |
| `Parent` | Category | single |
| `Children` | Category[] | collection |

**Writable properties:** `Name`, `Slug`, `Description`, `ParentID`
**Never send:** `ID`, `Count`, `Taxonomy`

---

### 3.6 Tags

**Endpoint:** `/Tags`
**Key property:** `ID` (Edm.Int32)
**Schema:** Same as Categories. `Taxonomy` will be `'post_tag'`.
**Navigation properties:** Same as Categories.

---

### 3.7 Custom Post Types

Each registered public CPT becomes its own entity set.
**Endpoint:** `/` + PascalCase label, pluralised. Examples:

| CPT `post_type` slug | CPT label | Entity Set |
|---|---|---|
| `book` | Book | `Books` |
| `book_review` | Book Review | `BookReviews` |
| `product` | Product | `Products` |

**Schema:** Inherits all Posts properties and navigation properties.

To discover all available entity sets at runtime: `GET /wp-json/odata/v4/` (service document).

---

## 4. URL Patterns

```
Collection:        GET    {BASE_URL}/{Entity}
Single:            GET    {BASE_URL}/{Entity}({id})
Navigation:        GET    {BASE_URL}/{Entity}({id})/{NavProperty}
Count only:        GET    {BASE_URL}/{Entity}/$count
Create:            POST   {BASE_URL}/{Entity}
Update partial:    PATCH  {BASE_URL}/{Entity}({id})
Replace full:      PUT    {BASE_URL}/{Entity}({id})
Delete:            DELETE {BASE_URL}/{Entity}({id})
Bulk update:       PATCH  {BASE_URL}/{Entity}?$filter=...
Bulk delete:       DELETE {BASE_URL}/{Entity}?$filter=...
Query via POST:    POST   {BASE_URL}/{Entity}/$query
Metadata:          GET    {BASE_URL}/$metadata
Metadata (JSON):   GET    {BASE_URL}/$metadata?$format=json
Service document:  GET    {BASE_URL}/
Batch:             POST   {BASE_URL}/$batch
Async status:      GET    {BASE_URL}/$status/{job_id}
```

**Key format:** Integer keys — no quotes. Example: `/Posts(42)`, not `/Posts('42')`.

---

## 5. Query Options

All query options are URL query string parameters. They are case-sensitive and must start with `$`.

### 5.1 `$top` — page size

```
?$top=25
```
- Default: 100
- Maximum: 1000 (server clamps higher values)
- Use with `$skip` for pagination

### 5.2 `$skip` — offset

```
?$skip=100
```
- Skip the first N records
- Combined with `$top`: `?$top=25&$skip=50` returns records 51–75

### 5.3 `$count` — include total

```
?$count=true
```
- Adds `@odata.count` to the response envelope
- Count reflects all matching records, not just the current page
- Accepted values: `true`, `1` (case-insensitive)

### 5.4 `$filter` — predicate

**Syntax:** `Property Operator Value`
**String values** must be wrapped in single quotes: `'publish'`
**Number values** are bare: `42`
**Date values** are ISO 8601: `2024-01-01T00:00:00Z`
**Boolean values:** `true` or `false`

**Comparison operators:**

| Operator | Meaning |
|---|---|
| `eq` | equal |
| `ne` | not equal |
| `gt` | greater than |
| `ge` | greater than or equal |
| `lt` | less than |
| `le` | less than or equal |

**Logical operators:** `and`, `or`, `not`

**String functions:**

| Function | Example |
|---|---|
| `contains(prop, 'val')` | `contains(Title, 'WordPress')` |
| `startswith(prop, 'val')` | `startswith(Slug, 'hello')` |
| `endswith(prop, 'val')` | `endswith(Title, 'Guide')` |
| `tolower(prop)` | `tolower(Status) eq 'publish'` |
| `toupper(prop)` | `toupper(Status) eq 'PUBLISH'` |
| `length(prop)` | `length(Title) gt 10` |
| `trim(prop)` | `trim(Title) eq 'Hello'` |

**Date functions (return number):**

| Function | Example |
|---|---|
| `year(prop)` | `year(PublishedDate) eq 2024` |
| `month(prop)` | `month(PublishedDate) eq 6` |
| `day(prop)` | `day(PublishedDate) eq 15` |
| `hour(prop)` | `hour(PublishedDate) gt 8` |

**Filter examples:**

```
Status eq 'publish'
AuthorID eq 5 and Status eq 'publish'
Status eq 'draft' or Status eq 'pending'
not (Status eq 'trash')
PublishedDate gt 2024-01-01T00:00:00Z
contains(Title, 'React')
year(PublishedDate) eq 2024 and month(PublishedDate) eq 6
ID ge 100 and ID le 200
```

### 5.5 `$select` — projection

Comma-separated property names. No spaces around commas.

```
?$select=ID,Title,Status,PublishedDate
?$select=ID,Title,Author
```

Omitting `$select` returns all properties.

### 5.6 `$expand` — related data

Comma-separated navigation property names. Case must match exactly.

```
?$expand=Author
?$expand=Author,Tags,Categories
?$expand=Tags($select=ID,Name)   // expand with nested $select
```

Available navigation properties per entity set — see Section 3.

### 5.7 `$orderby` — sorting

```
?$orderby=PublishedDate desc
?$orderby=Title asc
?$orderby=PublishedDate desc,Title asc   // multi-column
```

Direction: `asc` (default) or `desc`. Omitting direction defaults to `asc`.

### 5.8 `$search` — full text

```
?$search=hello world
```

Performs a basic LIKE match across text fields. For precise filtering, prefer `$filter=contains(...)`.

### 5.9 Combining options

URL-encode the full query string. All options can be combined:

```
/wp-json/odata/v4/Posts
  ?$filter=Status eq 'publish'
  &$select=ID,Title,PublishedDate,AuthorID
  &$expand=Author
  &$orderby=PublishedDate desc
  &$top=25
  &$skip=0
  &$count=true
```

---

## 6. CRUD Operations — Exact Patterns

### 6.1 List (with pagination)

```js
async function fetchPage(entity, page = 0, pageSize = 25, filter = '') {
  const params = new URLSearchParams({
    $top: pageSize,
    $skip: page * pageSize,
    $count: true,
    ...(filter && { $filter: filter }),
  });
  const res = await fetch(`${BASE_URL}/${entity}?${params}`, {
    headers: { 'X-WP-Nonce': nonce },
    credentials: 'include',
  });
  if (!res.ok) throw await res.json();
  return res.json();
  // returns: { "@odata.count": N, "value": [...], "@odata.nextLink": "..." }
}
```

### 6.2 Fetch single record

```js
async function fetchOne(entity, id) {
  const res = await fetch(`${BASE_URL}/${entity}(${id})`, {
    headers: { 'X-WP-Nonce': nonce },
    credentials: 'include',
  });
  if (!res.ok) throw await res.json();
  return res.json();
  // returns: { "ID": N, "Title": "...", ... }
}
```

### 6.3 Create

```js
async function create(entity, data) {
  const res = await fetch(`${BASE_URL}/${entity}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  if (!res.ok) throw await res.json();
  // res.status === 201
  // res.headers.get('Location') → URL of new entity
  return res.json(); // full created entity
}
```

### 6.4 Update (partial)

```js
async function update(entity, id, patch) {
  const res = await fetch(`${BASE_URL}/${entity}(${id})`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'include',
    body: JSON.stringify(patch),
  });
  if (!res.ok) throw await res.json();
  // res.status === 204, no body
}
```

### 6.5 Delete

```js
async function remove(entity, id) {
  const res = await fetch(`${BASE_URL}/${entity}(${id})`, {
    method: 'DELETE',
    headers: { 'X-WP-Nonce': nonce },
    credentials: 'include',
  });
  if (!res.ok) throw await res.json();
  // res.status === 204, no body
}
```

### 6.6 Fetch navigation property

```js
// GET /Posts(42)/Tags
async function fetchNav(entity, id, navProperty, queryOptions = {}) {
  const params = new URLSearchParams(queryOptions);
  const qs = params.toString() ? '?' + params : '';
  const res = await fetch(`${BASE_URL}/${entity}(${id})/${navProperty}${qs}`, {
    headers: { 'X-WP-Nonce': nonce },
    credentials: 'include',
  });
  if (!res.ok) throw await res.json();
  return res.json();
}
```

### 6.7 Count only

```js
async function count(entity, filter = '') {
  const params = filter ? `?$filter=${encodeURIComponent(filter)}` : '';
  const res = await fetch(`${BASE_URL}/${entity}/$count${params}`, {
    headers: { 'X-WP-Nonce': nonce },
    credentials: 'include',
  });
  if (!res.ok) throw await res.json();
  return parseInt(await res.text(), 10); // returns plain integer, not JSON
}
```

### 6.8 POST-based query (long filter strings)

```js
async function queryPost(entity, options) {
  const res = await fetch(`${BASE_URL}/${entity}/$query`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'include',
    body: JSON.stringify(options),
    // options shape: { "$filter": "...", "$select": "...", "$top": 25, "$skip": 0, "$count": true }
  });
  if (!res.ok) throw await res.json();
  return res.json();
}
```

---

## 7. Pagination Algorithm

The server returns `@odata.nextLink` when more records exist. The client may either:

**Option A — offset-based (simple)**

```
page 0: ?$top=25&$skip=0
page 1: ?$top=25&$skip=25
page N: ?$top=25&$skip=(N * 25)
```

Total pages = `Math.ceil(total / pageSize)` where `total` comes from `@odata.count`.

**Option B — follow `@odata.nextLink` (cursor-based)**

```js
let url = `${BASE_URL}/Posts?$top=25`;
while (url) {
  const page = await fetch(url, { credentials: 'include' }).then(r => r.json());
  process(page.value);
  url = page['@odata.nextLink'] ?? null;
}
```

---

## 8. Common Filter Recipes

```
# Published posts only
$filter=Status eq 'publish'

# Posts by a specific author
$filter=AuthorID eq 5

# Posts in a date range
$filter=PublishedDate ge 2024-01-01T00:00:00Z and PublishedDate lt 2025-01-01T00:00:00Z

# Search title
$filter=contains(Title, 'React')

# Exclude trashed
$filter=Status ne 'trash'

# User by email (requires list_users capability)
$filter=Email eq 'user@example.com'

# Categories with posts
$filter=Count gt 0

# Top-level categories only
$filter=ParentID eq 0
```

---

## 9. React Hook Pattern

Minimal reusable hook for OData collection queries:

```tsx
import { useState, useEffect, useCallback } from 'react';

const BASE_URL = '/wp-json/odata/v4';
const NONCE = (window as any).wpApiSettings?.nonce ?? '';

interface ODataCollection<T> {
  value: T[];
  count: number;
  nextLink: string | null;
  loading: boolean;
  error: string | null;
}

function useODataList<T>(
  entity: string,
  options: {
    filter?: string;
    select?: string;
    expand?: string;
    orderby?: string;
    top?: number;
    skip?: number;
  } = {}
): ODataCollection<T> & { refetch: () => void } {
  const [state, setState] = useState<ODataCollection<T>>({
    value: [],
    count: 0,
    nextLink: null,
    loading: true,
    error: null,
  });

  const load = useCallback(async () => {
    setState(s => ({ ...s, loading: true, error: null }));
    try {
      const params = new URLSearchParams({ $count: 'true' });
      if (options.filter)  params.set('$filter',  options.filter);
      if (options.select)  params.set('$select',  options.select);
      if (options.expand)  params.set('$expand',  options.expand);
      if (options.orderby) params.set('$orderby', options.orderby);
      if (options.top  != null) params.set('$top',  String(options.top));
      if (options.skip != null) params.set('$skip', String(options.skip));

      const res = await fetch(`${BASE_URL}/${entity}?${params}`, {
        headers: { 'X-WP-Nonce': NONCE },
        credentials: 'include',
      });
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err?.error?.message ?? `HTTP ${res.status}`);
      }
      const data = await res.json();
      setState({
        value:    data.value ?? [],
        count:    data['@odata.count'] ?? 0,
        nextLink: data['@odata.nextLink'] ?? null,
        loading:  false,
        error:    null,
      });
    } catch (e: any) {
      setState(s => ({ ...s, loading: false, error: e.message }));
    }
  }, [entity, JSON.stringify(options)]);

  useEffect(() => { load(); }, [load]);
  return { ...state, refetch: load };
}

// Usage:
// const { value: posts, count, loading } = useODataList<Post>('Posts', {
//   filter: "Status eq 'publish'",
//   orderby: 'PublishedDate desc',
//   top: 25,
//   skip: 0,
//   expand: 'Author',
// });
```

---

## 10. TypeScript Interfaces (Copy-Paste Ready)

```ts
export interface ODataCollection<T> {
  '@odata.context': string;
  '@odata.count'?: number;
  '@odata.nextLink'?: string;
  value: T[];
}

export interface ODataEntity {
  '@odata.context': string;
}

export interface ODataError {
  error: {
    code: string;
    message: string;
  };
}

export interface Post extends ODataEntity {
  ID: number;
  Title: string | null;
  Content: string | null;
  Excerpt: string | null;
  Status: 'publish' | 'draft' | 'pending' | 'private' | 'trash' | string;
  Slug: string | null;
  PublishedDate: string | null;
  ModifiedDate: string | null;
  AuthorID: number | null;
  ParentID: number | null;
  MenuOrder: number | null;
  CommentCount: number | null;
  Type: string | null;
  GUID: string | null;
  // When $expand=Author:
  Author?: User;
  // When $expand=Tags:
  Tags?: Tag[];
  // When $expand=Categories:
  Categories?: Category[];
}

export interface User extends ODataEntity {
  ID: number;
  DisplayName: string | null;
  RegisteredDate: string | null;
  Login: string | null;
  Email: string | null;
  Url: string | null;
  NiceName: string | null;
  Status: number | null;
}

export interface Category extends ODataEntity {
  ID: number;
  Name: string;
  Slug: string;
  Description: string | null;
  Count: number;
  ParentID: number | null;
  Taxonomy: string;
}

export interface Tag extends ODataEntity {
  ID: number;
  Name: string;
  Slug: string;
  Description: string | null;
  Count: number;
  ParentID: number | null;
  Taxonomy: string;
}

// Generic write payloads (omit read-only fields)
export type CreatePost = Omit<Post, '@odata.context' | 'ID' | 'ModifiedDate' | 'CommentCount'>;
export type PatchPost  = Partial<CreatePost>;
export type CreateUser = Omit<User, '@odata.context' | 'ID'> & { user_pass?: string };
export type PatchUser  = Partial<CreateUser>;
```

---

## 11. Rules the Agent Must Follow

1. **Never send read-only properties** (`ID`, `ModifiedDate`, `CommentCount`, `Count`, `Taxonomy`) in POST/PATCH bodies.
2. **Never quote integer keys** in URLs. Use `/Posts(42)`, not `/Posts('42')`.
3. **Always URL-encode** `$filter` values when building URLs manually.
4. **Check HTTP status before reading body.** 204 responses have no body — do not call `.json()`.
5. **Respect pagination.** Never set `$top` above 1000. Default is 100 if omitted.
6. **Property names are PascalCase** — always `Title`, never `title` or `post_title`.
7. **Single-entity responses have no `value` wrapper.** `GET /Posts(42)` returns `{ "ID": 42, "Title": "..." }`, not `{ "value": { ... } }`.
8. **`@odata.count` is only present** when `$count=true` was sent on the request.
9. **`@odata.nextLink` is only present** when the result set has more pages. Its absence means the last page was returned.
10. **The `$expand` navigation property names are fixed** — use exact names from Section 3. Wrong case returns a 400 error.

---

## 12. Discovering the Schema at Runtime

If the entity set list or property names are unknown, fetch the machine-readable schema:

```js
// JSON metadata (recommended for agents)
const meta = await fetch(`${BASE_URL}/$metadata?$format=json`, {
  credentials: 'include',
}).then(r => r.json());

// Service document — list all entity sets and their URLs
const svc = await fetch(`${BASE_URL}/`, {
  credentials: 'include',
}).then(r => r.json());
// svc.value = [{ name: 'Posts', url: 'Posts' }, ...]
```

Parse these responses to dynamically discover entity set names, property names, and types before generating UI or queries.
