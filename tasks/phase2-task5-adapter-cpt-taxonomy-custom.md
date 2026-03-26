# Task 2.5 — CPT Adapter + Taxonomy Adapter + Custom Table Adapter

## Dependencies
- Task 2.1 (adapter interface + resolver)
- Task 2.2 (WP_Posts adapter — CPT adapter extends it)
- Task 2.4 (WP_Terms adapter — Taxonomy adapter extends it)

## Goal
Build three adapters that auto-discover WordPress registrations or handle arbitrary
custom database tables.

---

## File 1: `src/adapters/class-wpos-adapter-cpt.php`

Handles any Custom Post Type. Extends or wraps `WPOS_Adapter_WP_Posts`.

```php
class WPOS_Adapter_CPT extends WPOS_Adapter_WP_Posts {

    /**
     * Auto-discovers all registered CPTs and returns an array of
     * WPOS_Adapter_CPT instances — one per CPT.
     *
     * @return WPOS_Adapter_CPT[]  keyed by entity set name
     */
    public static function discover_all(): array {
        $post_types = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
        $adapters   = [];

        foreach ( $post_types as $post_type => $obj ) {
            // Entity set name = PascalCase of post_type label, e.g. 'book' → 'Books'
            $entity_set = self::to_entity_set_name( $obj->labels->name ?? $post_type );
            $adapters[ $entity_set ] = new self( $post_type, $entity_set );
        }

        return $adapters;
    }

    private static function to_entity_set_name( string $label ): string {
        // Convert label to PascalCase plural, e.g. 'Book' → 'Books', 'Book Review' → 'BookReviews'
        $words = preg_split( '/[\s_-]+/', $label );
        return implode( '', array_map( 'ucfirst', $words ) );
    }
}
```

The CPT adapter inherits all `WPOS_Adapter_WP_Posts` methods.
`get_entity_type_definition()` should include any custom `register_post_type` labels.

---

## File 2: `src/adapters/class-wpos-adapter-taxonomy.php`

Handles any registered taxonomy. Extends `WPOS_Adapter_WP_Terms`.

```php
class WPOS_Adapter_Taxonomy extends WPOS_Adapter_WP_Terms {

    /**
     * Auto-discovers all registered custom taxonomies and returns
     * an array of WPOS_Adapter_Taxonomy instances.
     *
     * @return WPOS_Adapter_Taxonomy[]  keyed by entity set name
     */
    public static function discover_all(): array {
        $taxonomies = get_taxonomies( [ 'public' => true, '_builtin' => false ], 'objects' );
        $adapters   = [];

        foreach ( $taxonomies as $taxonomy => $obj ) {
            $entity_set = self::to_entity_set_name( $obj->labels->name ?? $taxonomy );
            $adapters[ $entity_set ] = new self( $taxonomy, $entity_set );
        }

        return $adapters;
    }

    private static function to_entity_set_name( string $label ): string {
        $words = preg_split( '/[\s_-]+/', $label );
        return implode( '', array_map( 'ucfirst', $words ) );
    }
}
```

---

## File 3: `src/adapters/class-wpos-adapter-custom-table.php`

Generic adapter for any arbitrary `$wpdb` prefixed table (e.g. `wp_employees`).

```php
class WPOS_Adapter_Custom_Table implements WPOS_Adapter {

    /**
     * @param string  $table_name      Without $wpdb prefix (e.g. 'employees')
     * @param string  $entity_set_name OData entity set name (e.g. 'Employees')
     * @param string  $key_column      Primary key column name (e.g. 'id')
     * @param ?array  $schema          Optional manual schema. If null, auto-detected via DESCRIBE.
     */
    public function __construct(
        private string  $table_name,
        private string  $entity_set_name,
        private string  $key_column  = 'id',
        private ?array  $schema      = null,
    ) {}

    /**
     * Auto-detect schema from DESCRIBE table.
     * Maps MySQL types to Edm types:
     *   int/bigint/tinyint → Edm.Int32 / Edm.Int64
     *   varchar/text       → Edm.String
     *   datetime/timestamp → Edm.DateTimeOffset
     *   decimal/float      → Edm.Decimal / Edm.Double
     *   tinyint(1)         → Edm.Boolean
     */
    private function detect_schema(): array;

    public function get_collection( WPOS_Query_Context $ctx ): array;
    public function get_entity( mixed $key, WPOS_Query_Context $ctx ): ?array;
    public function get_count( WPOS_Query_Context $ctx ): int;
    public function insert( array $data ): mixed;   // uses $wpdb->insert()
    public function update( mixed $key, array $data ): bool;  // uses $wpdb->update()
    public function delete( mixed $key ): bool;     // uses $wpdb->delete()
    public function get_entity_type_definition(): array;
    public function get_entity_set_name(): string;
}
```

**Custom table writes use raw `$wpdb` (not WP API functions) — this is intentional
per the master plan: `wp_insert_post()` for WP-native entities, `$wpdb` for custom tables.**

All `$wpdb` operations must use parameterized queries (`$wpdb->prepare()`,
`$wpdb->insert()`, `$wpdb->update()`, `$wpdb->delete()`).

---

## Acceptance Criteria

- `WPOS_Adapter_CPT::discover_all()` returns one adapter per public non-builtin post type.
- `WPOS_Adapter_Taxonomy::discover_all()` returns one adapter per public non-builtin taxonomy.
- `WPOS_Adapter_Custom_Table` with `$schema = null` runs `DESCRIBE {table}` and returns a valid property map.
- `WPOS_Adapter_Custom_Table::insert()` uses `$wpdb->insert()`, not `$wpdb->query()` with interpolated SQL.
- No string interpolation into SQL in custom table adapter — all values parameterized.
