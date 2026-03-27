# Task 5.3 — Set-Based Operations (PATCH/$each + DELETE/$each)

## Dependencies
- Task 3.1–3.2 (filter parser + compiler)
- Task 4.1 (permission engine)
- Task 1.2 (set operation events)

## Goal
Implement OData v4.01 set-based operations: bulk PATCH and DELETE applied to all
entities matching a filter, compiled to a **single atomic SQL statement**.

OData examples:
```
PATCH /odata/v4/Posts/$filter=@f/$each?@f=Status eq 'draft'
Body: { "Status": "publish" }

DELETE /odata/v4/Posts/$filter=@f/$each?@f=Status eq 'auto-draft'
```

---

## File

### `src/write/class-wpos-set-operations.php`

```php
class ODAD_Set_Operations {

    public function __construct(
        private ODAD_Adapter_Resolver $adapter_resolver,
        private ODAD_Filter_Parser    $filter_parser,
        private ODAD_Filter_Compiler  $filter_compiler,
        private ODAD_Event_Bus        $event_bus,
    ) {}

    /**
     * Execute a set-based PATCH (update all matching entities).
     *
     * @param string   $entity_set
     * @param string   $filter_expression  OData $filter string
     * @param array    $payload            Properties to update
     * @param \WP_User $user
     * @return int     Number of affected rows
     */
    public function patch_each(
        string   $entity_set,
        string   $filter_expression,
        array    $payload,
        \WP_User $user
    ): int;

    /**
     * Execute a set-based DELETE (delete all matching entities).
     *
     * @param string   $entity_set
     * @param string   $filter_expression
     * @param \WP_User $user
     * @return int     Number of deleted rows
     */
    public function delete_each(
        string   $entity_set,
        string   $filter_expression,
        \WP_User $user
    ): int;
}
```

---

## Execution Flow

```
patch_each():
  1. Build ODAD_Query_Context from filter_expression
  2. dispatch(ODAD_Event_Set_Operation_Before, operation='patch')
       → subscriber: check ODAD_can_update on entity set
       → subscriber: fire 'ODAD_before_set_operation' filter (can modify filter_ctx or payload)
     If $event->cancelled: return 0

  3. Compile filter to SQL WHERE via filter_compiler
  4. Build single SQL UPDATE:
       UPDATE {table} SET col1=%s, col2=%s WHERE {compiled_filter}
  5. Execute via $wpdb->query($wpdb->prepare(...))
     Wrap in $wpdb transaction if possible (BEGIN ... COMMIT)

  6. dispatch(ODAD_Event_Set_Operation_After)
       → subscriber fires 'ODAD_set_operation_completed' action

  7. Return $wpdb->rows_affected
```

**Critical design constraint from the master plan:**
> Set operations compile to a single SQL statement for atomicity.
> They do NOT loop over individual entities and fire per-row write events.
> Per-row lifecycle hooks require single-entity writes instead.

---

## URL Pattern (Router)

Add routes to `ODAD_Router`:
```
PATCH  /odata/v4/{entity}/$filter(@x)/$each?@x={expression}
DELETE /odata/v4/{entity}/$filter(@x)/$each?@x={expression}
```

Parse `@x` from the URL alias and `?@x=...` from query params.

---

## Subscriber: `class-wpos-subscriber-set-operation.php`

Flesh out the stub from Task 1.3:

```php
class ODAD_Subscriber_Set_Operation implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Permission_Engine $permissions,
        private ODAD_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Set_Operation_Before::class;
    }

    public function handle( ODAD_Event $event ): void {
        if ( $event instanceof ODAD_Event_Set_Operation_Before ) {
            // Permission check
            $operation = $event->operation === 'patch' ? 'update' : 'delete';
            $granted = $this->permissions->can( $event->entity_set, $operation, $event->user );
            if ( ! $granted ) {
                $event->cancelled = true;
                return;
            }
            // Allow plugins to modify the filter context and payload
            $event->filter_ctx = $this->bridge->filter(
                'ODAD_before_set_operation',
                $event->filter_ctx,
                [ $event->entity_set, $event->operation, $event->user ]
            );
        }

        if ( $event instanceof ODAD_Event_Set_Operation_After ) {
            $this->bridge->action( 'ODAD_set_operation_completed', [
                $event->entity_set, $event->operation, $event->affected_count,
            ]);
        }
    }
}
```

---

## Acceptance Criteria

- `PATCH /odata/v4/Posts/$filter(@f)/$each?@f=Status eq 'draft'` with body `{"Status":"publish"}` updates all draft posts in one SQL statement.
- `DELETE /odata/v4/Posts/$filter(@f)/$each?@f=Status eq 'auto-draft'` deletes matching posts.
- Only one SQL query is executed (not a loop of individual updates).
- `ODAD_before_set_operation` filter fires before the SQL executes.
- `ODAD_set_operation_completed` action fires with correct `$affected_count`.
- Permission check uses `ODAD_can_update`/`ODAD_can_delete` on the entity set.
- An invalid filter expression returns 400 with an OData error body.
