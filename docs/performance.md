# Performance Guide

## Recommended Database Indexes

| Table | Index columns | When needed |
|---|---|---|
| `wp_posts` | `(post_type, post_status)` | All Posts/Pages queries |
| `wp_posts` | `(post_author, post_type)` | Author-filtered queries |
| `wp_posts` | `(post_date_gmt)` | Date-range filters |
| `wp_postmeta` | `(post_id, meta_key)` | Meta expansion (`$expand`) |
| `wp_usermeta` | `(user_id, meta_key)` | User meta expansion |
| `wp_term_relationships` | `(term_taxonomy_id)` | Tag/Category queries |

Add these with:
```sql
ALTER TABLE wp_posts ADD INDEX idx_type_status (post_type, post_status);
ALTER TABLE wp_posts ADD INDEX idx_author_type (post_author, post_type);
ALTER TABLE wp_posts ADD INDEX idx_date (post_date_gmt);
```

## Pagination (`$top` / `$skip`)

Default `$top`: 100. Maximum: 1000. These can be configured via WP options:
- `wpos_default_top` — default value for `$top` when not specified (default: 100)
- `wpos_max_top` — hard cap on `$top` (default: 1000)

Example:
```php
update_option('wpos_default_top', 50);
update_option('wpos_max_top', 500);
```

## Metadata Cache

The `$metadata` response (CSDL XML and JSON) is cached as a WP transient for `DAY_IN_SECONDS` (86400 seconds) by default.

On large sites with many CPTs, metadata rebuilds are expensive. Configure a longer TTL:
```php
update_option('wpos_metadata_cache_ttl', WEEK_IN_SECONDS);
```

The cache is automatically busted when:
- An entity set is registered or removed
- Admin entity configuration is saved
- A plugin is activated or deactivated

## `$expand` Query Optimization

The `$expand` compiler uses **batched loading** to avoid N+1 queries:
- One query for the primary collection
- One additional query per expanded navigation property

Example: `GET /odata/v4/Posts?$expand=Author` on 100 posts = **2 queries** total, not 101.

## Object Cache

The plugin stores metadata in WP transients. With a **persistent object cache** (Redis, Memcached), transient reads/writes are much faster than database calls.

Configure via your hosting environment or a plugin such as Redis Object Cache.

## PHP Opcache

The plugin's autoloader traverses multiple directory paths. With **PHP Opcache** enabled, file lookups are cached in memory — warmup cost is paid only on the first request after deployment.

Recommended `opcache.ini` settings:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  ; set to 1 in development
```
