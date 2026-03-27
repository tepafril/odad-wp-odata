# Task 1.5 — Schema Registry + Metadata Cache + Minimal $metadata CSDL XML

## Dependencies
- Task 1.1 (DI container)
- Task 1.2 (event bus — `ODAD_Event_Metadata_Build`, `ODAD_Event_Schema_Changed`)
- Task 1.3 (hook bridge — `ODAD_metadata_entity_types`, `ODAD_metadata_entity_sets` filters)
- Task 1.4 (HTTP layer — `ODAD_Response::metadata_xml()`)

## Goal
Build the schema registry that tracks registered entity sets, and the metadata cache
that stores the compiled CSDL. Implement the `ODAD_Metadata_Builder` that produces
valid OData v4.01 CSDL XML.

---

## Files to Create

### `src/metadata/class-wpos-schema-registry.php`

Holds the canonical list of entity sets known to the plugin.

```php
class ODAD_Schema_Registry {

    /** @var array<string, array> entity_set_name → definition */
    private array $entity_sets = [];

    public function register( string $entity_set, array $definition ): void;

    public function has( string $entity_set ): bool;

    public function get( string $entity_set ): array;

    /** @return array<string, array> */
    public function all(): array;

    public function remove( string $entity_set ): void;
}
```

A `$definition` array has this shape (adapters populate it in Phase 2):
```php
[
    'entity_type'    => 'PostEntityType',   // CSDL EntityType name
    'key_property'   => 'ID',
    'properties'     => [
        'ID'          => [ 'type' => 'Edm.Int32',  'nullable' => false ],
        'Title'       => [ 'type' => 'Edm.String', 'nullable' => true  ],
        // ...
    ],
    'nav_properties' => [
        'Author'      => [ 'type' => 'Users',      'collection' => false ],
        'Tags'        => [ 'type' => 'Tags',        'collection' => true  ],
    ],
    'adapter_class'  => ODAD_Adapter_WP_Posts::class,
]
```

---

### `src/metadata/class-wpos-metadata-cache.php`

WP Transients-based cache. TTL = `DAY_IN_SECONDS`.

```php
class ODAD_Metadata_Cache {

    private const TRANSIENT_XML  = 'ODAD_metadata_xml';
    private const TRANSIENT_JSON = 'ODAD_metadata_json';
    private const TTL            = DAY_IN_SECONDS;

    public function get_xml(): ?string;
    public function set_xml( string $csdl ): void;

    public function get_json(): ?string;
    public function set_json( string $csdl ): void;

    public function bust(): void;   // deletes both transients
}
```

---

### `src/metadata/class-wpos-metadata-builder.php`

Builds CSDL XML (and later JSON) from the schema registry.
Must pass `ODAD_metadata_entity_types` and `ODAD_metadata_entity_sets` filters
**through the Hook Bridge** before generating output, so external plugins can inject
or modify entity type definitions.

```php
class ODAD_Metadata_Builder {

    public function __construct(
        private ODAD_Schema_Registry $registry,
        private ODAD_Metadata_Cache  $cache,
        private ODAD_Event_Bus       $event_bus,
        private ODAD_Hook_Bridge     $bridge,
    ) {}

    public function get_xml(): string;
    public function get_json(): string;

    private function build_xml(): string;
    private function build_json(): string;
}
```

**`get_xml()` algorithm:**
1. Check cache (`$cache->get_xml()`). Return cached value if present.
2. Dispatch `ODAD_Event_Metadata_Build` with entity types and sets from registry.
3. Apply `ODAD_metadata_entity_types` filter (via `$bridge->filter()`).
4. Apply `ODAD_metadata_entity_sets` filter (via `$bridge->filter()`).
5. Build the CSDL XML string.
6. Store in cache.
7. Return.

**Minimal valid CSDL XML output** (Phase 1 — no entity types yet, just the envelope):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<edmx:Edmx Version="4.01" xmlns:edmx="http://docs.oasis-open.org/odata/ns/edmx">
  <edmx:DataServices>
    <Schema Namespace="WPOData" xmlns="http://docs.oasis-open.org/odata/ns/edm">
      <EntityContainer Name="WPODataService">
        <!-- EntitySets populated by adapters in Phase 2 -->
      </EntityContainer>
    </Schema>
  </edmx:DataServices>
</edmx:Edmx>
```

Once entity types are registered (Phase 2), `build_xml()` must emit:
```xml
<EntityType Name="PostEntityType">
  <Key><PropertyRef Name="ID"/></Key>
  <Property Name="ID" Type="Edm.Int32" Nullable="false"/>
  <Property Name="Title" Type="Edm.String"/>
  <NavigationProperty Name="Author" Type="WPOData.UserEntityType"/>
  <NavigationProperty Name="Tags" Type="Collection(WPOData.TermEntityType)"/>
</EntityType>
...
<EntityContainer Name="WPODataService">
  <EntitySet Name="Posts" EntityType="WPOData.PostEntityType"/>
  ...
</EntityContainer>
```

---

### `src/hooks/subscribers/class-wpos-subscriber-schema-changed.php`

Implements the cache invalidation subscriber (flesh out the stub from Task 1.3):

```php
class ODAD_Subscriber_Schema_Changed implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Metadata_Cache $cache,
    ) {}

    public function get_event(): string {
        return ODAD_Event_Schema_Changed::class;
    }

    public function handle( ODAD_Event $event ): void {
        $this->cache->bust();
    }
}
```

---

## Bootstrapper Update

Update `class-wpos-bootstrapper.php` to wire:
- `ODAD_Schema_Registry` singleton
- `ODAD_Metadata_Cache` singleton
- `ODAD_Metadata_Builder` singleton (injecting the above + event bus + bridge)
- Update `ODAD_Subscriber_Schema_Changed` constructor to receive `ODAD_Metadata_Cache`

---

## Acceptance Criteria

- `GET /wp-json/odata/v4/$metadata` returns valid CSDL XML with correct namespace and `OData-Version: 4.01` header.
- CSDL XML is cached in a WP transient after first request.
- Second request returns cached value (no rebuild).
- Dispatching `ODAD_Event_Schema_Changed` busts both `ODAD_metadata_xml` and `ODAD_metadata_json` transients.
- `ODAD_metadata_entity_types` and `ODAD_metadata_entity_sets` WP filters are applied before building output.
- External plugin adding a filter to `ODAD_metadata_entity_types` can inject additional entity type definitions into the output.
