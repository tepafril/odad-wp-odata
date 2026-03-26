# Task 4.2 — Field ACL

## Dependencies
- Task 4.1 (WPOS_Permission_Engine)
- Task 2.1 (adapter interface — for property definitions with `required_capability`)

## Goal
Build `WPOS_Field_ACL` which strips properties from query results that the
current user is not permitted to see, and validates write payloads to prevent
writing to fields the user cannot modify.

---

## File

### `src/permissions/class-wpos-field-acl.php`

```php
class WPOS_Field_ACL {

    public function __construct(
        private WPOS_Permission_Engine $permission_engine,
    ) {}

    /**
     * Strip properties from result rows that the user cannot read.
     *
     * @param array[]  $rows        Array of entity row arrays
     * @param string   $entity_set
     * @param \WP_User $user
     * @param string   $operation   'read'
     * @return array[]  Filtered rows
     */
    public function apply( array $rows, string $entity_set, \WP_User $user, string $operation ): array;

    /**
     * Get the list of allowed property names for this user + operation.
     * This is what the wpos_allowed_properties filter starts from.
     *
     * @return string[]
     */
    public function get_allowed_properties( string $entity_set, \WP_User $user, string $operation ): array;

    /**
     * Validate that a write payload only contains properties the user can write.
     * Throws WPOS_Field_ACL_Exception if forbidden properties are present.
     *
     * @param array    $payload     Entity data being written
     * @param string   $entity_set
     * @param \WP_User $user
     * @param string   $operation   'insert' | 'update'
     */
    public function validate_write( array $payload, string $entity_set, \WP_User $user, string $operation ): void;
}
```

---

## How Field Permissions Work

1. Each property in `get_entity_type_definition()` can have an optional
   `'required_capability'` key (e.g. `'required_capability' => 'list_users'`).

2. `get_allowed_properties()` starts with all properties, then removes any that
   have a `required_capability` the user lacks.

3. The result is passed through the `wpos_allowed_properties` WP filter
   (applied by the subscriber, not here) so external plugins can further restrict
   or grant fields.

4. `apply()` removes any key from each row that is not in the allowed list.

---

## Read-Only Properties

Some properties should never be writable regardless of permission:
- `ID` (always read-only — key cannot be updated)
- `CommentCount` on Posts (computed by WP)
- `Count` on Terms (managed by WP internally)
- `RegisteredDate` on Users (set at creation, not updateable via API)

Mark these in the entity type definition as `'read_only' => true`.
`validate_write()` must reject any payload containing these fields.

---

## Exception

```php
class WPOS_Field_ACL_Exception extends \RuntimeException {
    public function __construct(
        string $message,
        public readonly string $entity_set,
        public readonly array  $forbidden_fields,
    ) {
        parent::__construct($message);
    }
}
```

---

## Acceptance Criteria

- `apply()` on a Users result removes `Login` and `Email` for a subscriber user (lacks `list_users`).
- `apply()` on a Users result keeps `Login` and `Email` for a user with `list_users`.
- `validate_write(['ID' => 5, 'Title' => 'Hello'])` throws `WPOS_Field_ACL_Exception` (ID is read-only).
- `apply()` never removes the key property from results.
- `get_allowed_properties()` returns a superset of just the key property.
- No WordPress hook calls in this file.
