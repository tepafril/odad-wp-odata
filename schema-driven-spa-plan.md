# Schema-Driven SPA with React.js — Refined Plan

> **Inspired by:** Microsoft Dynamics 365, Frappe Framework, Salesforce Lightning
> **Backend:** WP-OData Suite — OData v4.01 API at `/wp-json/odata/v4/`
> **Goal:** A single React SPA that renders entire CRUD interfaces from pure JSON schema definitions, with zero per-entity `.tsx` files, fully integrated with the existing OData backend.

---

## Table of Contents

1. [The Core Idea](#1-the-core-idea)
2. [Architecture Overview](#2-architecture-overview)
3. [OData Backend Integration](#3-odata-backend-integration)
4. [Schema Specification](#4-schema-specification)
5. [Engine Modules](#5-engine-modules)
6. [Component Architecture](#6-component-architecture)
7. [Routing Strategy](#7-routing-strategy)
8. [State Management](#8-state-management)
9. [OData API Layer](#9-odata-api-layer)
10. [Form Engine Deep Dive](#10-form-engine-deep-dive)
11. [DataTable Engine Deep Dive](#11-datatable-engine-deep-dive)
12. [Filter & Search Engine](#12-filter--search-engine)
13. [Permission & Visibility Rules](#13-permission--visibility-rules)
14. [Extensibility & Overrides](#14-extensibility--overrides)
15. [WordPress Embedding Strategy](#15-wordpress-embedding-strategy)
16. [Project File Structure](#16-project-file-structure)
17. [Tech Stack](#17-tech-stack)
18. [Entity Schemas — All 29 HR Entities](#18-entity-schemas--all-29-hr-entities)
19. [Implementation Phases](#19-implementation-phases)

---

## 1. The Core Idea

Instead of writing `EmployeeList.tsx`, `EmployeeForm.tsx`, `LeaveList.tsx`, `LeaveForm.tsx` for every entity, you write **one schema file per entity**:

```json
// schemas/entities/HrEmployees.json
{
  "entity": "HrEmployees",
  "label": "Employee",
  "labelPlural": "Employees",
  "icon": "Users",
  "odata": {
    "entitySet": "HrEmployees",
    "keyProperty": "id",
    "expand": ["Department", "Position"]
  },
  "table": { ... },
  "filters": { ... },
  "createForm": { ... },
  "editForm": "inherit"
}
```

The **Schema Engine** reads this and dynamically renders:
- A fully-featured DataTable with OData-powered sorting, pagination, filtering
- Filter/search bar that compiles to OData `$filter` expressions
- A Create modal with correct form fields → `POST /wp-json/odata/v4/HrEmployees`
- An Edit modal with pre-filled data → `PATCH /wp-json/odata/v4/HrEmployees(5)`

**You write the schema once. The engine does everything else.**

This is exactly how Microsoft Dynamics handles 1,500+ tables. The WP-OData backend already exposes all 29 HR entities with full CRUD, relations, and permissions — this SPA consumes that existing API.

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         React SPA (WordPress Admin Page)                 │
│                                                                          │
│  ┌──────────────┐    ┌───────────────────────────────────────────────┐  │
│  │ Schema       │    │                Schema Engine                   │  │
│  │ Registry     │───▶│  (reads schema, drives render + OData calls)  │  │
│  │ (JSON files) │    └───────────────────────────────────────────────┘  │
│  └──────────────┘                      │                                │
│                                        ▼                                │
│              ┌─────────────────────────────────────────────┐            │
│              │           Dynamic Route                      │            │
│              │   /entity/:entitySet                         │            │
│              └─────────────────────────────────────────────┘            │
│                              │                                          │
│           ┌──────────────────┼──────────────────┐                       │
│           ▼                  ▼                  ▼                       │
│     ┌──────────┐    ┌─────────────┐   ┌──────────────┐                 │
│     │DataTable │    │ FilterEngine│   │  FormEngine  │                 │
│     │ Engine   │    │ (→ $filter) │   │ (Create/Edit)│                 │
│     └──────────┘    └─────────────┘   └──────────────┘                 │
│           │                  │                  │                       │
│           └──────────────────┴──────────────────┘                       │
│                              │                                          │
│                   ┌──────────▼──────────┐                               │
│                   │   OData API Client  │                               │
│                   │ /wp-json/odata/v4/  │                               │
│                   │ (JWT Bearer Auth)   │                               │
│                   └─────────────────────┘                               │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 3. OData Backend Integration

### 3.1 What the Backend Provides

The existing WP-OData Suite plugin exposes a full **OData v4.01 API**:

| Capability | Endpoint / Query Option |
|---|---|
| List entities | `GET /wp-json/odata/v4/HrEmployees` |
| Paginate | `$top=25&$skip=0` |
| Filter | `$filter=department_id eq 5 and is_active eq true` |
| Sort | `$orderby=full_name asc` |
| Count total | `$count=true` |
| Full-text search | `$search=Ahmad` |
| Project fields | `$select=id,full_name,email,department_id` |
| Expand relations | `$expand=Department,Position` |
| Get single | `GET /wp-json/odata/v4/HrEmployees(5)` |
| Create | `POST /wp-json/odata/v4/HrEmployees` |
| Partial update | `PATCH /wp-json/odata/v4/HrEmployees(5)` |
| Delete | `DELETE /wp-json/odata/v4/HrEmployees(5)` |
| Full schema | `GET /wp-json/odata/v4/$metadata?$format=json` |
| Auth token | `POST /wp-json/odata/v4/auth/token` |

### 3.2 OData Response Format

```json
// Collection response
{
  "@odata.context": "https://site.com/wp-json/odata/v4/$metadata#HrEmployees",
  "@odata.count": 142,
  "value": [
    { "id": 1, "full_name": "Ahmad Hassan", "department_id": 3, ... },
    ...
  ]
}

// Single entity response
{
  "@odata.context": "...$metadata#HrEmployees/$entity",
  "id": 1,
  "full_name": "Ahmad Hassan",
  "department_id": 3,
  "Department": { "id": 3, "name": "Engineering" }
}
```

### 3.3 OData Filter Syntax

Filters are OData expressions, **not** plain query params. The FilterEngine must compile filter field values into OData `$filter` syntax:

| Filter UI Input | OData `$filter` Expression |
|---|---|
| `department = 3` | `department_id eq 3` |
| `status = "active"` | `is_active eq true` |
| `name search = "Ahmad"` | `contains(full_name, 'Ahmad')` |
| `date from/to` | `hired_at ge 2024-01-01T00:00:00Z and hired_at le 2024-12-31T23:59:59Z` |
| Multiple filters | Combined with `and` |

### 3.4 Authentication Flow

```
1. SPA boots → check localStorage for JWT token
2. If none → show login form → POST /wp-json/odata/v4/auth/token
   Body: { "username": "...", "password": "..." }
   Response: { "token": "eyJ...", "user": { ... } }
3. Store token → attach as Authorization: Bearer {token} to all requests
4. On 401 → redirect to login
```

### 3.5 The 29 Existing HR Entity Sets

All already implemented and exposed by the backend:

| Entity Set | Description |
|---|---|
| `HrEmployees` | Core employee profiles |
| `HrDepartments` | Organizational departments |
| `HrPositions` | Job positions/titles |
| `HrCompanies` | Company entities |
| `HrBranches` | Branch offices |
| `HrEmploymentTypes` | Full-time, part-time, contract, etc. |
| `HrLeaveTypes` | Annual, sick, unpaid, etc. |
| `HrLeaveRequests` | Employee leave applications |
| `HrLeaveBalances` | Accrued/used leave per employee |
| `HrLeavePolicies` | Leave rules per policy |
| `HrLeavePolicyDetails` | Days per leave type per policy |
| `HrLeavePolicyAssignments` | Policy → department assignments |
| `HrAttendance` | Daily check-in/check-out records |
| `HrAttendanceRequests` | Attendance correction requests |
| `HrShifts` | Shift definitions (start/end times) |
| `HrShiftAssignments` | Employee → shift assignments |
| `HrTimesheets` | Timesheet entries |
| `HrHolidayLists` | Holiday calendar lists |
| `HrHolidays` | Individual holiday entries |
| `HrSkills` | Skill catalog |
| `HrEmployeeSkills` | Employee → skill with proficiency |
| `HrEmployeeEducation` | Education history |
| `HrEmployeeWorkHistory` | Past employment records |
| `HrEmployeeBank` | Bank account details |
| `HrEmployeeMovement` | Position/department transfers |
| `HrEmployeeDocuments` | Document attachments |
| `HrCompensatoryRequests` | Compensatory leave requests |
| `HrAuditLog` | Change history log |
| `HrNotifications` | User notifications |

---

## 4. Schema Specification

Every entity is described by a **single JSON schema file**. This is the contract between the OData entity set and the UI engine.

### 4.1 Top-Level Structure

```typescript
interface EntitySchema {
  // Identity
  entity: string;              // OData entity set name: "HrEmployees"
  label: string;               // "Employee"
  labelPlural: string;         // "Employees"
  icon?: string;               // Lucide icon name: "Users"

  // OData configuration (replaces generic ApiConfig)
  odata: ODataConfig;

  // DataTable definition
  table: TableSchema;

  // Filter/search bar definition
  filters: FilterSchema;

  // Create form definition
  createForm: FormSchema;

  // Edit form definition (can inherit from createForm)
  editForm: FormSchema | "inherit";

  // Permissions (optional; enforced server-side, mirrored for UX)
  permissions?: PermissionSchema;
}
```

### 4.2 ODataConfig

Replaces the generic `ApiConfig`. Speaks OData natively.

```typescript
interface ODataConfig {
  entitySet: string;           // "HrEmployees" — used in all API calls
  keyProperty: string;         // "id" — used in single-entity URLs: HrEmployees(5)

  // Default $expand for list/detail views
  expand?: string[];           // ["Department", "Position"] — joined automatically

  // Default $select (omit to return all properties)
  select?: string[];

  // Update method: OData standard is PATCH (partial update)
  updateMethod?: "PATCH" | "PUT";  // default "PATCH"
}
```

### 4.3 TableSchema

```typescript
interface TableSchema {
  primaryKey: string;          // "id"
  defaultSort?: string;        // "full_name"
  defaultSortDir?: "asc" | "desc";
  perPage?: number;            // default 25

  columns: ColumnDef[];
  actions: ActionDef[];
  bulkActions?: BulkActionDef[];
  emptyMessage?: string;
}

interface ColumnDef {
  key: string;                 // maps to API field (supports dot notation: "Department.name")
  label: string;
  type: ColumnType;
  sortable?: boolean;
  sortKey?: string;            // OData sort field if different from key: "department_id"
  width?: string;
  hidden?: boolean;

  // For "badge" type
  badgeMap?: Record<string, { label: string; color: string }>;

  // For "relation" type (uses $expand data)
  displayKey?: string;         // "Department.name" — dot path into expanded data

  // For "computed" type
  render?: string;             // template: "{{first_name}} {{last_name}}"
}

type ColumnType =
  | "text"
  | "number"
  | "currency"
  | "date"
  | "datetime"
  | "boolean"
  | "badge"        // colored status chip
  | "avatar"       // image + name combo
  | "relation"     // uses $expand data (no extra API call)
  | "computed"     // derived from template
  | "actions";     // row action buttons column

interface ActionDef {
  key: string;
  label: string;
  icon?: string;
  variant?: "primary" | "secondary" | "danger" | "ghost";
  action: ActionType;
  showIf?: ConditionRule;
  requirePermission?: string;
}

type ActionType =
  | { type: "openEditForm" }
  | { type: "openViewModal" }
  | { type: "delete"; confirm?: string }
  | { type: "navigate"; to: string }    // "/entity/HrLeaveRequests?employee_id={{id}}"
  | { type: "odataAction"; action: string; confirm?: string }  // OData bound action
  | { type: "custom"; handlerKey: string };
```

### 4.4 FilterSchema

```typescript
interface FilterSchema {
  searchable?: boolean;
  searchPlaceholder?: string;

  filters: FilterFieldDef[];
}

interface FilterFieldDef {
  key: string;                   // logical key for filter state
  label: string;
  type: FilterFieldType;

  // OData filter compilation
  odataFilter: ODataFilterDef;   // how to compile this filter into $filter

  // For "select" / "multiselect"
  options?: Array<{ value: string | number; label: string }>;
  optionsFrom?: string;          // entity set: "HrDepartments"
  optionsValueKey?: string;      // "id"
  optionsLabelKey?: string;      // "name"

  // For "daterange"
  startKey?: string;
  endKey?: string;

  defaultValue?: unknown;
  placeholder?: string;
}

// Describes how to compile this filter field into a $filter fragment
type ODataFilterDef =
  | { operator: "eq"; field: string }              // department_id eq {value}
  | { operator: "contains"; field: string }        // contains(full_name, '{value}')
  | { operator: "startswith"; field: string }      // startswith(name, '{value}')
  | { operator: "ge_le"; startField: string; endField: string }  // date range
  | { operator: "in"; field: string }              // id in (1,2,3) — OData 4.01
  | { operator: "custom"; template: string };      // raw template: "{field} ne null"

type FilterFieldType =
  | "text"
  | "select"
  | "multiselect"
  | "date"
  | "daterange"
  | "boolean"
  | "number"
  | "numberrange";
```

### 4.5 FormSchema

```typescript
interface FormSchema {
  layout?: "single-column" | "two-column" | "sections";
  sections?: FormSection[];
  fields: FormFieldDef[];
  submitLabel?: string;
  cancelLabel?: string;
  display?: "modal" | "page" | "drawer";
  modalSize?: "sm" | "md" | "lg" | "xl" | "full";
}

interface FormSection {
  key: string;
  label: string;
  collapsible?: boolean;
  fields: string[];
}

interface FormFieldDef {
  key: string;                 // maps to OData property name
  label: string;
  type: FormFieldType;
  required?: boolean;
  disabled?: boolean;
  readOnly?: boolean;

  placeholder?: string;
  helpText?: string;
  defaultValue?: unknown;

  validation?: ValidationRule[];

  // For "select" / "radio" / "checkbox-group"
  options?: Array<{ value: string | number; label: string }>;
  optionsFrom?: string;        // entity set: "HrDepartments"
  optionsValueKey?: string;
  optionsLabelKey?: string;

  // For "relation" (async autocomplete → OData search)
  relationEntity?: string;     // "HrDepartments"
  relationSearchField?: string; // OData field to search: "name"
  relationSearchOp?: "contains" | "startswith"; // default "contains"
  relationDisplayKey?: string; // "name" — shown in input
  relationValueKey?: string;   // "id" — stored in payload

  // For "file" / "image"
  accept?: string;
  multiple?: boolean;

  // For "number" / "currency"
  min?: number;
  max?: number;
  step?: number;
  currency?: string;

  // Conditional show/hide based on sibling field values
  showIf?: ConditionRule;

  span?: 1 | 2;
}

type FormFieldType =
  | "text"
  | "textarea"
  | "richtext"
  | "number"
  | "currency"
  | "email"
  | "phone"
  | "password"
  | "url"
  | "date"
  | "datetime"
  | "time"
  | "select"
  | "multiselect"
  | "radio"
  | "checkbox"
  | "checkbox-group"
  | "switch"
  | "relation"       // async autocomplete → OData $search or $filter
  | "file"
  | "image"
  | "hidden"
  | "divider"
  | "heading";

interface ValidationRule {
  type: "required" | "min" | "max" | "minLength" | "maxLength" | "pattern" | "email" | "custom";
  value?: unknown;
  message: string;
  handlerKey?: string;
}

interface ConditionRule {
  field: string;
  operator: "eq" | "neq" | "in" | "nin" | "gt" | "lt" | "empty" | "notEmpty";
  value?: unknown;
}
```

---

## 5. Engine Modules

| Engine | Input | Output |
|---|---|---|
| `SchemaEngine` | Full `EntitySchema` | Orchestrates all engines |
| `DataTableEngine` | `TableSchema` + OData response | Rendered table with sorting, pagination, row actions |
| `FilterEngine` | `FilterSchema` + current filters | Filter bar + compiles to `$filter` expression |
| `FormEngine` | `FormSchema` + optional initial data | Create or Edit form with validation |

### 5.1 SchemaEngine — Orchestrator

```
SchemaEngine
├── reads EntitySchema from SchemaRegistry
├── fetches data via useODataQuery (React Query + JWT auth)
│   └── builds: $top, $skip, $filter, $orderby, $count=true, $select, $expand
├── renders FilterEngine (top)
│   └── filter changes → recompile $filter → refetch
├── renders DataTableEngine (middle)
│   ├── sort click → update $orderby → refetch
│   ├── page change → update $skip → refetch
│   ├── row action "edit" → openEditForm → FormEngine (modal)
│   └── row action "delete" → DELETE /EntitySet(id)
└── renders FormEngine (modal/drawer, conditionally)
    ├── create → POST /EntitySet
    └── edit → PATCH /EntitySet(id)
```

### 5.2 SchemaRegistry

```typescript
// src/schema/registry.ts
class SchemaRegistry {
  private schemas: Map<string, EntitySchema> = new Map();
  private columnRenderers: Map<string, ColumnRendererFn> = new Map();
  private fieldTypes: Map<string, React.ComponentType> = new Map();
  private actionHandlers: Map<string, ActionHandlerFn> = new Map();
  private pageOverrides: Map<string, React.ComponentType> = new Map();

  register(schema: EntitySchema): void
  get(entity: string): EntitySchema | undefined
  getAll(): EntitySchema[]

  // Extensibility
  registerColumnRenderer(key: string, fn: ColumnRendererFn): void
  registerFieldType(key: string, component: React.ComponentType): void
  registerActionHandler(key: string, fn: ActionHandlerFn): void
  registerPageOverride(entity: string, component: React.ComponentType): void
}
```

---

## 6. Component Architecture

```
src/
├── engines/
│   ├── SchemaEngine.tsx
│   ├── DataTableEngine/
│   │   ├── index.tsx
│   │   ├── ColumnRenderer.tsx
│   │   ├── ActionButtons.tsx
│   │   ├── BulkActions.tsx
│   │   └── Pagination.tsx
│   ├── FilterEngine/
│   │   ├── index.tsx
│   │   ├── FilterField.tsx
│   │   ├── ODataFilterBuilder.ts    ← compiles FilterState → $filter string
│   │   └── SearchInput.tsx
│   └── FormEngine/
│       ├── index.tsx
│       ├── FormField.tsx
│       ├── FormSection.tsx
│       ├── FormValidator.ts
│       └── FieldTypes/
│           ├── TextField.tsx
│           ├── TextareaField.tsx
│           ├── SelectField.tsx
│           ├── RelationField.tsx    ← OData-powered async autocomplete
│           ├── DateField.tsx
│           ├── DateTimeField.tsx
│           ├── TimeField.tsx
│           ├── SwitchField.tsx
│           ├── NumberField.tsx
│           ├── CurrencyField.tsx
│           ├── MultiSelectField.tsx
│           ├── FileField.tsx
│           ├── ImageField.tsx
│           └── HiddenField.tsx
│
├── schema/
│   ├── types.ts
│   ├── registry.ts
│   ├── conditionEvaluator.ts
│   └── entities/                   ← One JSON per entity set
│       ├── HrEmployees.json
│       ├── HrDepartments.json
│       ├── HrPositions.json
│       ├── HrLeaveRequests.json
│       ├── HrLeaveTypes.json
│       ├── HrLeaveBalances.json
│       ├── HrAttendance.json
│       ├── HrTimesheets.json
│       ├── HrShifts.json
│       ├── HrHolidays.json
│       ├── HrCompanies.json
│       ├── HrBranches.json
│       ├── HrSkills.json
│       ├── HrEmploymentTypes.json
│       ├── HrEmployeeEducation.json
│       ├── HrEmployeeDocuments.json
│       └── ... (all 29 entities)
│
├── api/
│   ├── client.ts                   ← Axios + JWT Bearer interceptor
│   ├── odataApi.ts                 ← OData-specific CRUD
│   ├── odataQueryBuilder.ts        ← Builds $top/$skip/$filter/$orderby/$expand
│   ├── auth.ts                     ← Login, token storage, refresh
│   └── queryKeys.ts
│
├── hooks/
│   ├── useODataQuery.ts            ← List + pagination + sort + filters
│   ├── useODataMutation.ts         ← POST / PATCH / DELETE
│   ├── useFilterState.ts           ← Filter state ↔ URL sync
│   ├── useRelationOptions.ts       ← OData-powered async select options
│   └── useAuth.ts                  ← Current user + JWT management
│
├── components/
│   ├── ui/
│   │   ├── Modal.tsx
│   │   ├── Drawer.tsx
│   │   ├── Button.tsx
│   │   ├── Badge.tsx
│   │   ├── Input.tsx
│   │   ├── Select.tsx
│   │   ├── ConfirmDialog.tsx
│   │   └── Icon.tsx
│   ├── LoginPage.tsx
│   └── AppShell.tsx                ← Sidebar nav + header
│
├── pages/
│   ├── EntityPage.tsx              ← Route: /entity/:entitySet
│   └── DashboardPage.tsx
│
└── utils/
    ├── templateInterpolator.ts     ← "{{first_name}} {{last_name}}"
    ├── columnFormatter.ts          ← date/currency/boolean formatters
    └── odataFilterCompiler.ts      ← Compiles FilterState → $filter string
```

---

## 7. Routing Strategy

The SPA uses **a single dynamic route** for all entity sets. No route file changes when adding a new entity.

```typescript
// App.tsx
<Routes>
  <Route path="/" element={<DashboardPage />} />
  <Route path="/entity/:entitySet" element={<EntityPage />} />
  <Route path="/entity/:entitySet/:id" element={<EntityPage viewMode="detail" />} />
</Routes>
```

```typescript
// pages/EntityPage.tsx
const EntityPage = () => {
  const { entitySet } = useParams();
  const schema = schemaRegistry.get(entitySet);

  // Page-level override registered for this entity?
  const Override = schemaRegistry.getPageOverride(entitySet);
  if (Override) return <Override />;

  if (!schema) return <NotFoundPage />;
  return <SchemaEngine schema={schema} />;
};
```

**URL Examples:**
- `/entity/HrEmployees` → Employee list with table, filters, create button
- `/entity/HrLeaveRequests` → Leave request list
- `/entity/HrLeaveRequests?employee_id=5` → Pre-filtered for employee 5
- `/entity/HrAttendance?date=2024-01-15` → Pre-filtered by date

Filter state lives in the URL query string — filtered views are shareable and bookmarkable.

---

## 8. State Management

### 8.1 Server State — TanStack Query

All OData fetching, caching, mutation, and cache invalidation:

```typescript
// hooks/useODataQuery.ts
export function useODataQuery(schema: EntitySchema, filterState: FilterState) {
  const { odata, table } = schema;
  const params = buildODataParams({ odata, table, filterState });

  return useQuery({
    queryKey: queryKeys.entityList(odata.entitySet, filterState),
    queryFn: () => odataApi.list(odata.entitySet, params),
  });
}

// hooks/useODataMutation.ts
export function useODataMutation(schema: EntitySchema) {
  const qc = useQueryClient();
  const { entitySet, keyProperty } = schema.odata;

  return {
    create: useMutation({
      mutationFn: (data: unknown) => odataApi.create(entitySet, data),
      onSuccess: () => qc.invalidateQueries(queryKeys.entityList(entitySet)),
    }),
    update: useMutation({
      mutationFn: ({ id, data }: { id: string | number; data: unknown }) =>
        odataApi.patch(entitySet, id, data),
      onSuccess: () => qc.invalidateQueries(queryKeys.entityList(entitySet)),
    }),
    delete: useMutation({
      mutationFn: (id: string | number) => odataApi.delete(entitySet, id),
      onSuccess: () => qc.invalidateQueries(queryKeys.entityList(entitySet)),
    }),
  };
}
```

### 8.2 UI State — useState + Zustand

```typescript
interface EntityPageState {
  formMode: "create" | "edit" | null;
  editingRecord: Record<string, unknown> | null;
  selectedRows: (string | number)[];
  openForm: (mode: "create" | "edit", record?: unknown) => void;
  closeForm: () => void;
  toggleRowSelection: (id: string | number) => void;
}
```

### 8.3 Filter State — URL + useState

```typescript
// hooks/useFilterState.ts
export function useFilterState(schema: FilterSchema) {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = parseFiltersFromURL(searchParams, schema);

  const setFilter = (key: string, value: unknown) =>
    setSearchParams(prev => updateSearchParam(prev, key, value));

  const resetFilters = () => setSearchParams({});

  return { filters, setFilter, resetFilters };
}
```

---

## 9. OData API Layer

### 9.1 OData Query Builder

Converts schema + filter state into OData query parameters:

```typescript
// api/odataQueryBuilder.ts
export function buildODataParams(opts: {
  odata: ODataConfig;
  table: TableSchema;
  filterState: FilterState;
  sort?: { field: string; dir: "asc" | "desc" };
  page?: number;
}): URLSearchParams {
  const params = new URLSearchParams();
  const { odata, table, filterState, sort, page = 1 } = opts;
  const perPage = table.perPage ?? 25;

  // Pagination
  params.set("$top", String(perPage));
  params.set("$skip", String((page - 1) * perPage));
  params.set("$count", "true");

  // Sorting
  const sortField = sort?.field ?? table.defaultSort;
  const sortDir = sort?.dir ?? table.defaultSortDir ?? "asc";
  if (sortField) params.set("$orderby", `${sortField} ${sortDir}`);

  // Expand (navigation properties)
  if (odata.expand?.length) params.set("$expand", odata.expand.join(","));

  // Select
  if (odata.select?.length) params.set("$select", odata.select.join(","));

  // Filters → compile to $filter
  const filterExpr = compileODataFilter(filterState);
  if (filterExpr) params.set("$filter", filterExpr);

  // Global search → $search
  if (filterState.$search) params.set("$search", filterState.$search);

  return params;
}
```

### 9.2 OData Filter Compiler

```typescript
// utils/odataFilterCompiler.ts
export function compileODataFilter(
  filterState: FilterState,
  schema: FilterSchema
): string {
  const expressions: string[] = [];

  for (const filterDef of schema.filters) {
    const value = filterState[filterDef.key];
    if (value === undefined || value === null || value === "") continue;

    const { odataFilter } = filterDef;
    let expr: string;

    switch (odataFilter.operator) {
      case "eq":
        expr = typeof value === "string"
          ? `${odataFilter.field} eq '${escapeOData(value)}'`
          : `${odataFilter.field} eq ${value}`;
        break;
      case "contains":
        expr = `contains(${odataFilter.field}, '${escapeOData(String(value))}')`;
        break;
      case "startswith":
        expr = `startswith(${odataFilter.field}, '${escapeOData(String(value))}')`;
        break;
      case "ge_le":
        const parts: string[] = [];
        if (value[filterDef.startKey!])
          parts.push(`${odataFilter.startField} ge ${toODataDate(value[filterDef.startKey!])}`);
        if (value[filterDef.endKey!])
          parts.push(`${odataFilter.endField} le ${toODataDate(value[filterDef.endKey!])}`);
        expr = parts.join(" and ");
        break;
      case "in":
        const list = (value as unknown[])
          .map(v => typeof v === "string" ? `'${escapeOData(v)}'` : v)
          .join(",");
        expr = `${odataFilter.field} in (${list})`;
        break;
      case "custom":
        expr = odataFilter.template.replace("{value}", String(value));
        break;
    }

    if (expr) expressions.push(`(${expr})`);
  }

  return expressions.join(" and ");
}
```

### 9.3 OData API Client

```typescript
// api/odataApi.ts
const BASE = "/wp-json/odata/v4";

export const odataApi = {

  list: async (entitySet: string, params: URLSearchParams) => {
    const res = await apiClient.get(`${BASE}/${entitySet}`, { params });
    return {
      data: res.data.value as Record<string, unknown>[],
      total: res.data["@odata.count"] as number,
    };
  },

  get: async (entitySet: string, id: string | number) => {
    const res = await apiClient.get(`${BASE}/${entitySet}(${id})`);
    return res.data as Record<string, unknown>;
  },

  create: async (entitySet: string, payload: unknown) => {
    const res = await apiClient.post(`${BASE}/${entitySet}`, payload);
    return res.data;
  },

  patch: async (entitySet: string, id: string | number, payload: unknown) => {
    const res = await apiClient.patch(`${BASE}/${entitySet}(${id})`, payload);
    return res.data;
  },

  put: async (entitySet: string, id: string | number, payload: unknown) => {
    const res = await apiClient.put(`${BASE}/${entitySet}(${id})`, payload);
    return res.data;
  },

  delete: async (entitySet: string, id: string | number) => {
    await apiClient.delete(`${BASE}/${entitySet}(${id})`);
  },

  // OData bound action (e.g., approve/reject leave request)
  action: async (entitySet: string, id: string | number, actionName: string, payload?: unknown) => {
    const res = await apiClient.post(`${BASE}/${entitySet}(${id})/${actionName}`, payload ?? {});
    return res.data;
  },
};
```

### 9.4 Axios Client with JWT Interceptor

```typescript
// api/client.ts
import axios from "axios";

export const apiClient = axios.create({
  headers: { "Content-Type": "application/json" },
});

// Attach JWT token to every request
apiClient.interceptors.request.use(config => {
  const token = localStorage.getItem("odata_jwt");
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Handle 401 → redirect to login
apiClient.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      localStorage.removeItem("odata_jwt");
      window.location.href = "/#/login";
    }
    return Promise.reject(err);
  }
);
```

---

## 10. Form Engine Deep Dive

### 10.1 FormEngine Flow

```
FormEngine receives: { schema: FormSchema, initialData?: object, mode: "create"|"edit" }
│
├── useForm() (React Hook Form)
│   └── defaultValues = schema.fields[].defaultValue merged with initialData
│
├── watch() all field values → used for showIf evaluation
│
├── render layout (single-column | two-column | sections)
│   └── each field → <FormField field={def} control={control} allValues={watch()} />
│       ├── evaluates showIf → null if hidden
│       └── renders correct FieldType component
│
└── onSubmit
    ├── validate via React Hook Form + Zod
    ├── mode="create" → POST /wp-json/odata/v4/{entitySet}
    ├── mode="edit"   → PATCH /wp-json/odata/v4/{entitySet}({id})
    ├── on success → invalidate React Query cache → close form
    └── on error → display OData error message (res.data.error.message)
```

### 10.2 RelationField — OData Autocomplete

The `RelationField` powers linked-entity autocomplete. It queries the related OData entity set using `$search` or `$filter`:

```typescript
// engines/FormEngine/FieldTypes/RelationField.tsx
const RelationField = ({ value, onChange, fieldDef }) => {
  const [input, setInput] = useState("");
  const relatedSchema = schemaRegistry.get(fieldDef.relationEntity);

  const { data } = useQuery({
    queryKey: ["relation-options", fieldDef.relationEntity, input],
    queryFn: async () => {
      const op = fieldDef.relationSearchOp ?? "contains";
      const field = fieldDef.relationSearchField ?? "name";
      const filter = `${op}(${field}, '${input}')`;
      const params = new URLSearchParams({ $top: "20", $filter: filter });
      return odataApi.list(fieldDef.relationEntity, params);
    },
    enabled: input.length >= 1,
    staleTime: 30_000,
  });

  return (
    <AsyncSelect
      options={data?.data.map(item => ({
        value: get(item, fieldDef.relationValueKey ?? "id"),
        label: get(item, fieldDef.relationDisplayKey ?? "name"),
      }))}
      onInputChange={setInput}
      onChange={opt => onChange(opt?.value)}
      value={...}
      placeholder={fieldDef.placeholder}
    />
  );
};
```

---

## 11. DataTable Engine Deep Dive

### 11.1 DataTableEngine Flow

```
DataTableEngine receives: { schema: TableSchema, data: [], total: number, ... }
│
├── Column header click (sortable=true) → update sort → refetch with new $orderby
├── Pagination controls → update page → refetch with new $skip
│
├── Render <thead>
│   └── for each ColumnDef → <ColumnHeader> (with sort indicators if sortable)
│
├── Render <tbody>
│   └── for each row → <TableRow>
│       └── for each ColumnDef → <ColumnRenderer type={col.type} value={...} />
│           ├── "text"     → plain text
│           ├── "badge"    → <Badge color={badgeMap[value].color}>
│           ├── "date"     → formatted date (date-fns)
│           ├── "boolean"  → ✓ / ✗ icon
│           ├── "currency" → formatted with currency symbol
│           ├── "avatar"   → avatar + name
│           ├── "relation" → dot-path into $expand data (no extra API call)
│           ├── "computed" → template string "{{first_name}} {{last_name}}"
│           └── "actions"  → <ActionButtons actions={schema.actions} row={row} />
│
└── Render <tfoot> pagination (OData: $skip-based)
```

### 11.2 Action Handler Dispatch

```typescript
// SchemaEngine.tsx
const handleAction = (action: ActionDef, row: Record<string, unknown>) => {
  const id = row[schema.table.primaryKey];

  switch (action.action.type) {
    case "openEditForm":
      openForm("edit", row);
      break;
    case "delete":
      confirmDelete(
        action.action.confirm ?? "Delete this record?",
        () => deleteMutation.mutate(id as string | number)
      );
      break;
    case "navigate":
      navigate(interpolateTemplate(action.action.to, row));
      break;
    case "odataAction":
      // POST /wp-json/odata/v4/{entitySet}({id})/{action}
      odataApi.action(schema.odata.entitySet, id, action.action.action)
        .then(() => qc.invalidateQueries(queryKeys.entityList(schema.odata.entitySet)));
      break;
    case "custom":
      schemaRegistry.getActionHandler(action.action.handlerKey)?.(row);
      break;
  }
};
```

---

## 12. Filter & Search Engine

### 12.1 FilterEngine

The FilterEngine renders filter inputs and compiles them into OData `$filter` expression on every change:

```typescript
// FilterEngine/index.tsx
const FilterEngine = ({ schema, filterState, onFilterChange }) => {
  return (
    <div className="filter-bar">
      {schema.searchable && (
        <SearchInput
          placeholder={schema.searchPlaceholder ?? "Search..."}
          value={filterState.$search ?? ""}
          onChange={value => onFilterChange("$search", value)}
        />
      )}
      {schema.filters.map(filter => (
        <FilterField
          key={filter.key}
          filter={filter}
          value={filterState[filter.key]}
          onChange={value => onFilterChange(filter.key, value)}
        />
      ))}
    </div>
  );
};
```

### 12.2 Options from OData entity sets

Filter fields with `optionsFrom` load their options from another entity set:

```typescript
// hooks/useRelationOptions.ts
export function useRelationOptions(entitySet: string, valueKey: string, labelKey: string) {
  return useQuery({
    queryKey: ["filter-options", entitySet],
    queryFn: async () => {
      const params = new URLSearchParams({ $top: "200", $select: `${valueKey},${labelKey}` });
      const result = await odataApi.list(entitySet, params);
      return result.data.map(item => ({
        value: get(item, valueKey),
        label: get(item, labelKey),
      }));
    },
    staleTime: 5 * 60 * 1000,
  });
}
```

---

## 13. Permission & Visibility Rules

The OData backend enforces permissions server-side via `ODAD_Permission_Engine`. The SPA mirrors permissions client-side for UX (hide buttons the user can't use anyway), but the server is the source of truth.

```typescript
interface PermissionSchema {
  canCreate?: boolean | string;   // true | "hr_admin"
  canEdit?: boolean | string;
  canDelete?: boolean | string;
  canView?: boolean | string;
  fieldPermissions?: Record<string, {
    readable?: boolean | string;
    writable?: boolean | string;
  }>;
}
```

At boot, the SPA fetches the current user from the JWT token payload (decoded client-side) or from a `GET /wp-json/odata/v4/auth/me` endpoint (if added), then intersects with schema-level `permissions` before rendering action buttons and form fields.

---

## 14. Extensibility & Overrides

For the rare entity needing custom UI, the engine supports override slots without forking engine code:

```typescript
// Register a custom column renderer
schemaRegistry.registerColumnRenderer("leave_status", (value, row) => (
  <LeaveStatusBadge value={value} startDate={row.start_date} />
));

// Register a custom form field type
schemaRegistry.registerFieldType("employee_avatar_picker", EmployeeAvatarPickerComponent);

// Register a custom action handler
schemaRegistry.registerActionHandler("approve_leave", async (row) => {
  await odataApi.action("HrLeaveRequests", row.id, "ODAD.ApproveLeave");
  toast.success("Leave approved");
});

// Full page-level override for one entity
schemaRegistry.registerPageOverride("HrEmployees", EmployeePageWithTimeline);
```

95% of entities use pure schema rendering. The 5% needing special UI override specific parts.

---

## 15. WordPress Embedding Strategy

### 15.1 SPA Location

The SPA lives inside the plugin as a separate Vite project:

```
wp-odata-suite/
├── spa/                         ← Vite + React project root
│   ├── src/
│   ├── public/
│   ├── package.json
│   ├── vite.config.ts
│   └── tsconfig.json
├── assets/
│   └── spa/                     ← Vite build output (gitignored)
│       ├── index.html
│       ├── assets/
│       │   ├── index-[hash].js
│       │   └── index-[hash].css
└── src/admin/
    └── class-odad-admin-spa.php ← WordPress admin page that loads the SPA
```

### 15.2 Vite Config for WordPress

```typescript
// spa/vite.config.ts
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { resolve } from "path";

export default defineConfig({
  plugins: [react()],
  base: "/wp-content/plugins/wp-odata-suite/assets/spa/",
  build: {
    outDir: resolve(__dirname, "../assets/spa"),
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, "index.html"),
    },
  },
});
```

### 15.3 WordPress Admin Page Integration

```php
// src/admin/class-odad-admin-spa.php
class ODAD_Admin_SPA {

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_spa' ] );
    }

    public function add_menu_page(): void {
        add_menu_page(
            'HR Suite',
            'HR Suite',
            'read',
            'odad-hr-spa',
            [ $this, 'render_spa_page' ],
            'dashicons-groups',
            30
        );
    }

    public function render_spa_page(): void {
        echo '<div id="odad-hr-spa-root"></div>';
    }

    public function enqueue_spa( string $hook ): void {
        if ( $hook !== 'toplevel_page_odad-hr-spa' ) return;

        $manifest = $this->read_vite_manifest();
        $entry    = $manifest['src/main.tsx'];

        wp_enqueue_script(
            'odad-hr-spa',
            plugins_url( "assets/spa/{$entry['file']}", ODAD_PLUGIN_FILE ),
            [],
            null,
            true
        );

        if ( ! empty( $entry['css'] ) ) {
            foreach ( $entry['css'] as $css ) {
                wp_enqueue_style(
                    'odad-hr-spa-' . md5( $css ),
                    plugins_url( "assets/spa/{$css}", ODAD_PLUGIN_FILE )
                );
            }
        }

        // Pass WP nonce + API base to SPA
        wp_localize_script( 'odad-hr-spa', 'ODAD_CONFIG', [
            'apiBase' => rest_url( 'odata/v4' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'siteUrl' => get_site_url(),
        ] );
    }

    private function read_vite_manifest(): array {
        $manifest_path = plugin_dir_path( ODAD_PLUGIN_FILE ) . 'assets/spa/.vite/manifest.json';
        return json_decode( file_get_contents( $manifest_path ), true );
    }
}
```

### 15.4 SPA reads WordPress config

```typescript
// api/client.ts
declare const ODAD_CONFIG: {
  apiBase: string;
  nonce: string;
  siteUrl: string;
};

export const apiClient = axios.create({
  baseURL: typeof ODAD_CONFIG !== "undefined"
    ? ODAD_CONFIG.apiBase          // WordPress context: use localized URL
    : "/wp-json/odata/v4",         // Standalone dev context
});
```

### 15.5 Development Workflow

```bash
# In spa/ directory
npm run dev        # Vite dev server at localhost:5173

# For dev against real WP backend, proxy in vite.config.ts:
server: {
  proxy: {
    "/wp-json": "http://odad-hr.local"
  }
}

# Build for production (outputs to assets/spa/)
npm run build
```

---

## 16. Project File Structure

```
wp-odata-suite/
├── spa/                              ← NEW: SPA project root
│   ├── src/
│   │   ├── main.tsx
│   │   ├── App.tsx
│   │   ├── schema/
│   │   │   ├── types.ts
│   │   │   ├── registry.ts
│   │   │   ├── conditionEvaluator.ts
│   │   │   └── entities/            ← 29 JSON schema files
│   │   │       ├── HrEmployees.json
│   │   │       ├── HrDepartments.json
│   │   │       ├── HrLeaveRequests.json
│   │   │       └── ... (all 29)
│   │   ├── engines/
│   │   │   ├── SchemaEngine.tsx
│   │   │   ├── DataTableEngine/
│   │   │   ├── FilterEngine/
│   │   │   │   └── ODataFilterBuilder.ts
│   │   │   └── FormEngine/
│   │   ├── api/
│   │   │   ├── client.ts            ← Axios + JWT Bearer + WP nonce
│   │   │   ├── odataApi.ts          ← OData CRUD
│   │   │   ├── odataQueryBuilder.ts ← $top/$skip/$filter/$orderby/$expand
│   │   │   ├── auth.ts
│   │   │   └── queryKeys.ts
│   │   ├── hooks/
│   │   │   ├── useODataQuery.ts
│   │   │   ├── useODataMutation.ts
│   │   │   ├── useFilterState.ts
│   │   │   ├── useRelationOptions.ts
│   │   │   └── useAuth.ts
│   │   ├── components/
│   │   │   ├── ui/
│   │   │   ├── LoginPage.tsx
│   │   │   └── AppShell.tsx
│   │   ├── pages/
│   │   │   ├── EntityPage.tsx
│   │   │   └── DashboardPage.tsx
│   │   └── utils/
│   │       ├── templateInterpolator.ts
│   │       ├── columnFormatter.ts
│   │       └── odataFilterCompiler.ts
│   ├── package.json
│   ├── tsconfig.json
│   └── vite.config.ts
│
├── assets/
│   └── spa/                          ← Vite build output
│
├── src/
│   └── admin/
│       └── class-odad-admin-spa.php  ← NEW: Enqueues SPA in WP admin
│
└── (existing plugin structure unchanged)
```

---

## 17. Tech Stack

| Concern | Choice | Reason |
|---|---|---|
| Framework | React 18 + TypeScript | Type-safe schema contracts |
| Build | Vite | Fast dev, proxy to WP backend |
| Routing | React Router v6 | Dynamic `:entitySet` routes |
| Server state | TanStack Query v5 | Cache, invalidation, pagination |
| Forms | React Hook Form + Zod | Schema-driven validation |
| UI Base | shadcn/ui + Tailwind CSS | Headless, fully customizable |
| Icons | Lucide React | Schema references icon by name |
| Date | date-fns | Lightweight |
| Rich Text | Tiptap | Extensible, headless |
| Async Select | react-select | RelationField autocomplete |
| HTTP Client | Axios | JWT interceptor + proxy support |
| Notifications | Sonner | Toast notifications |
| OData parsing | Custom (thin layer) | No suitable OData client library needed; query building is straightforward |

---

## 18. Entity Schemas — All 29 HR Entities

### Priority 1: Core (implement first)
1. **`HrEmployees`** — `$expand=Department,Position,Branch,EmploymentType`
2. **`HrDepartments`** — simple, used as optionsFrom by many entities
3. **`HrPositions`** — `$expand=Department`
4. **`HrLeaveRequests`** — `$expand=Employee,LeaveType` + badge for status
5. **`HrLeaveTypes`** — lookup table
6. **`HrLeaveBalances`** — `$expand=Employee,LeaveType`
7. **`HrAttendance`** — `$expand=Employee` + date range filter
8. **`HrTimesheets`** — `$expand=Employee`

### Priority 2: Organization
9. **`HrCompanies`**
10. **`HrBranches`** — `$expand=Company`
11. **`HrEmploymentTypes`**
12. **`HrShifts`**
13. **`HrShiftAssignments`** — `$expand=Employee,Shift`
14. **`HrHolidays`** — `$expand=HolidayList`
15. **`HrHolidayLists`**

### Priority 3: Employee Detail (used in employee profile sub-views)
16. **`HrEmployeeEducation`** — `$expand=Employee`
17. **`HrEmployeeWorkHistory`** — `$expand=Employee`
18. **`HrEmployeeBank`** — `$expand=Employee` + restricted field visibility
19. **`HrEmployeeDocuments`** — `$expand=Employee`
20. **`HrEmployeeMovement`** — `$expand=Employee,OldPosition,NewPosition`
21. **`HrEmployeeSkills`** — `$expand=Employee,Skill`
22. **`HrSkills`**

### Priority 4: Policies & Requests
23. **`HrLeavePolicies`**
24. **`HrLeavePolicyDetails`** — `$expand=Policy,LeaveType`
25. **`HrLeavePolicyAssignments`** — `$expand=Policy,Department`
26. **`HrAttendanceRequests`** — `$expand=Employee`
27. **`HrCompensatoryRequests`** — `$expand=Employee`

### Priority 5: System
28. **`HrAuditLog`** — read-only, no create/edit/delete actions
29. **`HrNotifications`** — read-only list + mark-as-read action

### Sample Schema: `HrLeaveRequests.json`

```json
{
  "entity": "HrLeaveRequests",
  "label": "Leave Request",
  "labelPlural": "Leave Requests",
  "icon": "Calendar",
  "odata": {
    "entitySet": "HrLeaveRequests",
    "keyProperty": "id",
    "expand": ["Employee", "LeaveType"],
    "updateMethod": "PATCH"
  },
  "table": {
    "primaryKey": "id",
    "defaultSort": "created_at",
    "defaultSortDir": "desc",
    "perPage": 25,
    "columns": [
      { "key": "id", "label": "#", "type": "number", "width": "60px" },
      { "key": "Employee.full_name", "label": "Employee", "type": "relation", "sortable": false },
      { "key": "LeaveType.name", "label": "Leave Type", "type": "relation", "sortable": false },
      { "key": "start_date", "label": "From", "type": "date", "sortable": true },
      { "key": "end_date", "label": "To", "type": "date", "sortable": true },
      { "key": "days", "label": "Days", "type": "number" },
      {
        "key": "status",
        "label": "Status",
        "type": "badge",
        "badgeMap": {
          "pending":  { "label": "Pending",  "color": "yellow" },
          "approved": { "label": "Approved", "color": "green" },
          "rejected": { "label": "Rejected", "color": "red" },
          "cancelled":{ "label": "Cancelled","color": "gray" }
        }
      },
      { "key": "_actions", "label": "", "type": "actions" }
    ],
    "actions": [
      {
        "key": "view",
        "label": "View",
        "icon": "Eye",
        "variant": "ghost",
        "action": { "type": "openViewModal" }
      },
      {
        "key": "edit",
        "label": "Edit",
        "icon": "Pencil",
        "variant": "secondary",
        "action": { "type": "openEditForm" },
        "showIf": { "field": "status", "operator": "eq", "value": "pending" }
      },
      {
        "key": "approve",
        "label": "Approve",
        "icon": "CheckCircle",
        "variant": "primary",
        "action": { "type": "odataAction", "action": "ODAD.ApproveLeave", "confirm": "Approve this leave request?" },
        "showIf": { "field": "status", "operator": "eq", "value": "pending" },
        "requirePermission": "hr_admin"
      },
      {
        "key": "delete",
        "label": "Delete",
        "icon": "Trash2",
        "variant": "danger",
        "action": { "type": "delete", "confirm": "Delete this leave request?" },
        "showIf": { "field": "status", "operator": "eq", "value": "pending" }
      }
    ]
  },
  "filters": {
    "searchable": true,
    "searchPlaceholder": "Search by employee name...",
    "filters": [
      {
        "key": "employee_id",
        "label": "Employee",
        "type": "select",
        "optionsFrom": "HrEmployees",
        "optionsValueKey": "id",
        "optionsLabelKey": "full_name",
        "odataFilter": { "operator": "eq", "field": "employee_id" }
      },
      {
        "key": "leave_type_id",
        "label": "Leave Type",
        "type": "select",
        "optionsFrom": "HrLeaveTypes",
        "optionsValueKey": "id",
        "optionsLabelKey": "name",
        "odataFilter": { "operator": "eq", "field": "leave_type_id" }
      },
      {
        "key": "status",
        "label": "Status",
        "type": "select",
        "options": [
          { "value": "pending",  "label": "Pending" },
          { "value": "approved", "label": "Approved" },
          { "value": "rejected", "label": "Rejected" }
        ],
        "odataFilter": { "operator": "eq", "field": "status" }
      },
      {
        "key": "date_range",
        "label": "Date Range",
        "type": "daterange",
        "startKey": "from",
        "endKey": "to",
        "odataFilter": { "operator": "ge_le", "startField": "start_date", "endField": "end_date" }
      }
    ]
  },
  "createForm": {
    "layout": "two-column",
    "display": "modal",
    "modalSize": "lg",
    "fields": [
      {
        "key": "employee_id",
        "label": "Employee",
        "type": "relation",
        "required": true,
        "span": 2,
        "relationEntity": "HrEmployees",
        "relationSearchField": "full_name",
        "relationDisplayKey": "full_name",
        "relationValueKey": "id"
      },
      {
        "key": "leave_type_id",
        "label": "Leave Type",
        "type": "relation",
        "required": true,
        "relationEntity": "HrLeaveTypes",
        "relationSearchField": "name",
        "relationDisplayKey": "name",
        "relationValueKey": "id"
      },
      {
        "key": "status",
        "label": "Status",
        "type": "select",
        "defaultValue": "pending",
        "options": [
          { "value": "pending",  "label": "Pending" },
          { "value": "approved", "label": "Approved" },
          { "value": "rejected", "label": "Rejected" }
        ]
      },
      { "key": "start_date", "label": "Start Date", "type": "date", "required": true },
      { "key": "end_date",   "label": "End Date",   "type": "date", "required": true },
      { "key": "days",       "label": "Days",       "type": "number", "min": 0.5 },
      { "key": "reason",     "label": "Reason",     "type": "textarea", "span": 2 }
    ]
  },
  "editForm": "inherit"
}
```

---

## 19. Implementation Phases

### Phase 0 — SPA Scaffold (Day 1–2)
- [ ] Create `spa/` directory inside plugin root
- [ ] `npm create vite@latest . -- --template react-ts`
- [ ] Install: `tailwindcss`, `react-router-dom`, `@tanstack/react-query`, `react-hook-form`, `zod`, `axios`, `lucide-react`, `react-select`, `sonner`, `date-fns`
- [ ] Install shadcn/ui base components
- [ ] Configure `vite.config.ts` — base URL + proxy to local WP
- [ ] Configure `vite.config.ts` — build output to `../assets/spa/`
- [ ] Create `src/admin/class-odad-admin-spa.php` — WP admin page + asset enqueue

### Phase 1 — OData Foundation (Day 2–4)
- [ ] Define all TypeScript interfaces in `schema/types.ts`
- [ ] Build `SchemaRegistry` class with extensibility hooks
- [ ] Implement `conditionEvaluator.ts`
- [ ] Build `api/client.ts` — Axios with JWT Bearer interceptor
- [ ] Build `api/odataApi.ts` — list, get, create, patch, delete, action
- [ ] Build `api/odataQueryBuilder.ts` — builds OData params from schema + state
- [ ] Build `utils/odataFilterCompiler.ts` — FilterState → `$filter` expression
- [ ] Build `api/auth.ts` — login, token storage, useAuth hook

### Phase 2 — API & Data Hooks (Day 4–5)
- [ ] Build `useODataQuery` — list + pagination + sort + $filter
- [ ] Build `useODataMutation` — create/update/delete with cache invalidation
- [ ] Build `useFilterState` — filter state ↔ URL query string sync
- [ ] Build `useRelationOptions` — OData-powered select options
- [ ] Build `queryKeys` factory keyed on entity set + filter state

### Phase 3 — DataTable Engine (Day 5–7)
- [ ] Build `DataTableEngine` skeleton with column headers + rows
- [ ] Implement `ColumnRenderer` for all 9 column types
- [ ] Handle `"relation"` type using `$expand` data (dot-path navigation)
- [ ] Build `Pagination` — OData `$skip`-based (not page-number)
- [ ] Build `ActionButtons` with `showIf` + `requirePermission` filtering
- [ ] Implement sort (column click → update `$orderby` → refetch)

### Phase 4 — Filter Engine (Day 7–9)
- [ ] Build `FilterEngine` layout (responsive wrapping)
- [ ] Implement all `FilterField` types
- [ ] Build `ODataFilterBuilder` — compiles FilterState → `$filter` string
- [ ] Implement `optionsFrom` filter with `useRelationOptions`
- [ ] Wire filter changes → URL params → `$filter` on next query

### Phase 5 — Form Engine (Day 9–13)
- [ ] Build `FormEngine` with React Hook Form
- [ ] Implement `FormField` dispatch to FieldType components
- [ ] Build all `FieldType` components (15 types)
- [ ] Implement `RelationField` — OData `contains()` autocomplete
- [ ] Implement `showIf` reactive field hiding via `watch()`
- [ ] Zod schema generation from `ValidationRule[]`
- [ ] Handle create vs edit mode (readOnly fields, different field sets)
- [ ] OData error message display (parse `res.data.error.message`)

### Phase 6 — SchemaEngine Orchestration (Day 13–15)
- [ ] Build `SchemaEngine` connecting all three engines
- [ ] Implement action handler dispatch (openEditForm, delete, navigate, odataAction, custom)
- [ ] Build `EntityPage` dynamic route using `useParams().entitySet`
- [ ] Implement form display modes (modal / drawer / full page)
- [ ] Permission intersection logic (schema permissions × current user roles)
- [ ] Build `AppShell` — sidebar with all registered entity schemas as nav links

### Phase 7 — Entity Schemas (Day 15–20)
- [ ] Write 8 Priority 1 schemas (Employees, Departments, Positions, LeaveRequests, etc.)
- [ ] Write 7 Priority 2 schemas (Companies, Branches, etc.)
- [ ] Write 7 Priority 3 schemas (Employee detail entities)
- [ ] Write 7 Priority 4 schemas (Policies, requests)
- [ ] Write 2 Priority 5 schemas (AuditLog, Notifications — read-only)

### Phase 8 — Polish & Production (Day 20–23)
- [ ] Login page with JWT token flow
- [ ] Loading skeletons for table rows
- [ ] Empty state illustrations
- [ ] Error boundaries + OData error message display
- [ ] Custom renderer/field type/action handler registration API
- [ ] Page-level override registration
- [ ] Bulk actions (multi-row select + bulk delete/approve)
- [ ] Keyboard shortcuts (Cmd+K search, Esc to close modals)
- [ ] `npm run build` → verify assets in `assets/spa/`
- [ ] Test in WordPress admin page

---

## Appendix: OData `$filter` Reference

Common patterns used across entity schemas:

```
# Equality
department_id eq 3
status eq 'pending'
is_active eq true

# String matching
contains(full_name, 'Ahmad')
startswith(name, 'Eng')

# Date range
hired_at ge 2024-01-01T00:00:00Z and hired_at le 2024-12-31T23:59:59Z

# Null check
manager_id ne null
approved_by eq null

# Multiple conditions
department_id eq 3 and is_active eq true and contains(full_name, 'Ahmad')

# In list (OData 4.01)
status in ('pending', 'approved')
```

---

*Document version: 2.0 — Refined for WP-OData Suite (OData v4.01 backend, 29 HR entities, WordPress embedding)*
