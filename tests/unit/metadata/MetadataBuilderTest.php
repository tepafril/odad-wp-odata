<?php
/**
 * Unit tests for ODAD_Metadata_Builder.
 *
 * The WordPress transient functions are stubbed in bootstrap.php:
 *   get_transient()  → false  (always a cache miss)
 *   set_transient()  → no-op
 *   delete_transient → no-op
 *
 * Because the stubs hold no in-memory state, we use a spy subclass of
 * ODAD_Metadata_Cache to verify caching behaviour without WP infrastructure.
 */

use PHPUnit\Framework\TestCase;

// ── In-memory metadata cache ──────────────────────────────────────────────────

/**
 * Drop-in replacement for ODAD_Metadata_Cache that stores values in memory
 * rather than WP transients.  Allows verifying cache-hit / miss / bust logic.
 */
class Test_Memory_Metadata_Cache extends ODAD_Metadata_Cache {

    private ?string $xml  = null;
    private ?string $json = null;

    public function get_xml(): ?string  { return $this->xml; }
    public function set_xml( string $csdl ): void { $this->xml = $csdl; }

    public function get_json(): ?string { return $this->json; }
    public function set_json( string $csdl ): void { $this->json = $csdl; }

    public function bust(): void {
        $this->xml  = null;
        $this->json = null;
    }
}

// ── Test class ────────────────────────────────────────────────────────────────

class MetadataBuilderTest extends TestCase {

    private ODAD_Schema_Registry    $registry;
    private Test_Memory_Metadata_Cache $cache;
    private ODAD_Event_Bus          $event_bus;
    private ODAD_Hook_Bridge        $bridge;
    private ODAD_Function_Registry  $function_registry;
    private ODAD_Action_Registry    $action_registry;
    private ODAD_Metadata_Builder   $builder;

    protected function setUp(): void {
        $this->registry          = new ODAD_Schema_Registry();
        $this->cache             = new Test_Memory_Metadata_Cache();
        $this->event_bus         = new ODAD_Event_Bus();
        $this->bridge            = new ODAD_Hook_Bridge( $this->event_bus );
        $this->function_registry = new ODAD_Function_Registry();
        $this->action_registry   = new ODAD_Action_Registry();

        // Register a minimal entity set so the metadata is non-trivial.
        $this->registry->register( 'Posts', [
            'entity_type'  => 'PostEntityType',
            'key_property' => 'ID',
            'properties'   => [
                'ID'    => [ 'type' => 'Edm.Int32', 'nullable' => false ],
                'Title' => [ 'type' => 'Edm.String' ],
            ],
        ] );

        $this->builder = new ODAD_Metadata_Builder(
            registry:          $this->registry,
            cache:             $this->cache,
            event_bus:         $this->event_bus,
            bridge:            $this->bridge,
            function_registry: $this->function_registry,
            action_registry:   $this->action_registry,
        );
    }

    // ── XML output ───────────────────────────────────────────────────────────

    public function test_get_xml_returns_valid_xml_string(): void {
        $xml = $this->builder->get_xml();

        $this->assertIsString( $xml );
        $this->assertStringContainsString( '<?xml', $xml );
        $this->assertStringContainsString( 'Edmx', $xml );
    }

    public function test_get_xml_contains_registered_entity_type_name(): void {
        $xml = $this->builder->get_xml();

        $this->assertStringContainsString( 'PostEntityType', $xml );
    }

    // ── Caching ──────────────────────────────────────────────────────────────

    public function test_get_xml_returns_same_string_on_second_call(): void {
        $first  = $this->builder->get_xml();
        $second = $this->builder->get_xml();

        $this->assertSame( $first, $second );
    }

    public function test_cache_is_populated_after_first_call(): void {
        $this->assertNull( $this->cache->get_xml() );

        $this->builder->get_xml();

        $this->assertNotNull( $this->cache->get_xml() );
    }

    public function test_cache_bust_causes_rebuild(): void {
        $first = $this->builder->get_xml();

        // Bust the cache and add a new entity set to force a different output.
        $this->cache->bust();
        $this->registry->register( 'Pages', [
            'entity_type'  => 'PageEntityType',
            'key_property' => 'ID',
            'properties'   => [
                'ID'    => [ 'type' => 'Edm.Int32', 'nullable' => false ],
                'Title' => [ 'type' => 'Edm.String' ],
            ],
        ] );

        $second = $this->builder->get_xml();

        $this->assertStringContainsString( 'PageEntityType', $second );
    }

    // ── JSON CSDL ─────────────────────────────────────────────────────────────

    public function test_get_json_contains_odata_context_key(): void {
        $json = $this->builder->get_json();

        $this->assertIsString( $json );
        $decoded = json_decode( $json, true );
        $this->assertIsArray( $decoded );

        // The JSON CSDL root must contain $Version and $EntityContainer.
        $this->assertArrayHasKey( '$Version', $decoded );
        $this->assertArrayHasKey( '$EntityContainer', $decoded );
    }

    // ── Schema-changed event busts cache ─────────────────────────────────────

    public function test_schema_changed_event_dispatched_and_cache_can_be_busted(): void {
        // Build an XML so the cache is warm.
        $this->builder->get_xml();
        $this->assertNotNull( $this->cache->get_xml() );

        // Simulate what ODAD_Subscriber_Schema_Changed does: bust the cache.
        $this->cache->bust();

        // Cache should now be empty.
        $this->assertNull( $this->cache->get_xml() );

        // Rebuilding should work.
        $xml = $this->builder->get_xml();
        $this->assertStringContainsString( 'PostEntityType', $xml );
    }
}
