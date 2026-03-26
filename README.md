# WP-OData Suite — Unified Master Plan
## Reconciled Implementation + Hooks & Separation of Concerns

> **Document Type:** Unified Master Plan (Canonical Reference)
> **Supersedes:** `wp-odata-implementation-plan.md` + `wp-odata-hooks-separation-of-concerns.md`
> **Status:** Authoritative — all development decisions reference this document
> **OData Target:** OASIS OData v4.01 Standard (April 23, 2020)

---

## Table of Contents

1. [Project Overview & Goals](#1-project-overview--goals)
2. [Resolved Conflicts from Previous Documents](#2-resolved-conflicts-from-previous-documents)
3. [Canonical Architecture](#3-canonical-architecture)
4. [Canonical Folder Structure](#4-canonical-folder-structure)
5. [Three-Layer Hook Architecture (Canonical)](#5-three-layer-hook-architecture-canonical)
6. [Canonical Hook & Filter Registry](#6-canonical-hook--filter-registry)
7. [Canonical Event Catalogue](#7-canonical-event-catalogue)
8. [Adapter Resolver & Adapter Event Integration](#8-adapter-resolver--adapter-event-integration)
9. [Deep Insert / Deep Update / Set Operations Events](#9-deep-insert--deep-update--set-operations-events)
10. [Admin UI Hook Coverage](#10-admin-ui-hook-coverage)
11. [Metadata Cache Invalidation Strategy](#11-metadata-cache-invalidation-strategy)
12. [Dependency Injection Container](#12-dependency-injection-container)
13. [Phase Breakdown (Reconciled)](#13-phase-breakdown-reconciled)
14. [OData v4.01 Feature Implementation Map](#14-odata-v401-feature-implementation-map)
15. [Data Source Strategy](#15-data-source-strategy)
16. [URL Routing Design](#16-url-routing-design)
17. [Metadata ($metadata) Design](#17-metadata-metadata-design)
18. [Role & Permission Design](#18-role--permission-design)
19. [Security Considerations](#19-security-considerations)
20. [Tech Stack](#20-tech-stack)
21. [Milestones & Timeline](#21-milestones--timeline)
22. [Open Questions & Risks](#22-open-questions--risks)
23. [Appendix A — Full Hook Reference](#appendix-a--full-hook-reference)
24. [Appendix B — Example OData Requests](#appendix-b--example-odata-requests)
25. [Appendix C — Key Rules Quick Reference](#appendix-c--key-rules-quick-reference)

---

## 1. Project Overview & Goals

**WP-OData Suite** is a WordPress plugin that exposes WordPress data — native
tables, Custom Post Types (CPTs), Taxonomies, and custom database tables — as a
fully compliant **OData v4.01 REST API**. It enables any OData-compatible client
(Power BI, Excel, Salesforce, SAP, custom apps) to query, filter, and manipulate
WordPress data using the OData standard.

### Goals

- Implement all major OData v4.01 features on top of WordPress data
- Support native WordPress tables (`wp_posts`, `wp_users`, `wp_terms`, etc.)
- Support Custom Post Types and Taxonomies as first-class OData Entity Sets
- Support arbitrary custom DB tables (e.g., `wp_employees`, `wp_orders`)
- Enforce WordPress role/capability-based permissions on all OData endpoints
- Be extensible by other plugins via a clean, documented WP hook/filter API
- Maintain strict separation of concerns so domain logic is testable without WordPress

### Non-Goals (v1.0)

- Full-text `$search` with advanced relevance scoring
- Real-time / streaming data
- Multi-site network-level endpoints

---

## 2. Resolved Conflicts from Previous Documents

This section documents every conflict found between the two prior documents
and the canonical decision made for each.

### Resolution 1 — Folder Structure

**Conflict:** Impl Plan used `includes/`, Hooks doc used `src/`.

**Decision:** Use `src/` as the source root. It is cleaner, framework-standard,
and the Hooks doc's three-layer structure requires it. All classes from the
Impl Plan's `includes/` are remapped into `src/` subfolders below.

---

### Resolution 2 — Hook Names (Canonical Registry)

**Conflict:** Both documents defined overlapping but different hook names.
Several were renamed, split, or had type changed (action vs. filter).

**Decision:** The table below is the single source of truth. All hook names
from both prior documents are consolidated here. No hook name from a prior
document that is not in this table should be used.

> See **Section 6** for the full canonical hook registry.

Key resolutions:
- `wpos_before_query` (action in Impl Plan) → replaced by `wpos_query_context` **(filter)** — filters are more useful than actions for query modification
- `wpos_after_query` (action in Impl Plan) → replaced by `wpos_query_results` **(filter)**
- `wpos_register_custom_table` (Impl Plan) → merged into `wpos_register_entity_sets` (Hooks doc) — one unified registration point
- `wpos_allow_anonymous_access` (Impl Plan) → renamed `wpos_allow_public_access` (Hooks doc) — more precise wording
- `wpos_can_read/insert/update/delete` filters (Hooks doc only) → **adopted**, added to canonical registry
- `wpos_before_insert` / `wpos_before_update` (Hooks doc only) → **adopted**
- `wpos_inserted` / `wpos_updated` / `wpos_deleted` (Hooks doc only) → **adopted**

---

### Resolution 3 — DI Container

**Conflict:** Impl Plan had no DI container. Hooks doc introduced `WPOS_Container`
without placing it in the phase plan.

**Decision:** `WPOS_Container` is a **Phase 1 deliverable**. It is the backbone
of the three-layer architecture and must be built before any other component.
See Section 12 for the canonical container design.

---

### Resolution 4 — Adapter Resolver

**Conflict:** Hooks doc referenced `WPOS_Adapter_Resolver` in code examples but
never defined it. Impl Plan had no adapter resolver at all.

**Decision:** `WPOS_Adapter_Resolver` is defined in **Section 8** of this document.
It is a Phase 2 deliverable, built alongside the adapters.

---

### Resolution 5 — Deep Insert / Deep Update / Set Operations Events

**Conflict:** Impl Plan defined these as separate classes. Hooks doc only had a
single generic `WPOS_Event_Write_Before/After`, insufficient for nested entity
lifecycle and bulk operations.

**Decision:** New dedicated events are defined in **Section 9**:
`WPOS_Event_Deep_Insert_*`, `WPOS_Event_Deep_Update_*`, `WPOS_Event_Set_Operation_*`.

---

### Resolution 6 — Admin UI Hooks

**Conflict:** Impl Plan had Phase 6 Admin UI. Hooks doc had no admin hook coverage,
risking `apply_filters()` calls leaking into admin classes.

**Decision:** Admin UI classes route through the same Hook Bridge. Admin-specific
hooks are defined in **Section 10**.

---

### Resolution 7 — Metadata Cache Invalidation

**Conflict:** Impl Plan defined `WPOS_Metadata_Cache` but had no invalidation
strategy. Hooks doc exposed `wpos_metadata_entity_types/sets` filters but didn't
address cache busting when schema changed via a hook.

**Decision:** Cache invalidation strategy defined in **Section 11**.
A `WPOS_Event_Schema_Changed` event triggers cache invalidation automatically.

---

## 3. Canonical Architecture

```
┌───────────────────────────────────────────────────────────────────────────┐
│  External Plugins (WP-HR Suite, WooCommerce, etc.)                        │
│  add_action('wpos_register_entity_sets', ...)                             │
│  add_filter('wpos_can_read', ...)                                         │
└──────────────────────────────┬────────────────────────────────────────────┘
                               │  WordPress hook system
                               ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  WordPress Layer                                                          │
│  WP REST API  ──►  WPOS_Router  ──►  WPOS_Request / WPOS_Response        │
└──────────────────────────────┬────────────────────────────────────────────┘
                               │
                               ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  LAYER 1: Hook Bridge  (src/hooks/)                          [WP-aware]   │
│  WPOS_Hook_Bridge   — ONLY class calling add_filter/apply_filters         │
│  WPOS_Subscriber_*  — one per concern, thin bridge: WP ↔ Event Bus       │
└──────────────────────────────┬────────────────────────────────────────────┘
                               │  dispatch(Event)
                               ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  LAYER 2: Internal Event Bus  (src/events/)               [Pure PHP]      │
│  WPOS_Event_Bus    — pure PHP dispatcher                                  │
│  WPOS_Event_*      — plain value objects (data carriers, no logic)        │
└──────────────────────────────┬────────────────────────────────────────────┘
                               │  calls methods on
                               ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  LAYER 3: Domain Services  (src/query/, src/write/,       [Pure PHP]      │
│                             src/permissions/, src/metadata/)              │
│                                                                           │
│  WPOS_Query_Engine      WPOS_Write_Handler    WPOS_Permission_Engine      │
│  WPOS_Deep_Insert       WPOS_Deep_Update      WPOS_Set_Operations         │
│  WPOS_Filter_Compiler   WPOS_Metadata_Builder WPOS_Field_ACL              │
└──────────────────────────────┬────────────────────────────────────────────┘
                               │  resolved via
                               ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  Adapter Layer  (src/adapters/)                           [WP-aware]      │
│  WPOS_Adapter_Resolver                                                    │
│  WPOS_Adapter_WP_Posts   WPOS_Adapter_WP_Users   WPOS_Adapter_WP_Terms   │
│  WPOS_Adapter_CPT        WPOS_Adapter_Taxonomy   WPOS_Adapter_Custom_Table│
└──────────────────────────────┬────────────────────────────────────────────┘
                               │
                               ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  WordPress DB ($wpdb)                                                     │
└───────────────────────────────────────────────────────────────────────────┘

RULE: Arrows only point downward. Layer 3 never imports from Layer 1 or 2.
      Adapters are WP-aware but report through Layer 3 interfaces only.
```

---

## 4. Canonical Folder Structure

This is the single authoritative file/folder layout. It merges both prior
documents and adds missing pieces.

```
wp-odata-suite/
│
├── wp-odata-suite.php                        # Plugin entry point
├── readme.txt
├── uninstall.php
│
├── src/
│   │
│   ├── bootstrap/
│   │   ├── class-wpos-container.php          # DI container (Phase 1)
│   │   └── class-wpos-bootstrapper.php       # Wires container + bridge
│   │
│   ├── http/                                 # WP REST API boundary
│   │   ├── class-wpos-router.php             # REST route registration
│   │   ├── class-wpos-request.php            # Parses incoming OData request
│   │   ├── class-wpos-response.php           # Formats OData JSON response
│   │   └── class-wpos-error.php              # OData error format
│   │
│   ├── hooks/                                # LAYER 1 — WP boundary
│   │   ├── class-wpos-hook-bridge.php        # ONLY file with add_filter/apply_filters
│   │   └── subscribers/
│   │       ├── class-wpos-subscriber-schema-init.php
│   │       ├── class-wpos-subscriber-schema-changed.php     # cache invalidation
│   │       ├── class-wpos-subscriber-permission-check.php
│   │       ├── class-wpos-subscriber-query-before.php
│   │       ├── class-wpos-subscriber-query-after.php
│   │       ├── class-wpos-subscriber-write-before.php
│   │       ├── class-wpos-subscriber-write-after.php
│   │       ├── class-wpos-subscriber-deep-insert.php
│   │       ├── class-wpos-subscriber-deep-update.php
│   │       ├── class-wpos-subscriber-set-operation.php
│   │       ├── class-wpos-subscriber-metadata-build.php
│   │       └── class-wpos-subscriber-admin-config-saved.php
│   │
│   ├── events/                               # LAYER 2 — Internal event bus
│   │   ├── class-wpos-event-bus.php
│   │   ├── interface-wpos-event.php
│   │   ├── interface-wpos-stoppable-event.php
│   │   ├── interface-wpos-event-listener.php
│   │   └── events/
│   │       ├── class-wpos-event-wp-init.php
│   │       ├── class-wpos-event-rest-init.php
│   │       ├── class-wpos-event-schema-register.php
│   │       ├── class-wpos-event-schema-changed.php          # NEW — triggers cache bust
│   │       ├── class-wpos-event-metadata-build.php
│   │       ├── class-wpos-event-query-before.php
│   │       ├── class-wpos-event-query-after.php
│   │       ├── class-wpos-event-write-before.php
│   │       ├── class-wpos-event-write-after.php
│   │       ├── class-wpos-event-permission-check.php
│   │       ├── class-wpos-event-deep-insert-before.php      # NEW
│   │       ├── class-wpos-event-deep-insert-after.php       # NEW
│   │       ├── class-wpos-event-deep-update-before.php      # NEW
│   │       ├── class-wpos-event-deep-update-after.php       # NEW
│   │       ├── class-wpos-event-set-operation-before.php    # NEW
│   │       └── class-wpos-event-set-operation-after.php     # NEW
│   │
│   ├── query/                                # LAYER 3 — Domain: query
│   │   ├── class-wpos-query-engine.php
│   │   ├── class-wpos-query-context.php
│   │   ├── class-wpos-query-result.php
│   │   ├── class-wpos-filter-parser.php
│   │   ├── class-wpos-filter-compiler.php
│   │   ├── class-wpos-orderby-compiler.php
│   │   ├── class-wpos-select-compiler.php
│   │   ├── class-wpos-expand-compiler.php
│   │   ├── class-wpos-compute-compiler.php
│   │   └── class-wpos-search-compiler.php
│   │
│   ├── write/                                # LAYER 3 — Domain: writes
│   │   ├── class-wpos-write-handler.php
│   │   ├── class-wpos-deep-insert.php
│   │   ├── class-wpos-deep-update.php
│   │   └── class-wpos-set-operations.php
│   │
│   ├── permissions/                          # LAYER 3 — Domain: ACL
│   │   ├── class-wpos-permission-engine.php
│   │   ├── class-wpos-capability-map.php
│   │   └── class-wpos-field-acl.php
│   │
│   ├── metadata/                             # LAYER 3 — Domain: schema
│   │   ├── class-wpos-metadata-builder.php
│   │   ├── class-wpos-metadata-cache.php
│   │   └── class-wpos-schema-registry.php
│   │
│   ├── adapters/                             # Adapter layer (WP-aware)
│   │   ├── interface-wpos-adapter.php
│   │   ├── class-wpos-adapter-resolver.php   # NEW — resolves name → adapter
│   │   ├── class-wpos-adapter-wp-posts.php
│   │   ├── class-wpos-adapter-wp-users.php
│   │   ├── class-wpos-adapter-wp-terms.php
│   │   ├── class-wpos-adapter-cpt.php
│   │   ├── class-wpos-adapter-taxonomy.php
│   │   └── class-wpos-adapter-custom-table.php
│   │
│   └── admin/                                # Admin UI (WP-aware)
│       ├── class-wpos-admin.php
│       ├── class-wpos-admin-entity-config.php
│       └── class-wpos-admin-permission-config.php
│
├── assets/
│   ├── js/
│   └── css/
│
└── tests/
    ├── unit/
    │   ├── query/          # No WP bootstrap needed
    │   ├── write/
    │   ├── permissions/
    │   ├── metadata/
    │   └── events/
    └── integration/
        ├── adapters/       # Needs WP + DB
        ├── hooks/          # Needs WP bootstrap
        └── admin/
```

---

## 5. Three-Layer Hook Architecture (Canonical)

### The Core Rule

```
WordPress (outer) → Hook Bridge → Event Bus → Domain Services (inner)

Lower layers NEVER know about upper layers.
Domain services dispatch WPOS_Event objects, never WP hook functions.
apply_filters() and add_filter() exist ONLY in WPOS_Hook_Bridge.
```

### Layer 1 — WordPress Hook Bridge

The single WP boundary class. All `add_action`, `add_filter`, `apply_filters`,
`do_action` calls live here and nowhere else.

```php
// src/hooks/class-wpos-hook-bridge.php

class WPOS_Hook_Bridge {

    public function __construct(private WPOS_Event_Bus $event_bus) {}

    /**
     * Called once at plugins_loaded.
     * This is the complete list of WP hooks this plugin registers.
     */
    public function register(): void {
        // WordPress lifecycle
        add_action('init',          [$this, 'on_wp_init']);
        add_action('rest_api_init', [$this, 'on_rest_api_init']);

        // Plugin registration extension points
        // Priority 1 so external plugins at default priority 10 arrive after
        add_action('wpos_register_entity_sets',  '__return_null', 1);
        add_action('wpos_register_permissions',  '__return_null', 1);
        add_action('wpos_register_functions',    '__return_null', 1);
        add_action('wpos_register_actions',      '__return_null', 1);
    }

    public function on_wp_init(): void {
        $this->event_bus->dispatch(new WPOS_Event_WP_Init());
    }

    public function on_rest_api_init(): void {
        $this->event_bus->dispatch(new WPOS_Event_REST_Init());
    }

    /** Expose a WP filter as a public extension point. */
    public function filter(string $hook, mixed $value, array $context = []): mixed {
        return apply_filters($hook, $value, ...$context);
    }

    /** Fire a WP action as a public notification. */
    public function action(string $hook, array $context = []): void {
        do_action($hook, ...$context);
    }
}
```

### Layer 2 — Internal Event Bus

```php
// src/events/class-wpos-event-bus.php

class WPOS_Event_Bus {

    /** @var array<string, WPOS_Event_Listener[]> */
    private array $listeners = [];

    public function subscribe(WPOS_Event_Listener $listener): void {
        $this->listeners[$listener->get_event()][] = $listener;
    }

    public function dispatch(WPOS_Event $event): WPOS_Event {
        foreach ($this->listeners[get_class($event)] ?? [] as $listener) {
            $listener->handle($event);
            if ($event instanceof WPOS_Stoppable_Event && $event->is_stopped()) {
                break;
            }
        }
        return $event;
    }
}
```

### Layer 3 — Domain Services

Pure PHP. Dispatch internal events. Never call WP functions directly.

```php
// Pattern all domain services follow:

class WPOS_Query_Engine {
    public function __construct(
        private WPOS_Filter_Compiler  $filter_compiler,
        private WPOS_Select_Compiler  $select_compiler,
        private WPOS_Expand_Compiler  $expand_compiler,
        private WPOS_Compute_Compiler $compute_compiler,
        private WPOS_Adapter_Resolver $adapter_resolver,
        private WPOS_Event_Bus        $event_bus,
    ) {}

    public function execute(WPOS_Request $request, WP_User $user): WPOS_Result {
        $adapter = $this->adapter_resolver->resolve($request->entity_set);
        $ctx     = $this->build_context($request);

        // Internal event — NO apply_filters() here
        $before = new WPOS_Event_Query_Before($request->entity_set, $user, $ctx);
        $this->event_bus->dispatch($before);
        $ctx = $before->query_context;

        $rows  = $adapter->get_collection($ctx);
        $total = $request->count ? $adapter->get_count($ctx) : null;

        $after = new WPOS_Event_Query_After($request->entity_set, $user, $ctx, $rows);
        $this->event_bus->dispatch($after);

        return new WPOS_Result(rows: $after->results, total_count: $total);
    }
}
```

### Subscriber Pattern

Each subscriber is a thin bridge class: one internal event → one domain call
→ one WP filter exposure.

```php
// src/hooks/subscribers/class-wpos-subscriber-query-before.php

class WPOS_Subscriber_Query_Before implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Permission_Engine $permissions,
        private WPOS_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Query_Before::class;
    }

    public function handle(WPOS_Event $event): void {
        /** @var WPOS_Event_Query_Before $event */

        // 1. Domain logic
        $ctx = $this->permissions->apply_row_filter(
            $event->entity_set, $event->user, $event->query_context
        );

        // 2. Public WP filter — external plugins can modify
        $ctx = $this->bridge->filter(
            'wpos_query_context',
            $ctx,
            [$event->entity_set, $event->user]
        );

        // 3. Write modified context back
        $event->query_context = $ctx;
    }
}
```

---

## 6. Canonical Hook & Filter Registry

**This is the single source of truth for all public WP hooks.**
No hook name outside this table should be used anywhere in the codebase.

### Actions — Plugin Registration (fired during `init`)

| Hook | Arguments | Purpose |
|---|---|---|
| `wpos_register_entity_sets` | `WPOS_Schema_Registry $registry` | Register custom entity sets / custom tables |
| `wpos_register_permissions` | `WPOS_Capability_Map $map` | Register permission rules for entity sets |
| `wpos_register_functions` | `WPOS_Function_Registry $registry` | Register OData bound/unbound functions |
| `wpos_register_actions` | `WPOS_Action_Registry $registry` | Register OData bound/unbound actions |

### Actions — Lifecycle Notifications (fired after events)

| Hook | Arguments | Purpose |
|---|---|---|
| `wpos_inserted` | `string $entity_set, mixed $key, array $payload` | React after entity is created |
| `wpos_updated` | `string $entity_set, mixed $key, array $payload` | React after entity is updated |
| `wpos_deleted` | `string $entity_set, mixed $key` | React after entity is deleted |
| `wpos_deep_inserted` | `string $entity_set, mixed $key, array $payload` | React after deep insert completes |
| `wpos_deep_updated` | `string $entity_set, mixed $key, array $payload` | React after deep update completes |
| `wpos_set_operation_completed` | `string $entity_set, string $op, int $affected` | React after bulk set operation |
| `wpos_admin_entity_config_saved` | `string $entity_set, array $config` | React after admin saves entity config |
| `wpos_admin_permission_saved` | `string $entity_set, array $permissions` | React after admin saves permission config |

### Filters — Permission Overrides

| Hook | Value Type | Arguments | Purpose |
|---|---|---|---|
| `wpos_can_read` | `bool` | `$entity_set, WP_User $user` | Override read permission |
| `wpos_can_insert` | `bool` | `$entity_set, WP_User $user` | Override insert permission |
| `wpos_can_update` | `bool` | `$entity_set, mixed $key, WP_User $user` | Override update permission |
| `wpos_can_delete` | `bool` | `$entity_set, mixed $key, WP_User $user` | Override delete permission |
| `wpos_allowed_properties` | `array` | `$entity_set, WP_User $user, string $op` | Modify allowed fields for role |
| `wpos_allow_public_access` | `bool` | `$entity_set, string $operation` | Allow unauthenticated access |

### Filters — Query Pipeline

| Hook | Value Type | Arguments | Purpose |
|---|---|---|---|
| `wpos_query_context` | `WPOS_Query_Context` | `$entity_set, WP_User $user` | Modify query context before execution |
| `wpos_query_results` | `array` | `$entity_set, WP_User $user` | Modify results after execution |
| `wpos_filter_sql` | `string` | `WPOS_Query_Context $ctx` | Modify compiled SQL WHERE clause |

### Filters — Write Pipeline

| Hook | Value Type | Arguments | Purpose |
|---|---|---|---|
| `wpos_before_insert` | `array $payload` | `$entity_set, WP_User $user` | Modify data before insert |
| `wpos_before_update` | `array $payload` | `$entity_set, mixed $key, WP_User $user` | Modify data before update |
| `wpos_before_deep_insert` | `array $payload` | `$entity_set, WP_User $user` | Modify full deep insert payload |
| `wpos_before_deep_update` | `array $payload` | `$entity_set, mixed $key, WP_User $user` | Modify full deep update payload |
| `wpos_before_set_operation` | `array $filter_ctx` | `$entity_set, string $op, WP_User $user` | Modify filter before bulk op |
| `wpos_nested_entity_payload` | `array $payload` | `$parent_set, $nested_set, WP_User $user` | Modify individual nested entity payload |

### Filters — Schema & Metadata

| Hook | Value Type | Arguments | Purpose |
|---|---|---|---|
| `wpos_entity_type_definition` | `array` | `string $entity_set` | Modify entity type schema definition |
| `wpos_metadata_entity_types` | `array` | *(none)* | Modify all entity types in CSDL output |
| `wpos_metadata_entity_sets` | `array` | *(none)* | Modify all entity sets in CSDL output |

### Filters — Response

| Hook | Value Type | Arguments | Purpose |
|---|---|---|---|
| `wpos_response_payload` | `array` | `WPOS_Request $request` | Modify final JSON payload before send |

---

## 7. Canonical Event Catalogue

All internal events dispatched on the event bus. These are **not** WP hooks —
they are internal PHP objects. External plugins never interact with these directly;
they use the WP filters/actions in Section 6.

### Schema Events

```php
class WPOS_Event_WP_Init implements WPOS_Event {}

class WPOS_Event_REST_Init implements WPOS_Event {}

class WPOS_Event_Schema_Register implements WPOS_Event {
    public function __construct(
        public WPOS_Schema_Registry $registry,   // mutable
    ) {}
}

// Fired whenever schema changes (entity added, config updated, etc.)
// Triggers metadata cache invalidation automatically
class WPOS_Event_Schema_Changed implements WPOS_Event {
    public function __construct(
        public string $reason,   // 'entity_registered' | 'config_updated' | 'entity_removed'
        public string $entity_set,
    ) {}
}

class WPOS_Event_Metadata_Build implements WPOS_Event {
    public function __construct(
        public array $entity_types,   // mutable
        public array $entity_sets,    // mutable
    ) {}
}
```

### Query Events

```php
class WPOS_Event_Query_Before implements WPOS_Event {
    public function __construct(
        public string             $entity_set,
        public WP_User            $user,
        public WPOS_Query_Context $query_context,   // mutable
    ) {}
}

class WPOS_Event_Query_After implements WPOS_Event {
    public function __construct(
        public string             $entity_set,
        public WP_User            $user,
        public WPOS_Query_Context $query_context,
        public array              $results,          // mutable
    ) {}
}
```

### Standard Write Events

```php
class WPOS_Event_Write_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string  $entity_set,
        public string  $operation,   // 'insert' | 'update' | 'delete'
        public WP_User $user,
        public array   $payload,     // mutable
        public mixed   $key = null,
    ) {}
}

class WPOS_Event_Write_After implements WPOS_Event {
    public function __construct(
        public string  $entity_set,
        public string  $operation,
        public WP_User $user,
        public mixed   $key,
        public array   $result,
    ) {}
}
```

### Deep Insert Events

```php
// Fired once before the entire deep insert begins
class WPOS_Event_Deep_Insert_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string  $entity_set,
        public WP_User $user,
        public array   $payload,        // full nested payload, mutable
    ) {}
}

// Fired for each nested entity before it is inserted
class WPOS_Event_Deep_Insert_Nested_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string  $parent_entity_set,
        public string  $nested_entity_set,
        public string  $nav_property,
        public WP_User $user,
        public array   $nested_payload,  // mutable
    ) {}
}

// Fired once after the entire deep insert succeeds
class WPOS_Event_Deep_Insert_After implements WPOS_Event {
    public function __construct(
        public string  $entity_set,
        public WP_User $user,
        public mixed   $key,
        public array   $result,
    ) {}
}
```

### Deep Update Events

```php
// Fired once before the entire deep update begins
class WPOS_Event_Deep_Update_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string  $entity_set,
        public mixed   $key,
        public WP_User $user,
        public array   $payload,        // full delta payload, mutable
    ) {}
}

// Fired for each nested entity touched during deep update
class WPOS_Event_Deep_Update_Nested_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string  $parent_entity_set,
        public string  $nested_entity_set,
        public string  $operation,        // 'insert' | 'update' | 'delete'
        public WP_User $user,
        public array   $nested_payload,   // mutable
        public mixed   $nested_key = null,
    ) {}
}

// Fired once after the entire deep update succeeds
class WPOS_Event_Deep_Update_After implements WPOS_Event {
    public function __construct(
        public string  $entity_set,
        public mixed   $key,
        public WP_User $user,
        public array   $result,
    ) {}
}
```

### Set-Based Operation Events

```php
// Fired before a bulk PATCH/$each or DELETE/$each
class WPOS_Event_Set_Operation_Before implements WPOS_Event {
    public bool $cancelled = false;

    public function __construct(
        public string             $entity_set,
        public string             $operation,     // 'patch' | 'delete' | 'action'
        public WP_User            $user,
        public WPOS_Query_Context $filter_ctx,    // defines which rows are affected, mutable
        public array              $payload,       // patch payload (empty for delete), mutable
    ) {}
}

// Fired after a bulk operation completes
class WPOS_Event_Set_Operation_After implements WPOS_Event {
    public function __construct(
        public string  $entity_set,
        public string  $operation,
        public WP_User $user,
        public int     $affected_count,
    ) {}
}
```

### Permission Check Event

```php
class WPOS_Event_Permission_Check implements WPOS_Event {
    public function __construct(
        public string  $entity_set,
        public string  $operation,   // 'read' | 'insert' | 'update' | 'delete'
        public WP_User $user,
        public bool    $granted,     // initial result from capability map, mutable
        public mixed   $key = null,
    ) {}
}
```

### Admin Events

```php
class WPOS_Event_Admin_Entity_Config_Saved implements WPOS_Event {
    public function __construct(
        public string $entity_set,
        public array  $config,
    ) {}
}

class WPOS_Event_Admin_Permission_Saved implements WPOS_Event {
    public function __construct(
        public string $entity_set,
        public array  $permissions,
    ) {}
}
```

---

## 8. Adapter Resolver & Adapter Event Integration

### WPOS_Adapter_Resolver

The resolver is a registry that maps entity set names to adapter instances.
It lives in the adapter layer (WP-aware) and is injected into domain services.

```php
// src/adapters/class-wpos-adapter-resolver.php

class WPOS_Adapter_Resolver {

    /** @var array<string, WPOS_Adapter> */
    private array $adapters = [];

    public function register(string $entity_set, WPOS_Adapter $adapter): void {
        $this->adapters[$entity_set] = $adapter;
    }

    public function resolve(string $entity_set): WPOS_Adapter {
        if (!isset($this->adapters[$entity_set])) {
            throw new WPOS_Unknown_Entity_Exception(
                "No adapter registered for entity set: {$entity_set}"
            );
        }
        return $this->adapters[$entity_set];
    }

    public function has(string $entity_set): bool {
        return isset($this->adapters[$entity_set]);
    }

    /** @return string[] */
    public function registered_entity_sets(): array {
        return array_keys($this->adapters);
    }
}
```

### Adapter Interface (Canonical)

```php
// src/adapters/interface-wpos-adapter.php

interface WPOS_Adapter {

    // Reads
    public function get_collection(WPOS_Query_Context $ctx): array;
    public function get_entity(mixed $key, WPOS_Query_Context $ctx): ?array;
    public function get_count(WPOS_Query_Context $ctx): int;

    // Writes
    public function insert(array $data): mixed;                    // returns new key
    public function update(mixed $key, array $data): bool;
    public function delete(mixed $key): bool;

    // Schema
    public function get_entity_type_definition(): array;
    public function get_entity_set_name(): string;
}
```

### How Adapters Connect to the Schema Registry

Adapters are registered into the resolver during the `WPOS_Event_Schema_Register`
event, which the `WPOS_Subscriber_Schema_Init` subscriber triggers:

```
WP 'init' fires
  → WPOS_Hook_Bridge::on_wp_init()
      → dispatch(WPOS_Event_WP_Init)
          → WPOS_Subscriber_Schema_Init::handle()
              → do_action('wpos_register_entity_sets', $registry)   ← external plugins hook here
              → dispatch(WPOS_Event_Schema_Register)
                  → WPOS_Bootstrapper registers built-in adapters into WPOS_Adapter_Resolver
                  → WPOS_Schema_Registry is populated from registered adapters
```

---

## 9. Deep Insert / Deep Update / Set Operations Events

### Deep Insert Flow

```
POST /odata/v4/Posts  { "Title": "...", "Tags": [...], "Meta": [...] }
  │
  ▼
WPOS_Write_Handler::insert()
  → dispatch(WPOS_Event_Write_Before, operation='insert')
      → WPOS_Subscriber_Write_Before checks top-level permission
  → WPOS_Deep_Insert::execute()
      → dispatch(WPOS_Event_Deep_Insert_Before)        ← full payload exposed to WP filter
          → WPOS_Subscriber_Deep_Insert: 'wpos_before_deep_insert' filter
      → adapter->insert(root payload)                  ← insert root entity
      → for each navigation property with nested data:
          → dispatch(WPOS_Event_Deep_Insert_Nested_Before)
              → WPOS_Subscriber_Deep_Insert: check nested entity permission
              → 'wpos_nested_entity_payload' filter
          → nested_adapter->insert(nested payload)
      → dispatch(WPOS_Event_Deep_Insert_After)
          → WPOS_Subscriber_Deep_Insert: 'wpos_deep_inserted' action
  → dispatch(WPOS_Event_Write_After)
```

### Deep Update Flow

```
PATCH /odata/v4/Posts(42)  { "Title": "...", "Meta@delta": [...] }
  │
  ▼
WPOS_Write_Handler::update()
  → dispatch(WPOS_Event_Write_Before, operation='update')
  → WPOS_Deep_Update::execute()
      → dispatch(WPOS_Event_Deep_Update_Before)
          → 'wpos_before_deep_update' filter
      → adapter->update(key, root payload)             ← update root entity
      → for each delta nested entity:
          → dispatch(WPOS_Event_Deep_Update_Nested_Before, operation='insert'|'update'|'delete')
              → check permission for the specific nested operation
              → 'wpos_nested_entity_payload' filter
          → nested_adapter->(insert|update|delete)(...)
      → dispatch(WPOS_Event_Deep_Update_After)
          → 'wpos_deep_updated' action
  → dispatch(WPOS_Event_Write_After)
```

### Set-Based Operation Flow

```
PATCH /odata/v4/Posts/$filter=@f/$each?@f=Status eq 'draft'  { "Status": "publish" }
  │
  ▼
WPOS_Set_Operations::patch_each()
  → dispatch(WPOS_Event_Set_Operation_Before, operation='patch')
      → WPOS_Subscriber_Set_Operation:
          → check 'wpos_can_update' for the entity set
          → 'wpos_before_set_operation' filter (can modify filter or payload)
  → compile filter → single SQL UPDATE ... WHERE ...
  → execute atomically via $wpdb transaction
  → dispatch(WPOS_Event_Set_Operation_After)
      → 'wpos_set_operation_completed' action
```

**Important:** Set operations compile to a single SQL statement for atomicity.
They do NOT loop over individual entities and fire per-row write events.
This is intentional for performance. If per-row lifecycle hooks are needed,
use standard single-entity writes instead.

---

## 10. Admin UI Hook Coverage

Admin classes route through the Hook Bridge exactly like everything else.
No `apply_filters()` calls in admin classes directly.

### Admin Save Flow

```
Admin saves entity config in WP admin panel
  → WPOS_Admin_Entity_Config::save()
      → validates input
      → updates WP option / config storage
      → dispatch(WPOS_Event_Admin_Entity_Config_Saved)   ← internal event
          → WPOS_Subscriber_Admin_Config_Saved::handle()
              → do_action('wpos_admin_entity_config_saved', $entity_set, $config)  ← WP action
              → dispatch(WPOS_Event_Schema_Changed)       ← triggers cache invalidation
```

### Admin-Specific Subscribers

```php
// src/hooks/subscribers/class-wpos-subscriber-admin-config-saved.php

class WPOS_Subscriber_Admin_Config_Saved implements WPOS_Event_Listener {

    public function __construct(
        private WPOS_Hook_Bridge  $bridge,
        private WPOS_Event_Bus    $event_bus,
    ) {}

    public function get_event(): string {
        return WPOS_Event_Admin_Entity_Config_Saved::class;
    }

    public function handle(WPOS_Event $event): void {
        /** @var WPOS_Event_Admin_Entity_Config_Saved $event */

        // 1. Fire WP action for external plugins to react
        $this->bridge->action('wpos_admin_entity_config_saved', [
            $event->entity_set,
            $event->config,
        ]);

        // 2. Trigger schema change → will bust metadata cache (see Section 11)
        $this->event_bus->dispatch(new WPOS_Event_Schema_Changed(
            reason:     'config_updated',
            entity_set: $event->entity_set,
        ));
    }
}
```

---

## 11. Metadata Cache Invalidation Strategy

### Problem

`$metadata` CSDL output is expensive to build (it inspects all registered entity
sets, their properties, navigation properties, and annotations). It must be cached.
But when schema changes — whether via admin UI or via an external plugin calling
`wpos_register_entity_sets` — the cache must be invalidated.

### Solution: Schema Changed Event → Auto Bust

`WPOS_Event_Schema_Changed` is dispatched any time the schema is modified.
`WPOS_Subscriber_Schema_Changed` catches it and busts the transient cache.

```php
// src/hooks/subscribers/class-wpos-subscriber-schema-changed.php

class WPOS_Subscriber_Schema_Changed implements WPOS_Event_Listener {

    public function get_event(): string {
        return WPOS_Event_Schema_Changed::class;
    }

    public function handle(WPOS_Event $event): void {
        // Delete both XML and JSON cached metadata
        delete_transient('wpos_metadata_xml');
        delete_transient('wpos_metadata_json');
    }
}
```

### Cache Build Strategy

```php
// src/metadata/class-wpos-metadata-cache.php

class WPOS_Metadata_Cache {

    private const TTL = DAY_IN_SECONDS;

    public function get_xml(): ?string {
        $cached = get_transient('wpos_metadata_xml');
        return $cached !== false ? $cached : null;
    }

    public function set_xml(string $csdl): void {
        set_transient('wpos_metadata_xml', $csdl, self::TTL);
    }

    public function get_json(): ?string {
        $cached = get_transient('wpos_metadata_json');
        return $cached !== false ? $cached : null;
    }

    public function set_json(string $csdl): void {
        set_transient('wpos_metadata_json', $csdl, self::TTL);
    }

    public function bust(): void {
        delete_transient('wpos_metadata_xml');
        delete_transient('wpos_metadata_json');
    }
}
```

### When Schema Changed Is Dispatched

| Trigger | Dispatched by |
|---|---|
| External plugin registers new entity set | `WPOS_Subscriber_Schema_Init` after `wpos_register_entity_sets` fires |
| Admin saves entity config | `WPOS_Subscriber_Admin_Config_Saved` |
| Admin saves permission config | `WPOS_Subscriber_Admin_Config_Saved` |
| Plugin activation/deactivation | `WPOS_Hook_Bridge::register()` hooks on `activated_plugin` / `deactivated_plugin` |

---

## 12. Dependency Injection Container

The container is a **Phase 1 deliverable**. It is the wiring backbone of the
entire three-layer architecture. Without it, the separation of concerns collapses.

```php
// src/bootstrap/class-wpos-container.php

class WPOS_Container {

    private array $factories  = [];
    private array $singletons = [];

    public function singleton(string $id, callable $factory): void {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed {
        if (!isset($this->singletons[$id])) {
            if (!isset($this->factories[$id])) {
                throw new \RuntimeException("No binding for: {$id}");
            }
            $this->singletons[$id] = ($this->factories[$id])($this);
        }
        return $this->singletons[$id];
    }
}
```

```php
// src/bootstrap/class-wpos-bootstrapper.php

class WPOS_Bootstrapper {

    public static function build(): WPOS_Container {
        $c = new WPOS_Container();

        // ── Core infrastructure ─────────────────────────────────────────
        $c->singleton(WPOS_Event_Bus::class,
            fn() => new WPOS_Event_Bus()
        );

        $c->singleton(WPOS_Hook_Bridge::class,
            fn($c) => new WPOS_Hook_Bridge($c->get(WPOS_Event_Bus::class))
        );

        // ── Schema & metadata ────────────────────────────────────────────
        $c->singleton(WPOS_Schema_Registry::class,
            fn() => new WPOS_Schema_Registry()
        );

        $c->singleton(WPOS_Metadata_Cache::class,
            fn() => new WPOS_Metadata_Cache()
        );

        $c->singleton(WPOS_Metadata_Builder::class,
            fn($c) => new WPOS_Metadata_Builder(
                $c->get(WPOS_Schema_Registry::class),
                $c->get(WPOS_Metadata_Cache::class),
                $c->get(WPOS_Event_Bus::class),
            )
        );

        // ── Adapters ─────────────────────────────────────────────────────
        $c->singleton(WPOS_Adapter_Resolver::class,
            fn() => new WPOS_Adapter_Resolver()
        );

        $c->singleton(WPOS_Adapter_WP_Posts::class,
            fn() => new WPOS_Adapter_WP_Posts()
        );
        $c->singleton(WPOS_Adapter_WP_Users::class,
            fn() => new WPOS_Adapter_WP_Users()
        );
        $c->singleton(WPOS_Adapter_WP_Terms::class,
            fn() => new WPOS_Adapter_WP_Terms()
        );
        $c->singleton(WPOS_Adapter_CPT::class,
            fn() => new WPOS_Adapter_CPT()
        );
        $c->singleton(WPOS_Adapter_Custom_Table::class,
            fn() => new WPOS_Adapter_Custom_Table()
        );

        // ── Permissions ──────────────────────────────────────────────────
        $c->singleton(WPOS_Capability_Map::class,
            fn() => new WPOS_Capability_Map()
        );

        $c->singleton(WPOS_Permission_Engine::class,
            fn($c) => new WPOS_Permission_Engine(
                $c->get(WPOS_Capability_Map::class)
            )
        );

        $c->singleton(WPOS_Field_ACL::class,
            fn($c) => new WPOS_Field_ACL(
                $c->get(WPOS_Permission_Engine::class)
            )
        );

        // ── Query compilers ──────────────────────────────────────────────
        $c->singleton(WPOS_Filter_Compiler::class,  fn() => new WPOS_Filter_Compiler());
        $c->singleton(WPOS_Select_Compiler::class,  fn() => new WPOS_Select_Compiler());
        $c->singleton(WPOS_Expand_Compiler::class,  fn() => new WPOS_Expand_Compiler());
        $c->singleton(WPOS_Compute_Compiler::class, fn() => new WPOS_Compute_Compiler());
        $c->singleton(WPOS_Orderby_Compiler::class, fn() => new WPOS_Orderby_Compiler());
        $c->singleton(WPOS_Search_Compiler::class,  fn() => new WPOS_Search_Compiler());

        // ── Domain services ──────────────────────────────────────────────
        $c->singleton(WPOS_Query_Engine::class,
            fn($c) => new WPOS_Query_Engine(
                $c->get(WPOS_Filter_Compiler::class),
                $c->get(WPOS_Select_Compiler::class),
                $c->get(WPOS_Expand_Compiler::class),
                $c->get(WPOS_Compute_Compiler::class),
                $c->get(WPOS_Adapter_Resolver::class),
                $c->get(WPOS_Event_Bus::class),
            )
        );

        $c->singleton(WPOS_Deep_Insert::class,
            fn($c) => new WPOS_Deep_Insert(
                $c->get(WPOS_Adapter_Resolver::class),
                $c->get(WPOS_Event_Bus::class),
            )
        );

        $c->singleton(WPOS_Deep_Update::class,
            fn($c) => new WPOS_Deep_Update(
                $c->get(WPOS_Adapter_Resolver::class),
                $c->get(WPOS_Event_Bus::class),
            )
        );

        $c->singleton(WPOS_Set_Operations::class,
            fn($c) => new WPOS_Set_Operations(
                $c->get(WPOS_Adapter_Resolver::class),
                $c->get(WPOS_Filter_Compiler::class),
                $c->get(WPOS_Event_Bus::class),
            )
        );

        $c->singleton(WPOS_Write_Handler::class,
            fn($c) => new WPOS_Write_Handler(
                $c->get(WPOS_Adapter_Resolver::class),
                $c->get(WPOS_Deep_Insert::class),
                $c->get(WPOS_Deep_Update::class),
                $c->get(WPOS_Set_Operations::class),
                $c->get(WPOS_Event_Bus::class),
            )
        );

        // ── HTTP layer ───────────────────────────────────────────────────
        $c->singleton(WPOS_Router::class,
            fn($c) => new WPOS_Router(
                $c->get(WPOS_Query_Engine::class),
                $c->get(WPOS_Write_Handler::class),
                $c->get(WPOS_Metadata_Builder::class),
                $c->get(WPOS_Permission_Engine::class),
            )
        );

        // ── Register all subscribers ─────────────────────────────────────
        self::register_subscribers($c);

        return $c;
    }

    private static function register_subscribers(WPOS_Container $c): void {
        $bus    = $c->get(WPOS_Event_Bus::class);
        $bridge = $c->get(WPOS_Hook_Bridge::class);

        $subscribers = [
            // Schema
            new WPOS_Subscriber_Schema_Init(
                $c->get(WPOS_Schema_Registry::class),
                $c->get(WPOS_Adapter_Resolver::class),
                $bridge
            ),
            new WPOS_Subscriber_Schema_Changed(
                $c->get(WPOS_Metadata_Cache::class)
            ),
            new WPOS_Subscriber_Metadata_Build(
                $c->get(WPOS_Metadata_Builder::class), $bridge
            ),

            // Query
            new WPOS_Subscriber_Query_Before(
                $c->get(WPOS_Permission_Engine::class), $bridge
            ),
            new WPOS_Subscriber_Query_After(
                $c->get(WPOS_Field_ACL::class), $bridge
            ),

            // Permissions
            new WPOS_Subscriber_Permission_Check(
                $c->get(WPOS_Permission_Engine::class), $bridge
            ),

            // Writes
            new WPOS_Subscriber_Write_Before(
                $c->get(WPOS_Permission_Engine::class), $bridge
            ),
            new WPOS_Subscriber_Write_After($bridge),
            new WPOS_Subscriber_Deep_Insert(
                $c->get(WPOS_Permission_Engine::class), $bridge
            ),
            new WPOS_Subscriber_Deep_Update(
                $c->get(WPOS_Permission_Engine::class), $bridge
            ),
            new WPOS_Subscriber_Set_Operation(
                $c->get(WPOS_Permission_Engine::class), $bridge
            ),

            // Admin
            new WPOS_Subscriber_Admin_Config_Saved($bridge, $bus),
        ];

        foreach ($subscribers as $subscriber) {
            $bus->subscribe($subscriber);
        }
    }
}
```

**Plugin entry point:**

```php
// wp-odata-suite.php

add_action('plugins_loaded', function() {
    $container = WPOS_Bootstrapper::build();
    $container->get(WPOS_Hook_Bridge::class)->register();

    // Make container accessible for testing / advanced use
    // (never used for internal wiring — use constructor injection instead)
    $GLOBALS['wpos_container'] = $container;
}, 5);

function wpos_container(): WPOS_Container {
    return $GLOBALS['wpos_container'];
}
```

---

## 13. Phase Breakdown (Reconciled)

### Phase 1 — Foundation & Core Engine
**Duration: 3–4 weeks**

Deliverables:
- `WPOS_Container` + `WPOS_Bootstrapper` (DI container — first thing built)
- `WPOS_Event_Bus` + all event interfaces + all event value objects
- `WPOS_Hook_Bridge` + subscriber scaffolding (empty implementations)
- `WPOS_Router`, `WPOS_Request`, `WPOS_Response`, `WPOS_Error`
- `WPOS_Schema_Registry`
- `WPOS_Metadata_Cache`
- Plugin entry point wiring
- OData header handling (`OData-Version`, `Prefer`, `Content-Type`)
- `$metadata` endpoint returning minimal valid CSDL (XML)

---

### Phase 2 — Data Source Adapters
**Duration: 4–5 weeks**

Deliverables:
- `WPOS_Adapter` interface (canonical, from Section 8)
- `WPOS_Adapter_Resolver`
- `WPOS_Adapter_WP_Posts` — wp_posts, full property + navigation map
- `WPOS_Adapter_WP_Users` — wp_users + wp_usermeta
- `WPOS_Adapter_WP_Terms` — wp_terms + taxonomies
- `WPOS_Adapter_CPT` — auto-discovers registered CPTs
- `WPOS_Adapter_Taxonomy` — auto-discovers registered taxonomies
- `WPOS_Adapter_Custom_Table` — generic, schema from `DESCRIBE` or manual config
- `WPOS_Subscriber_Schema_Init` — wires adapters via `wpos_register_entity_sets`
- `WPOS_Event_Schema_Changed` + `WPOS_Subscriber_Schema_Changed` — cache bust

---

### Phase 3 — OData Query Engine
**Duration: 5–6 weeks**

Deliverables:
- `WPOS_Filter_Parser` — tokenizer → AST (all v4.01 operators + functions)
- `WPOS_Filter_Compiler` — AST → SQL WHERE (with `$wpdb->prepare()`)
- `WPOS_Select_Compiler` — property → column map
- `WPOS_Expand_Compiler` — navigation expansion
- `WPOS_Compute_Compiler` — computed virtual columns
- `WPOS_Orderby_Compiler`, `WPOS_Search_Compiler`
- `WPOS_Query_Engine` — orchestrates all compilers + dispatches events
- `WPOS_Subscriber_Query_Before` — row ACL + `wpos_query_context` filter
- `WPOS_Subscriber_Query_After` — field ACL + `wpos_query_results` filter
- `/$query` endpoint (POST body query)
- Server-driven pagination (`$top`, `$skip`, `@odata.nextLink`)

---

### Phase 4 — Role & Permission System
**Duration: 3–4 weeks**

Deliverables:
- `WPOS_Capability_Map` — WP capability → OData operation mapping
- `WPOS_Permission_Engine` — entity-level + row-level checks
- `WPOS_Field_ACL` — field-level property stripping
- `WPOS_Subscriber_Permission_Check` — bridges to `wpos_can_*` filters
- `WPOS_Subscriber_Write_Before` — permission check before writes
- Row-level security via query context injection
- Custom capability convention (`wpos_{entity_set}_{operation}`)
- Unauthenticated access via `wpos_allow_public_access` filter

---

### Phase 5 — Advanced OData v4.01 Features
**Duration: 4–5 weeks** *(extended from original 3–4 to account for new event coverage)*

Deliverables:
- `WPOS_Deep_Insert` + deep insert events + subscribers
- `WPOS_Deep_Update` + deep update events + subscribers
- `WPOS_Set_Operations` + set operation events + subscribers
- Alternate keys support
- Key-as-segment URL convention
- `$expand` with POST/PATCH (`Prefer: return=representation`)
- OData Functions + Actions registry + routing
- Delta responses (`@odata.deltaLink`)
- Batch requests — multipart MIME format
- JSON Batch requests (v4.01)
- Async responses via WP-Cron (`Prefer: respond-async`)
- `$metadata` JSON CSDL output

---

### Phase 6 — Admin UI & Configuration
**Duration: 2–3 weeks**

Deliverables:
- `WPOS_Admin` — dashboard page, WP admin menu
- `WPOS_Admin_Entity_Config` — entity set configuration UI
- `WPOS_Admin_Permission_Config` — role × entity × operation grid
- `WPOS_Subscriber_Admin_Config_Saved` — hooks admin saves into event bus
- Admin save fires `WPOS_Event_Schema_Changed` → cache invalidated automatically
- Admin-specific WP actions: `wpos_admin_entity_config_saved`, `wpos_admin_permission_saved`

---

### Phase 7 — Testing, Security & Performance
**Duration: 3–4 weeks**

Deliverables:
- Unit tests: all domain services (no WP bootstrap)
- Unit tests: event bus, all event objects
- Integration tests: hook bridge (WP bootstrap, all public hooks)
- Integration tests: all adapters (full DB round-trip)
- Integration tests: deep insert / deep update / set operations
- Security hardening: SQL injection, privilege escalation, PII, CSRF
- Performance: query analysis, index recommendations, `$top` cap, metadata cache tuning
- CI: GitHub Actions matrix across PHP 8.1/8.2/8.3 × WP 6.3/6.4/6.5

---

## 14. OData v4.01 Feature Implementation Map

| Feature | Priority | Phase | Covered by Hook |
|---|---|---|---|
| `$filter` basic operators | P0 | 3 | `wpos_filter_sql` |
| `$select` | P0 | 3 | `wpos_allowed_properties` |
| `$orderby` | P0 | 3 | — |
| `$top` / `$skip` | P0 | 3 | — |
| `$count` | P0 | 3 | — |
| `$expand` (single level) | P0 | 3 | — |
| CRUD operations | P0 | 2–3 | `wpos_can_*`, `wpos_before_insert`, `wpos_before_update` |
| `$metadata` XML CSDL | P0 | 1 | `wpos_metadata_entity_types/sets` |
| Role/Permission enforcement | P0 | 4 | `wpos_can_*`, `wpos_allowed_properties` |
| Key-as-segment URLs | P1 | 5 | — |
| Alternate keys | P1 | 5 | — |
| `in` operator | P1 | 3 | `wpos_filter_sql` |
| `divby` operator | P1 | 3 | `wpos_filter_sql` |
| `matchesPattern` function | P1 | 3 | `wpos_filter_sql` |
| `hassubset` / `hassubsequence` | P1 | 3 | `wpos_filter_sql` |
| `$compute` | P1 | 3 | — |
| `/$query` endpoint | P1 | 3 | — |
| `omit-values` preference | P1 | 1 | `wpos_response_payload` |
| Deep Insert | P1 | 5 | `wpos_before_deep_insert`, `wpos_nested_entity_payload`, `wpos_deep_inserted` |
| Deep Update | P1 | 5 | `wpos_before_deep_update`, `wpos_nested_entity_payload`, `wpos_deep_updated` |
| Set-based PATCH / DELETE | P1 | 5 | `wpos_before_set_operation`, `wpos_set_operation_completed` |
| `$search` | P1 | 3 | — |
| `$metadata` JSON CSDL | P2 | 5 | `wpos_metadata_entity_types/sets` |
| JSON Batch requests | P2 | 5 | — |
| Multipart Batch requests | P2 | 5 | — |
| Delta responses | P2 | 5 | — |
| Async responses | P2 | 5 | — |
| OData Functions | P2 | 5 | `wpos_register_functions` |
| OData Actions | P2 | 5 | `wpos_register_actions` |
| `$expand` (nested) | P2 | 3 | — |
| Schema versioning | P2 | 1 | — |
| `$index` ordered collections | P3 | 3 | — |
| `case` function | P3 | 3 | `wpos_filter_sql` |
| `substring` negative index | P3 | 3 | — |
| AsyncResult header | P3 | 5 | — |
| ETag in batch | P3 | 5 | — |

---

## 15. Data Source Strategy

```
/odata/v4/Posts              → WPOS_Adapter_WP_Posts (post_type='post')
/odata/v4/Pages              → WPOS_Adapter_WP_Posts (post_type='page')
/odata/v4/{CPT}              → WPOS_Adapter_CPT (auto-discovered)
/odata/v4/Users              → WPOS_Adapter_WP_Users
/odata/v4/Categories         → WPOS_Adapter_WP_Terms (taxonomy='category')
/odata/v4/Tags               → WPOS_Adapter_WP_Terms (taxonomy='post_tag')
/odata/v4/{Taxonomy}         → WPOS_Adapter_Taxonomy (auto-discovered)
/odata/v4/Employees          → WPOS_Adapter_Custom_Table (wp_employees)
/odata/v4/Comments           → WPOS_Adapter_WP_Posts (wp_comments)
/odata/v4/Attachments        → WPOS_Adapter_WP_Posts (post_type='attachment')
```

Navigation properties resolve cross-adapter:
```
Post.Author     → WPOS_Adapter_WP_Users
Post.Tags       → WPOS_Adapter_WP_Terms
Post.Meta       → WPOS_Adapter_WP_Posts (postmeta)
Employee.Dept   → WPOS_Adapter_Custom_Table (wp_departments)
Employee.User   → WPOS_Adapter_WP_Users (linked by meta)
```

---

## 16. URL Routing Design

Base URL: `/wp-json/odata/v4/`

```
GET    /odata/v4/                           → Service document
GET    /odata/v4/$metadata                  → CSDL (XML default, JSON via $format)
POST   /odata/v4/$batch                     → Batch (multipart or JSON)
POST   /odata/v4/{EntitySet}/$query         → Long query via POST body

GET    /odata/v4/{EntitySet}                → Collection
POST   /odata/v4/{EntitySet}                → Create entity (deep insert supported)
PATCH  /odata/v4/{EntitySet}                → Update collection (delta payload)

GET    /odata/v4/{EntitySet}({key})         → Single entity (parentheses style)
GET    /odata/v4/{EntitySet}/{key}          → Single entity (key-as-segment, 4.01)
PUT    /odata/v4/{EntitySet}({key})         → Replace entity
PATCH  /odata/v4/{EntitySet}({key})         → Update entity (deep update supported)
DELETE /odata/v4/{EntitySet}({key})         → Delete entity

PATCH  /odata/v4/{EntitySet}/$filter(@x)/$each?@x={expr}  → Set-based update
DELETE /odata/v4/{EntitySet}/$filter(@x)/$each?@x={expr}  → Set-based delete
POST   /odata/v4/{EntitySet}/$filter(@x)/$each/NS.Action  → Set-based action

GET    /odata/v4/{EntitySet}({key})/{NavProp}              → Navigation collection
GET    /odata/v4/{EntitySet}({key})/{NavProp}/$ref         → Navigation refs
GET    /odata/v4/{EntitySet}({key})/{Property}/$value      → Raw property value
GET    /odata/v4/{EntitySet}/$count                        → Count only

GET    /odata/v4/NS.Function(param=value)                  → Unbound function
POST   /odata/v4/NS.Action                                 → Unbound action
POST   /odata/v4/{EntitySet}({key})/NS.Action              → Bound action
GET    /odata/v4/{EntitySet}/NS.Function(param=value)      → Bound function
```

---

## 17. Metadata ($metadata) Design

XML CSDL served by default. JSON CSDL served when `?$format=application/json`
or `Accept: application/json` is sent.

Both formats are built by `WPOS_Metadata_Builder`, cached by `WPOS_Metadata_Cache`,
and cache-busted via `WPOS_Event_Schema_Changed`.

Both expose `wpos_metadata_entity_types` and `wpos_metadata_entity_sets` WP filters
so external plugins can add/modify schema declarations.

---

## 18. Role & Permission Design

### Two-Layer Model

- **Layer 1 — Entity Set Level:** can the user access this entity set at all?
- **Layer 2 — Field Level:** which properties can the user read/write?

### Default Capability Map (Native WP Tables)

| Entity Set | Read | Insert | Update | Delete |
|---|---|---|---|---|
| `Posts` | `read` | `edit_posts` | `edit_posts` | `delete_posts` |
| `Pages` | `read` | `edit_pages` | `edit_pages` | `delete_pages` |
| `Users` | `list_users` | `create_users` | `edit_users` | `delete_users` |
| `Terms` | `read` | `manage_categories` | `manage_categories` | `manage_categories` |
| `Comments` | `read` | `read` | `edit_comment` | `delete_comment` |
| `Media` | `read` | `upload_files` | `upload_files` | `delete_posts` |

### Custom Table Capability Convention

```
wpos_{entity_set_lowercase}_{operation}

wpos_employees_read
wpos_employees_insert
wpos_employees_update
wpos_employees_delete
wpos_salary_read       ← field-level
```

### Permission Request Flow

```
Incoming OData Request
  │
  ├─ Is user authenticated?
  │     No  → check wpos_allow_public_access filter
  │     Yes ↓
  │
  ├─ dispatch(WPOS_Event_Permission_Check)
  │     → WPOS_Subscriber_Permission_Check
  │         → WPOS_Permission_Engine::can_{operation}()
  │         → bridge->filter('wpos_can_{operation}', $granted, [...])
  │     Denied? → 403 Forbidden
  │
  ├─ dispatch(WPOS_Event_Query_Before)
  │     → WPOS_Subscriber_Query_Before
  │         → WPOS_Permission_Engine::apply_row_filter()  ← row-level security
  │         → bridge->filter('wpos_query_context', $ctx, [...])
  │
  ├─ Execute query / write
  │
  └─ dispatch(WPOS_Event_Query_After)
        → WPOS_Subscriber_Query_After
            → WPOS_Field_ACL::apply()                     ← field-level stripping
            → bridge->filter('wpos_query_results', $results, [...])
```

---

## 19. Security Considerations

| Risk | Mitigation |
|---|---|
| SQL injection via `$filter` | AST-based compilation; all values via `$wpdb->prepare()`; never string interpolation |
| Unauthorized data exposure | Entity-level + field-level ACL; row-level filter injection |
| PII leakage | `user_pass` permanently excluded; `user_email` / `user_login` require `list_users` |
| Over-fetching / DoS | Default `$top=100`; max `$top=1000`; max filter depth; 8KB URL limit |
| Privilege escalation via deep insert | Each nested entity permission checked individually via `WPOS_Event_Deep_Insert_Nested_Before` |
| Set-based operation abuse | `wpos_can_update` / `wpos_can_delete` checked on entity set before bulk op executes |
| CSRF (cookie auth) | WP nonce on all non-GET requests |
| Schema disclosure | `$metadata` requires auth unless `wpos_allow_public_access` returns true |
| Hook injection by malicious plugins | Subscribers validate event data; domain services validate all inputs |

---

## 20. Tech Stack

| Component | Technology | Reason |
|---|---|---|
| Plugin framework | WordPress Plugin API | Native WP integration |
| DI Container | Custom `WPOS_Container` | Lightweight; no external deps |
| REST routing | `WP_REST_Server` + custom handler | WP REST API foundation |
| Database | `$wpdb` with `prepare()` | WP standard; SQL injection safe |
| Filter parser | Custom recursive descent parser | Full OData grammar control |
| Event bus | Custom `WPOS_Event_Bus` | Pure PHP; zero WP dependency |
| JSON serialization | Native `json_encode` | Performance |
| Metadata cache | WP Transients API | Works with any object cache backend |
| Admin UI | WP Settings API (+ React optional) | WP admin standards |
| Testing | PHPUnit + WP_UnitTestCase | Standard WP testing |
| CI/CD | GitHub Actions | Matrix: PHP 8.1/8.2/8.3 × WP 6.3/6.4/6.5 |

---

## 21. Milestones & Timeline

| Phase | Deliverable | Duration | Cumulative |
|---|---|---|---|
| **Phase 1** | Container, Event Bus, Hook Bridge, Router, Schema Registry | 3–4 wks | Wk 4 |
| **Phase 2** | All adapters + Adapter Resolver + Schema init subscriber | 4–5 wks | Wk 9 |
| **Phase 3** | Full query engine + all compilers + query subscribers | 5–6 wks | Wk 15 |
| **Phase 4** | Permission engine + field ACL + all permission subscribers | 3–4 wks | Wk 19 |
| **Phase 5** | Deep insert/update, set ops, batch, async, functions/actions | 4–5 wks | Wk 24 |
| **Phase 6** | Admin UI + admin subscribers + cache invalidation wiring | 2–3 wks | Wk 27 |
| **Phase 7** | Testing, security hardening, performance | 3–4 wks | Wk 31 |
| **Beta** | Public beta release | — | Wk 33 |
| **v1.0** | Stable release | — | Wk 35 |

---

## 22. Open Questions & Risks

### Open Questions

1. **Multisite:** Entity sets site-scoped or network-scoped?
2. **CPT meta fields:** Opt-in or all-exposed by default? (Performance risk with large meta tables.)
3. **Write via WP API vs raw `$wpdb`:** Use `wp_insert_post()` (fires WP hooks, data integrity) or raw `$wpdb` (faster, bypasses hooks). **Recommendation: `wp_insert_post()` for WP-native entity sets; raw `$wpdb` for custom tables.**
4. **OData namespace:** Use `Service.` prefix or plugin-specific namespace for functions/actions?
5. **Delta tracking:** `post_modified` timestamp or dedicated change-log table?
6. **Event priority ordering:** If two subscribers listen to the same event, what determines order? **Decision needed:** use explicit integer priority in `subscribe()` or rely on registration order?

### Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| `$filter` SQL compilation edge cases | High | High | Extensive unit tests + fuzz testing |
| wp_users PII sensitivity | High | High | Strict defaults; field ACL enforced before response |
| Performance on large datasets | Medium | High | `$top` cap; query analysis; transient cache |
| Plugin name conflicts (same CPT name) | Medium | Medium | Namespace detection on schema registry |
| Admin saves firing excessive cache busts | Low | Medium | Debounce `WPOS_Event_Schema_Changed` in admin subscriber |
| WP core updates breaking internal hooks | Low | High | CI matrix across WP versions |

---

## Appendix A — Full Hook Reference

### How another plugin registers a custom table

```php
add_action('wpos_register_entity_sets', function(WPOS_Schema_Registry $registry) {
    $registry->register_entity_set('Employees', [
        'adapter'     => 'custom_table',
        'table'       => 'wp_employees',
        'entity_type' => 'Employee',
        'key'         => ['employee_id'],
        'properties'  => [
            'employee_id' => 'Edm.Int64',
            'first_name'  => 'Edm.String',
            'salary'      => 'Edm.Decimal',
            'hire_date'   => 'Edm.Date',
        ],
        'navigations' => [
            'Department' => ['target' => 'Departments', 'key' => 'department_id'],
        ],
    ]);
});
```

### How another plugin overrides permissions

```php
// Deny all reads of Employees for non-HR users
add_filter('wpos_can_read', function(bool $granted, string $entity_set, WP_User $user): bool {
    if ($entity_set === 'Employees' && !user_can($user, 'wpos_employees_read')) {
        return false;
    }
    return $granted;
}, 10, 3);
```

### How another plugin adds a computed field to results

```php
add_filter('wpos_query_results', function(array $results, string $entity_set): array {
    if ($entity_set !== 'Employees') return $results;
    return array_map(function($row) {
        $row['FullName'] = $row['first_name'] . ' ' . $row['last_name'];
        return $row;
    }, $results);
}, 10, 2);
```

### How another plugin reacts to a new entity being created

```php
add_action('wpos_inserted', function(string $entity_set, mixed $key, array $payload) {
    if ($entity_set === 'Employees') {
        // Send welcome email, provision accounts, etc.
        my_plugin_on_employee_created($key, $payload);
    }
}, 10, 3);
```

---

## Appendix B — Example OData Requests

```http
# Get published posts with author
GET /wp-json/odata/v4/Posts
    ?$filter=Status eq 'publish'
    &$expand=Author($select=DisplayName)
    &$orderby=Date desc
    &$top=10

# Get user by email (alternate key)
GET /wp-json/odata/v4/Users(email='john@example.com')

# Employees with department (custom table + navigation)
GET /wp-json/odata/v4/Employees
    ?$expand=Department($select=Name)
    &$filter=IsActive eq true
    &$select=employee_id,first_name,last_name,Department

# Computed properties on-the-fly
GET /wp-json/odata/v4/Posts
    ?$compute=days(now() sub Date) as AgeDays
    &$orderby=AgeDays desc
    &$top=5

# Bulk publish all drafts by author 5
PATCH /wp-json/odata/v4/Posts/$filter=@f/$each?@f=Status eq 'draft' and Author/ID eq 5
Content-Type: application/json
{ "Status": "publish" }

# In operator
GET /wp-json/odata/v4/Posts?$filter=Status in ('publish','draft')

# Count only
GET /wp-json/odata/v4/Posts/$count?$filter=Status eq 'publish'

# Omit nulls to reduce payload
GET /wp-json/odata/v4/Employees
Prefer: omit-values=nulls

# Deep insert — post + tags + meta in one request
POST /wp-json/odata/v4/Posts
Content-Type: application/json
{
    "Title": "Hello World",
    "Status": "publish",
    "Tags": [{ "Name": "OData" }, { "Name": "WordPress" }],
    "Meta": [{ "Key": "_custom", "Value": "value" }]
}

# JSON batch (v4.01)
POST /wp-json/odata/v4/$batch
Content-Type: application/json
{
    "requests": [
        { "id": "1", "method": "GET", "url": "Posts(1)" },
        { "id": "2", "method": "GET", "url": "Users(1)" },
        { "id": "3", "method": "PATCH", "url": "Posts(1)",
          "headers": { "Content-Type": "application/json" },
          "body": { "Status": "draft" } }
    ]
}
```

---

## Appendix C — Key Rules Quick Reference

| Rule | Reason |
|---|---|
| `apply_filters()` and `do_action()` only in `WPOS_Hook_Bridge` | One place to audit all WP extension points |
| `add_action()` and `add_filter()` only in `WPOS_Hook_Bridge::register()` | One place to audit all WP hook registrations |
| Domain services dispatch `WPOS_Event`, never WP functions | Domain logic testable without WordPress bootstrap |
| Subscribers: one event → one domain call → one WP filter | Thin, traceable, individually testable |
| Events are pure value objects — no logic | Events carry data; logic lives in services |
| Container built once at `plugins_loaded` priority 5 | Single wiring point before anything else runs |
| All public hooks in canonical Section 6 table | No undocumented or scattered hook names |
| `WPOS_Event_Schema_Changed` busts metadata cache | Admin saves and external registrations auto-invalidate |
| Set operations compile to single SQL, no per-row events | Performance: bulk ops stay atomic and fast |
| `user_pass` always excluded from responses | Security: no circumstance leaks password hashes |

---

*This document supersedes:*
- *`wp-odata-implementation-plan.md`*
- *`wp-odata-hooks-separation-of-concerns.md`*

*Spec reference: OASIS OData v4.01 — https://docs.oasis-open.org/odata/odata/v4.01/odata-v4.01-part1-protocol.html*
*Architecture: Hexagonal Architecture (Ports & Adapters) — Alistair Cockburn, adapted for WordPress*
