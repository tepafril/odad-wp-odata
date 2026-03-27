# Task 5.2 — Deep Update

## Dependencies
- Task 5.1 (deep insert — ODAD_Write_Handler skeleton)
- Task 1.2 (deep update events)
- Task 4.1 (permission engine)

## Goal
Implement deep update: PATCH with a delta payload that includes nested navigation
property changes. Supports inserting, updating, and deleting nested entities
in a single operation.

OData example:
```json
PATCH /odata/v4/Posts(42)
{
  "Title": "Updated Title",
  "Meta@delta": [
    { "@removed": {}, "id": 99 },
    { "meta_key": "featured", "meta_value": "1" }
  ]
}
```

---

## File

### `src/write/class-odad-deep-update.php`

```php
class ODAD_Deep_Update {

    public function __construct(
        private ODAD_Adapter_Resolver $adapter_resolver,
        private ODAD_Schema_Registry  $schema_registry,
        private ODAD_Event_Bus        $event_bus,
    ) {}

    /**
     * Execute a deep update.
     *
     * @param string   $entity_set
     * @param mixed    $key         Root entity key
     * @param array    $payload     Delta payload (may contain @delta nav properties)
     * @param \WP_User $user
     * @return array   Updated root entity
     */
    public function execute( string $entity_set, mixed $key, array $payload, \WP_User $user ): array;
}
```

---

## Deep Update Flow

```
execute():
  1. Separate root properties from delta navigation properties
     Delta nav properties have keys ending in '@delta' (e.g. 'Meta@delta')

  2. dispatch(ODAD_Event_Deep_Update_Before)
       → subscriber fires 'ODAD_before_deep_update' WP filter
     If $event->cancelled: return without modifying

  3. Update root entity (if root properties present):
       root_adapter->update($key, $root_payload)

  4. For each delta navigation property:
       For each nested item in the delta array:
         - If '@removed' key present: operation = 'delete'
         - If no key value: operation = 'insert'
         - Otherwise: operation = 'update'

         dispatch(ODAD_Event_Deep_Update_Nested_Before, operation=...)
           → subscriber checks permission for the specific nested operation
           → subscriber fires 'ODAD_nested_entity_payload' filter
         If $event->cancelled: rollback, throw

         nested_adapter->(insert|update|delete)(...)
         Update relationship if needed

  5. dispatch(ODAD_Event_Deep_Update_After)
       → subscriber fires 'ODAD_deep_updated' action

  6. Return updated root entity
```

---

## Delta Payload Format

OData v4.01 delta for navigation properties uses `@delta` suffix:

```json
{
  "Title": "New Title",
  "Tags@delta": [
    { "Name": "php" },
    { "@removed": { "reason": "deleted" }, "ID": 5 }
  ]
}
```

Items without `@removed` are either inserts (no key) or updates (has key).
Items with `@removed` are deletes — use the key to identify which nested entity.

---

## Subscriber: `class-odad-subscriber-deep-update.php`

Pattern mirrors `ODAD_Subscriber_Deep_Insert`. Register for:
- `ODAD_Event_Deep_Update_Before`
- `ODAD_Event_Deep_Update_Nested_Before`
- `ODAD_Event_Deep_Update_After`

Fires:
- `ODAD_before_deep_update` filter
- `ODAD_nested_entity_payload` filter
- `ODAD_deep_updated` action

Permission checks for nested operations:
- `insert` → `can_insert(nested_entity_set)`
- `update` → `can_update(nested_entity_set, nested_key)`
- `delete` → `can_delete(nested_entity_set, nested_key)`

---

## Acceptance Criteria

- `PATCH /odata/v4/Posts(42)` with `Tags@delta` array inserts new tags and deletes removed ones.
- `@removed` items trigger nested delete, not update.
- `ODAD_before_deep_update` filter fires once for the whole operation.
- Per-nested-entity permission is checked for the specific operation (insert/update/delete).
- `ODAD_deep_updated` action fires on success.
- Partial failure (one nested op fails) does not leave database in inconsistent state.
