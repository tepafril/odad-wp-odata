<?php
/**
 * Integration tests for ODAD_Adapter_Custom_Table.
 *
 * Creates a temporary table in setUp and drops it in tearDown.
 * Tests run inside WP's transaction wrapper so row data is rolled back,
 * but DDL (CREATE/DROP TABLE) commits immediately in MySQL.
 *
 * @package WPOS\Tests\Integration
 */

class CustomTableAdapterTest extends WP_UnitTestCase {

    /**
     * Bare table name (without prefix) used across tests.
     */
    private const TABLE = 'ODAD_test_custom';

    /** @var ODAD_Adapter_Custom_Table */
    private ODAD_Adapter_Custom_Table $adapter;

    public function setUp(): void {
        parent::setUp();

        global $wpdb;

        // Create the temporary test table (DDL auto-commits in MySQL).
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}" . self::TABLE . "` (
                id     BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
                title  VARCHAR(255) NULL,
                status VARCHAR(50)  NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Adapter uses the bare name; the class prepends $wpdb->prefix internally.
        $this->adapter = new ODAD_Adapter_Custom_Table(
            self::TABLE,
            'WposTestCustom',
            'id',
        );
    }

    public function tearDown(): void {
        global $wpdb;

        $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}" . self::TABLE . '`' );

        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function make_ctx(): ODAD_Query_Context {
        $ctx      = new ODAD_Query_Context();
        $ctx->top = 100;
        return $ctx;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * get_entity_type_definition() detects all three columns from the schema.
     */
    public function test_schema_detection(): void {
        $definition = $this->adapter->get_entity_type_definition();

        $this->assertArrayHasKey( 'properties', $definition );
        $properties = $definition['properties'];

        $this->assertArrayHasKey( 'id',     $properties, 'Schema must include "id" column.' );
        $this->assertArrayHasKey( 'title',  $properties, 'Schema must include "title" column.' );
        $this->assertArrayHasKey( 'status', $properties, 'Schema must include "status" column.' );
    }

    /**
     * Full CRUD cycle: insert, get, update, delete.
     */
    public function test_crud(): void {
        // INSERT
        $key = $this->adapter->insert( [
            'title'  => 'Test Row',
            'status' => 'active',
        ] );

        $this->assertIsInt( (int) $key, 'insert() must return a numeric key.' );
        $this->assertGreaterThan( 0, (int) $key );

        // READ
        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );
        $this->assertIsArray( $entity );
        $this->assertSame( 'Test Row', $entity['title'] );
        $this->assertSame( 'active',   $entity['status'] );

        // UPDATE
        $updated = $this->adapter->update( $key, [ 'title' => 'Updated Row', 'status' => 'inactive' ] );
        $this->assertTrue( $updated );

        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );
        $this->assertSame( 'Updated Row', $entity['title'] );
        $this->assertSame( 'inactive',    $entity['status'] );

        // DELETE
        $deleted = $this->adapter->delete( $key );
        $this->assertTrue( $deleted );

        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );
        $this->assertNull( $entity, 'get_entity() must return null after delete.' );
    }
}
