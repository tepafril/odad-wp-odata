# WP-OData Suite — User Guide

WP-OData Suite exposes your WordPress data as a standards-based **OData v4** REST API. Any tool that speaks OData — Power BI, Excel, Salesforce, SAP, or your own application — can query, filter, and update WordPress content without custom integration work.

---

## Table of Contents

1. [Requirements & Installation](#1-requirements--installation)
2. [What You Get Out of the Box](#2-what-you-get-out-of-the-box)
3. [Exploring the API](#3-exploring-the-api)
4. [Querying Data](#4-querying-data)
5. [Writing Data](#5-writing-data)
6. [Admin Configuration](#6-admin-configuration)
7. [Permissions & Security](#7-permissions--security)
8. [Exposing Custom Database Tables](#8-exposing-custom-database-tables)
9. [Registering Custom Functions & Actions](#9-registering-custom-functions--actions)
10. [Batch & Async Requests](#10-batch--async-requests)
11. [Performance](#11-performance)
12. [Known Limitations](#12-known-limitations)

---

## 1. Requirements & Installation

**Requirements**

- WordPress 6.3 or later
- PHP 8.1 or later

**Installation**

1. Place the `wp-odata-suite` folder in `wp-content/plugins/`.
2. Activate the plugin in **WordPress Admin → Plugins**.
3. That's it. Your OData endpoint is immediately live at:

```
https://yoursite.com/wp-json/odata/v4/
```

No configuration is required to start reading data.

---

## 2. What You Get Out of the Box

Once activated, the following WordPress data is available as OData entity sets:

| Entity Set | WordPress Data | Key |
|---|---|---|
| `Posts` | Blog posts | `ID` |
| `Pages` | Static pages | `ID` |
| `Attachments` | Media library items | `ID` |
| `Users` | WordPress users | `ID` |
| `Categories` | Post categories | `ID` |
| `Tags` | Post tags | `ID` |

Any **Custom Post Types** and **Custom Taxonomies** registered in your theme or other plugins are also detected and exposed automatically.

---

## 3. Exploring the API

### Service Document

The root endpoint lists every available entity set and its URL:

```
GET /wp-json/odata/v4/
```

### Metadata

The metadata endpoint describes every entity type — its properties, data types, and relationships. OData clients use this to understand the schema automatically.

```
GET /wp-json/odata/v4/$metadata          (XML — CSDL format)
GET /wp-json/odata/v4/$metadata?$format=json   (JSON format)
```

You can open either URL in a browser to inspect the schema. Most OData clients fetch `$metadata` automatically on first connection.

---

## 4. Querying Data

All queries are standard HTTP GET requests. OData query options are passed as URL parameters.

### Get a collection

```
GET /wp-json/odata/v4/Posts
```

Returns the first 100 posts (default page size).

### Get a single record by ID

```
GET /wp-json/odata/v4/Posts(123)
```

Returns the post with ID 123.

---

### Pagination — `$top` and `$skip`

| Parameter | Meaning |
|---|---|
| `$top=N` | Return at most N records |
| `$skip=N` | Skip the first N records |

```
GET /wp-json/odata/v4/Posts?$top=25&$skip=50
```

Returns records 51–75. The response includes an `@odata.nextLink` URL when more records exist — follow it to get the next page.

The default page size is **100**. The hard maximum is **1000**. Both are configurable (see [Admin Configuration](#6-admin-configuration)).

---

### Filtering — `$filter`

Filter by any property using standard comparison operators.

**Operators**

| Operator | Meaning | Example |
|---|---|---|
| `eq` | equals | `Status eq 'publish'` |
| `ne` | not equals | `Status ne 'draft'` |
| `gt` | greater than | `ID gt 100` |
| `ge` | greater than or equal | `ID ge 100` |
| `lt` | less than | `ID lt 100` |
| `le` | less than or equal | `ID le 100` |
| `and` | both conditions | `Status eq 'publish' and AuthorID eq 5` |
| `or` | either condition | `Status eq 'draft' or Status eq 'pending'` |
| `not` | negate | `not (Status eq 'trash')` |

**String functions**

| Function | Example |
|---|---|
| `contains(field, value)` | `contains(Title, 'WordPress')` |
| `startswith(field, value)` | `startswith(Title, 'Hello')` |
| `endswith(field, value)` | `endswith(Title, 'World')` |
| `tolower(field)` | `tolower(Status) eq 'publish'` |
| `toupper(field)` | `toupper(Status) eq 'PUBLISH'` |

**Date functions**

| Function | Example |
|---|---|
| `year(field)` | `year(PublishedDate) eq 2024` |
| `month(field)` | `month(PublishedDate) eq 6` |
| `day(field)` | `day(PublishedDate) eq 15` |

**Examples**

```
# Published posts only
GET /wp-json/odata/v4/Posts?$filter=Status eq 'publish'

# Published posts by a specific author
GET /wp-json/odata/v4/Posts?$filter=Status eq 'publish' and AuthorID eq 5

# Posts published after a specific date
GET /wp-json/odata/v4/Posts?$filter=PublishedDate gt 2024-01-01T00:00:00Z

# Posts whose title contains a word
GET /wp-json/odata/v4/Posts?$filter=contains(Title, 'OData')
```

---

### Selecting fields — `$select`

Return only the properties you need, reducing payload size.

```
GET /wp-json/odata/v4/Posts?$select=ID,Title,Status,PublishedDate
```

---

### Sorting — `$orderby`

```
GET /wp-json/odata/v4/Posts?$orderby=PublishedDate desc
GET /wp-json/odata/v4/Posts?$orderby=Title asc
GET /wp-json/odata/v4/Posts?$orderby=PublishedDate desc,Title asc
```

---

### Including related data — `$expand`

Navigation properties let you pull in related entities in a single request. The plugin uses batched loading — expanding Author on 100 posts costs 2 database queries, not 101.

```
# Posts with their author details
GET /wp-json/odata/v4/Posts?$expand=Author

# Posts with author and tags
GET /wp-json/odata/v4/Posts?$expand=Author,Tags
```

---

### Total count — `$count`

Add `$count=true` to include the total number of matching records in the response as `@odata.count`. This is useful for building pagination UI without a separate request.

```
GET /wp-json/odata/v4/Posts?$filter=Status eq 'publish'&$count=true
```

You can also request the count alone:

```
GET /wp-json/odata/v4/Posts/$count
```

---

### Full-text search — `$search`

A basic search across text fields:

```
GET /wp-json/odata/v4/Posts?$search=Hello World
```

Note: `$search` uses simple LIKE matching. For relevance-ranked search, use the `$filter` approach with `contains()`.

---

### Combining options

Query options can be combined freely:

```
GET /wp-json/odata/v4/Posts
  ?$filter=Status eq 'publish'
  &$select=ID,Title,PublishedDate
  &$expand=Author
  &$orderby=PublishedDate desc
  &$top=25
  &$count=true
```

---

### POST-based queries

If your query string would be very long, send it in the request body instead:

```
POST /wp-json/odata/v4/Posts/$query
Content-Type: application/json

{
  "$filter": "Status eq 'publish'",
  "$select": "ID,Title",
  "$top": 25
}
```

---

## 5. Writing Data

Write operations require the requesting user to have appropriate WordPress capabilities (see [Permissions](#7-permissions--security)).

### Create a record

```
POST /wp-json/odata/v4/Posts
Content-Type: application/json

{
  "Title": "My New Post",
  "Content": "Hello, world!",
  "Status": "draft"
}
```

Returns the created record with its assigned ID and a `201 Created` status.

### Update a record (partial)

Send only the fields you want to change:

```
PATCH /wp-json/odata/v4/Posts(123)
Content-Type: application/json

{
  "Status": "publish"
}
```

### Replace a record (full)

Send the complete replacement document:

```
PUT /wp-json/odata/v4/Posts(123)
Content-Type: application/json

{
  "Title": "Updated Title",
  "Content": "New content...",
  "Status": "publish"
}
```

### Delete a record

```
DELETE /wp-json/odata/v4/Posts(123)
```

### Bulk update

Apply a change to all records matching a filter:

```
PATCH /wp-json/odata/v4/Posts?$filter=Status eq 'draft'
Content-Type: application/json

{
  "Status": "publish"
}
```

### Bulk delete

Delete all records matching a filter:

```
DELETE /wp-json/odata/v4/Posts?$filter=Status eq 'trash'
```

Use bulk delete with care — it is not reversible through the API.

### Create with nested records (Deep Insert)

Create a parent and child entities in one request by nesting them in the payload:

```json
POST /wp-json/odata/v4/Posts

{
  "Title": "A Post With Categories",
  "Status": "draft",
  "Categories": [
    { "Name": "Technology" }
  ]
}
```

---

## 6. Admin Configuration

Go to **WordPress Admin → WP-OData Suite** to access the configuration pages.

### Dashboard

Shows the plugin version, the live endpoint URL, and a summary of all registered entity sets with their direct links.

### Entity Settings

For each entity set you can:

- **Enable / Disable** — hide an entity set from the API entirely
- **Label** — change the display name shown in `$metadata`
- **Exposed Properties** — whitelist specific columns (leave empty to expose all)
- **Allow Insert / Update / Delete** — toggle write operations independently
- **Max `$top`** — override the per-entity page size cap
- **Require Authentication** — reject anonymous requests for this entity set

Changes take effect immediately. The metadata cache is automatically cleared when you save.

### Permissions

Map WordPress roles to read/insert/update/delete operations for each entity set. The built-in WordPress roles (Subscriber, Contributor, Author, Editor, Administrator) are shown. Custom roles registered by other plugins appear automatically.

Field-level restrictions can also be configured here — for example, making a `Salary` field visible only to users with the `view_salaries` capability.

---

## 7. Permissions & Security

### How permissions work

Every API request goes through two checks:

1. **Entity-level** — can this user read/write this entity set at all?
2. **Row-level** — which rows is this user allowed to see?

Built-in row-level rules:

- Non-admin users see only published posts, or posts they authored themselves.
- Non-admin users see only approved comments.

### Default capability requirements

If you haven't configured custom permissions, the plugin looks for WordPress capabilities named `wpos_{entityset}_{operation}` — for example, `wpos_posts_read` or `wpos_employees_delete`. Grant these capabilities to roles using a capability management plugin, or configure overrides in the Permissions admin page.

### Field-level access control

Mark sensitive fields so they are only returned for users who have a specific capability. Users without that capability simply don't see the field in responses, and attempts to write to it are silently ignored. Configure this in the schema definition (see [Exposing Custom Tables](#8-exposing-custom-database-tables)) or via the Permissions admin page.

### Authentication

The plugin uses WordPress's built-in REST API authentication. Include an authentication cookie (logged-in session), or use the WordPress Application Passwords feature (Settings → Application Passwords) to generate a token for API clients.

```
Authorization: Basic base64(username:application_password)
```

---

## 8. Exposing Custom Database Tables

See [custom-tables.md](custom-tables.md) for the full guide. Here is the short version.

Add this to your theme's `functions.php` or a small site plugin:

```php
add_action( 'wpos_register_entity_sets', function (
    WPOS_Schema_Registry  $registry,
    WPOS_Adapter_Resolver $resolver
) {
    $adapter = new WPOS_Adapter_Custom_Table(
        table_name:      'employees',   // without the $wpdb->prefix
        entity_set_name: 'Employees',   // OData name (PascalCase)
        key_column:      'id',
    );

    $resolver->register( 'Employees', $adapter );
    $registry->register( 'Employees', $adapter->get_entity_type_definition() );

}, 10, 2 );
```

The adapter inspects the table schema automatically. Your table is then queryable at `/wp-json/odata/v4/Employees` with full filtering, sorting, and pagination support.

Pass a `schema` array to control property names, data types, read-only fields, and capability-gated fields. See [custom-tables.md](custom-tables.md) for complete examples.

---

## 9. Registering Custom Functions & Actions

OData distinguishes between **functions** (read-only, called with GET) and **actions** (state-changing, called with POST).

### Custom Function

```php
add_action( 'wpos_register_functions', function ( $function_registry ) {
    $function_registry->register(
        name:        'MyPlugin.GetPostCount',
        handler:     fn( $params, $user ) => wp_count_posts()->publish,
        parameters:  [],
        return_type: 'Edm.Int32'
    );
} );
```

Call it with:

```
GET /wp-json/odata/v4/MyPlugin.GetPostCount()
```

### Custom Action

```php
add_action( 'wpos_register_actions', function ( $action_registry ) {
    $action_registry->register(
        name:        'MyPlugin.PublishPost',
        handler:     function ( $params, $user ) {
            wp_update_post( [ 'ID' => $params['PostID'], 'post_status' => 'publish' ] );
            return true;
        },
        parameters:  [
            [ 'name' => 'PostID', 'type' => 'Edm.Int32', 'required' => true ],
        ],
        return_type: 'Edm.Boolean'
    );
} );
```

Call it with:

```
POST /wp-json/odata/v4/MyPlugin.PublishPost
Content-Type: application/json

{ "PostID": 123 }
```

Functions and actions can be **bound** to a specific entity set or to a single entity by passing a `binding` argument. See the function/action registry classes for details.

---

## 10. Batch & Async Requests

### Batch requests

Send multiple OData operations in a single HTTP call. This is useful when connecting from environments with high per-request overhead.

```
POST /wp-json/odata/v4/$batch
Content-Type: multipart/mixed; boundary=batch_xyz

--batch_xyz
Content-Type: application/http

GET /wp-json/odata/v4/Posts(1) HTTP/1.1

--batch_xyz
Content-Type: application/http

GET /wp-json/odata/v4/Users(5) HTTP/1.1

--batch_xyz--
```

### Async requests

For long-running queries, add the `Prefer: respond-async` header. The server returns a `202 Accepted` with a job ID. Poll the status endpoint until the result is ready.

```
GET /wp-json/odata/v4/Posts?$filter=...
Prefer: respond-async

→ 202 Accepted
  Location: /wp-json/odata/v4/$status/abc123

GET /wp-json/odata/v4/$status/abc123
→ 200 OK (when complete) or 202 (still processing)
```

---

## 11. Performance

See [performance.md](performance.md) for the full guide. Key points:

- **Add database indexes** on `wp_posts (post_type, post_status)` and `(post_author, post_type)` to speed up most collection queries.
- **Use `$select`** to retrieve only the columns your client needs.
- **`$expand` is batched** — expanding a navigation property on 1000 rows costs 2 queries, not 1001.
- **Metadata is cached** for 24 hours by default. Raise the TTL on stable production sites.
- **Use a persistent object cache** (Redis, Memcached) to keep metadata out of the database entirely.

---

## 12. Known Limitations

Version 0.1.0 has the following gaps relative to the full OData v4.01 specification:

| Feature | Status |
|---|---|
| `$search` with relevance ranking | Basic LIKE matching only |
| `$compute` (derived properties) | Partial support |
| Delta tracking (`$deltatoken`) | Basic support only |
| Real-time / streaming | Not supported |
| WordPress Multisite network endpoints | Not supported |

These are planned for future releases.
