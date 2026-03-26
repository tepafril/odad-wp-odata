# Task 2.1 — Adapter Interface + Adapter Resolver

## Dependencies
- All Phase 1 tasks complete.

## Goal
Define the canonical adapter interface and the adapter resolver that maps entity set
names to adapter instances. These are the contracts everything in Phase 2 is built against.

---

## Files to Create

### `src/adapters/interface-wpos-adapter.php`

```php
interface WPOS_Adapter {

    // ── Reads ─────────────────────────────────────────────────────────────
    /** Return an array of entity rows matching the query context. */
    public function get_collection( WPOS_Query_Context $ctx ): array;

    /** Return a single entity row by key, or null if not found. */
    public function get_entity( mixed $key, WPOS_Query_Context $ctx ): ?array;

    /** Return the total count of rows matching $ctx (ignoring $top/$skip). */
    public function get_count( WPOS_Query_Context $ctx ): int;

    // ── Writes ────────────────────────────────────────────────────────────
    /** Insert a new entity. Returns the new key value. */
    public function insert( array $data ): mixed;

    /** Update an existing entity. Returns true on success. */
    public function update( mixed $key, array $data ): bool;

    /** Delete an entity. Returns true on success. */
    public function delete( mixed $key ): bool;

    // ── Schema ────────────────────────────────────────────────────────────
    /**
     * Return the entity type definition array for the schema registry.
     * Shape:
     * [
     *     'entity_type'    => 'PostEntityType',
     *     'key_property'   => 'ID',
     *     'properties'     => [ 'ID' => ['type'=>'Edm.Int32', 'nullable'=>false], ... ],
     *     'nav_properties' => [ 'Author' => ['type'=>'Users', 'collection'=>false], ... ],
     *     'adapter_class'  => static::class,
     * ]
     */
    public function get_entity_type_definition(): array;

    /** Return the OData entity set name (e.g. 'Posts', 'Users'). */
    public function get_entity_set_name(): string;
}
```

---

### `src/adapters/class-wpos-adapter-resolver.php`

Maps entity set names → adapter instances. Injected into all domain services.

```php
class WPOS_Adapter_Resolver {

    /** @var array<string, WPOS_Adapter> */
    private array $adapters = [];

    public function register( string $entity_set, WPOS_Adapter $adapter ): void {
        $this->adapters[ $entity_set ] = $adapter;
    }

    public function resolve( string $entity_set ): WPOS_Adapter {
        if ( ! isset( $this->adapters[ $entity_set ] ) ) {
            throw new WPOS_Unknown_Entity_Exception(
                "No adapter registered for entity set: {$entity_set}"
            );
        }
        return $this->adapters[ $entity_set ];
    }

    public function has( string $entity_set ): bool {
        return isset( $this->adapters[ $entity_set ] );
    }

    /** @return string[] */
    public function registered_entity_sets(): array {
        return array_keys( $this->adapters );
    }
}
```

---

### Exception class

Create `src/adapters/class-wpos-unknown-entity-exception.php`:
```php
class WPOS_Unknown_Entity_Exception extends \RuntimeException {}
```

---

## `WPOS_Query_Context` Stub

`WPOS_Query_Context` is fully built in Phase 3 (Task 3.5), but the adapter interface
references it. Create a minimal stub in `src/query/class-wpos-query-context.php` now:

```php
class WPOS_Query_Context {
    public ?string $filter  = null;
    public ?array  $select  = null;
    public ?array  $orderby = null;
    public int     $top     = 100;
    public int     $skip    = 0;
    public bool    $count   = false;
    public ?string $expand  = null;
    public ?string $search  = null;
    public ?array  $compute = null;
    // Additional fields added in Phase 3 without breaking this interface
}
```

---

## Bootstrapper Update

In `class-wpos-bootstrapper.php`, add:
```php
$c->singleton( WPOS_Adapter_Resolver::class, fn() => new WPOS_Adapter_Resolver() );
```

---

## Acceptance Criteria

- `WPOS_Adapter` interface is well-typed and matches the signatures above exactly.
- `WPOS_Adapter_Resolver::resolve()` throws `WPOS_Unknown_Entity_Exception` (not a generic RuntimeException) for unknown entity sets.
- `WPOS_Adapter_Resolver::registered_entity_sets()` returns only entity set names that have been registered.
- `WPOS_Query_Context` stub compiles without errors.
- No WordPress functions called in these files.
