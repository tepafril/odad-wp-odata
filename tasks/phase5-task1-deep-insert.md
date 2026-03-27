# Task 5.1 — Deep Insert

## Dependencies
- Task 2.1 (adapter interface + resolver)
- Task 1.2 (deep insert events from Task 1.2)
- Task 4.1 (permission engine — for nested entity permission checks)

## Goal
Implement deep insert: when a POST body contains nested navigation property data,
the plugin inserts the root entity and all nested entities atomically.

OData example:
```json
POST /odata/v4/Posts
{
  "Title": "Hello World",
  "Status": "publish",
  "Tags": [
    { "Name": "php", "Taxonomy": "post_tag" }
  ]
}
```

---

## File 1: `src/write/class-wpos-deep-insert.php`

```php
class ODAD_Deep_Insert {

    public function __construct(
        private ODAD_Adapter_Resolver  $adapter_resolver,
        private ODAD_Schema_Registry   $schema_registry,
        private ODAD_Event_Bus         $event_bus,
    ) {}

    /**
     * Execute a deep insert for a root entity and any nested navigation property data.
     *
     * @param string   $entity_set  Root entity set name
     * @param array    $payload     Full nested payload including navigation properties
     * @param \WP_User $user
     * @return array   The inserted root entity (with generated key)
     * @throws \RuntimeException on failure — everything rolls back
     */
    public function execute( string $entity_set, array $payload, \WP_User $user ): array;
}
```

---

## Deep Insert Flow

```
execute():
  1. Separate $payload into root properties vs. navigation properties
     (nav properties are those that match nav_properties in the entity type definition)

  2. dispatch(ODAD_Event_Deep_Insert_Before)
       → subscriber fires 'ODAD_before_deep_insert' WP filter
     If $event->cancelled: throw \RuntimeException('Deep insert cancelled')

  3. Insert root entity:
       root_adapter = resolver->resolve($entity_set)
       root_key = root_adapter->insert(root_payload)

  4. For each navigation property with nested data:
       a. dispatch(ODAD_Event_Deep_Insert_Nested_Before)
            → subscriber checks nested entity permission
            → subscriber fires 'ODAD_nested_entity_payload' WP filter
          If $event->cancelled: rollback root + all previous nested inserts, throw

       b. Resolve nested adapter from nav property definition
       c. nested_adapter->insert(nested_payload)
       d. Create the relationship (e.g. for Tags: insert into wp_term_relationships)

  5. dispatch(ODAD_Event_Deep_Insert_After)
       → subscriber fires 'ODAD_deep_inserted' WP action

  6. Return full root entity (re-fetched from adapter to include generated fields)
```

---

## Relationship Creation

After inserting a nested entity, the link between parent and child must be created:

| Parent → Nav | Relationship strategy |
|---|---|
| Post → Tags | `wp_set_object_terms( $post_id, $term_ids, $taxonomy )` |
| Post → Meta | `add_post_meta( $post_id, $meta_key, $meta_value )` |
| Post → Comments | Set `comment_post_ID` on the comment |
| Employee → User | Set a meta value linking the two |

The relationship strategy is defined in the nav property definition under a
`'relationship'` key (added to adapter definitions in this task).

---

## `ODAD_Write_Handler` Update

Create `src/write/class-wpos-write-handler.php` if not yet created:

```php
class ODAD_Write_Handler {

    public function __construct(
        private ODAD_Adapter_Resolver $adapter_resolver,
        private ODAD_Deep_Insert      $deep_insert,
        private ODAD_Deep_Update      $deep_update,
        private ODAD_Set_Operations   $set_operations,
        private ODAD_Event_Bus        $event_bus,
    ) {}

    public function insert( string $entity_set, array $payload, \WP_User $user ): array;
    public function update( string $entity_set, mixed $key, array $payload, \WP_User $user ): array;
    public function delete( string $entity_set, mixed $key, \WP_User $user ): void;
}
```

`insert()` detects whether the payload contains navigation properties to decide
whether to call `deep_insert->execute()` or a simple `adapter->insert()`.

---

## Subscriber: `class-wpos-subscriber-deep-insert.php`

Flesh out the stub from Task 1.3:

```php
class ODAD_Subscriber_Deep_Insert implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Permission_Engine $permissions,
        private ODAD_Hook_Bridge       $bridge,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Deep_Insert_Before::class;
    }

    public function handle( ODAD_Event $event ): void {
        // Handle both ODAD_Event_Deep_Insert_Before and ODAD_Event_Deep_Insert_Nested_Before
        // (register this subscriber for both events)
        if ( $event instanceof ODAD_Event_Deep_Insert_Before ) {
            $event->payload = $this->bridge->filter(
                'ODAD_before_deep_insert',
                $event->payload,
                [ $event->entity_set, $event->user ]
            );
        }

        if ( $event instanceof ODAD_Event_Deep_Insert_Nested_Before ) {
            // Check nested entity permission
            $can = $this->permissions->can_insert( $event->nested_entity_set, $event->user );
            if ( ! $can ) {
                $event->cancelled = true;
                return;
            }
            // Apply per-nested-entity filter
            $event->nested_payload = $this->bridge->filter(
                'ODAD_nested_entity_payload',
                $event->nested_payload,
                [ $event->parent_entity_set, $event->nested_entity_set, $event->user ]
            );
        }

        if ( $event instanceof ODAD_Event_Deep_Insert_After ) {
            $this->bridge->action( 'ODAD_deep_inserted', [
                $event->entity_set, $event->key, $event->result,
            ]);
        }
    }
}
```

Register this subscriber for all three deep insert event types.

---

## Acceptance Criteria

- `POST /odata/v4/Posts` with `Tags` array creates post + term relationships atomically.
- `ODAD_before_deep_insert` filter fires before any insert.
- `ODAD_nested_entity_payload` filter fires for each nested entity.
- If inserting a nested entity fails, the root entity insert is rolled back (or the response reflects the error).
- `ODAD_deep_inserted` action fires after full success.
- Nested entity permission is checked per entity set.
- If nested entity permission is denied, the whole operation is cancelled with 403.
