# WP-OData Suite — Agent Task Index

> Each task file is a self-contained prompt for a Claude Code agent.
> Read the task file in full before starting. Each file lists its dependencies.
> All code must follow PSR-4 naming, WordPress coding standards, and the
> three-layer architecture defined in the master plan.

## Canonical Source of Truth
Master plan: `wp-odata-unified-master-plan.md`
Plugin root: `wp-odata-suite/`

---

## Phase 1 — Foundation & Core Engine

| Task | File | Description |
|---|---|---|
| 1.1 | [phase1-task1-plugin-entry-di-container.md](phase1-task1-plugin-entry-di-container.md) | Plugin entry point + DI Container + Bootstrapper scaffold |
| 1.2 | [phase1-task2-event-bus-events.md](phase1-task2-event-bus-events.md) | Event Bus interfaces + all event value objects |
| 1.3 | [phase1-task3-hook-bridge-subscribers.md](phase1-task3-hook-bridge-subscribers.md) | Hook Bridge + subscriber interface + empty subscriber stubs |
| 1.4 | [phase1-task4-http-layer.md](phase1-task4-http-layer.md) | Router, Request, Response, Error (WP REST API boundary) |
| 1.5 | [phase1-task5-schema-registry-metadata-cache.md](phase1-task5-schema-registry-metadata-cache.md) | Schema Registry + Metadata Cache + minimal $metadata CSDL XML |

## Phase 2 — Data Source Adapters

| Task | File | Description |
|---|---|---|
| 2.1 | [phase2-task1-adapter-interface-resolver.md](phase2-task1-adapter-interface-resolver.md) | Adapter interface + Adapter Resolver |
| 2.2 | [phase2-task2-adapter-wp-posts.md](phase2-task2-adapter-wp-posts.md) | WP_Posts adapter (posts, pages, attachments, comments) |
| 2.3 | [phase2-task3-adapter-wp-users.md](phase2-task3-adapter-wp-users.md) | WP_Users adapter (users + usermeta, PII rules) |
| 2.4 | [phase2-task4-adapter-wp-terms.md](phase2-task4-adapter-wp-terms.md) | WP_Terms adapter (categories, tags, taxonomies) |
| 2.5 | [phase2-task5-adapter-cpt-taxonomy-custom.md](phase2-task5-adapter-cpt-taxonomy-custom.md) | CPT adapter + Taxonomy adapter + Custom Table adapter |
| 2.6 | [phase2-task6-schema-init-subscriber.md](phase2-task6-schema-init-subscriber.md) | Schema Init subscriber + Schema Changed subscriber (cache bust) |

## Phase 3 — OData Query Engine

| Task | File | Description |
|---|---|---|
| 3.1 | [phase3-task1-filter-parser.md](phase3-task1-filter-parser.md) | OData $filter tokenizer + recursive descent AST parser |
| 3.2 | [phase3-task2-filter-compiler.md](phase3-task2-filter-compiler.md) | Filter Compiler: AST → SQL WHERE via $wpdb->prepare() |
| 3.3 | [phase3-task3-query-compilers.md](phase3-task3-query-compilers.md) | Select, OrderBy, Search, Compute compilers |
| 3.4 | [phase3-task4-expand-compiler.md](phase3-task4-expand-compiler.md) | Expand compiler (single-level + nested $expand) |
| 3.5 | [phase3-task5-query-engine.md](phase3-task5-query-engine.md) | Query Engine (orchestrates compilers, dispatches events, pagination) |
| 3.6 | [phase3-task6-query-subscribers.md](phase3-task6-query-subscribers.md) | Query Before + Query After subscribers |

## Phase 4 — Role & Permission System

| Task | File | Description |
|---|---|---|
| 4.1 | [phase4-task1-capability-map-permission-engine.md](phase4-task1-capability-map-permission-engine.md) | Capability Map + Permission Engine (entity-level + row-level) |
| 4.2 | [phase4-task2-field-acl.md](phase4-task2-field-acl.md) | Field ACL — property stripping by role |
| 4.3 | [phase4-task3-permission-subscribers.md](phase4-task3-permission-subscribers.md) | Permission Check, Write Before, Write After subscribers |

## Phase 5 — Advanced OData v4.01

| Task | File | Description |
|---|---|---|
| 5.1 | [phase5-task1-deep-insert.md](phase5-task1-deep-insert.md) | Deep Insert handler + events + subscriber |
| 5.2 | [phase5-task2-deep-update.md](phase5-task2-deep-update.md) | Deep Update handler + events + subscriber |
| 5.3 | [phase5-task3-set-operations.md](phase5-task3-set-operations.md) | Set-based PATCH/$each + DELETE/$each (atomic SQL) |
| 5.4 | [phase5-task4-functions-actions.md](phase5-task4-functions-actions.md) | OData Functions + Actions registry + routing |
| 5.5 | [phase5-task5-batch-requests.md](phase5-task5-batch-requests.md) | Batch requests (multipart MIME + JSON format) |
| 5.6 | [phase5-task6-delta-async-metadata-json.md](phase5-task6-delta-async-metadata-json.md) | Delta responses, async (WP-Cron), $metadata JSON CSDL |

## Phase 6 — Admin UI

| Task | File | Description |
|---|---|---|
| 6.1 | [phase6-task1-admin-dashboard.md](phase6-task1-admin-dashboard.md) | Admin dashboard page + WP admin menu |
| 6.2 | [phase6-task2-admin-entity-config.md](phase6-task2-admin-entity-config.md) | Entity Config UI + save flow → schema changed event |
| 6.3 | [phase6-task3-admin-permission-config.md](phase6-task3-admin-permission-config.md) | Permission Config UI (role × entity × operation grid) |

## Phase 7 — Testing, Security & CI

| Task | File | Description |
|---|---|---|
| 7.1 | [phase7-task1-unit-tests.md](phase7-task1-unit-tests.md) | PHPUnit unit tests for all domain services (no WP bootstrap) |
| 7.2 | [phase7-task2-integration-tests.md](phase7-task2-integration-tests.md) | Integration tests: adapters, hooks, admin (WP_UnitTestCase) |
| 7.3 | [phase7-task3-security-hardening.md](phase7-task3-security-hardening.md) | SQL injection, privilege escalation, PII, CSRF hardening |
| 7.4 | [phase7-task4-ci-performance.md](phase7-task4-ci-performance.md) | GitHub Actions CI matrix + performance tuning |
