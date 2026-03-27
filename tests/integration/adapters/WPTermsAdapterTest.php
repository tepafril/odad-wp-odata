<?php
/**
 * Integration tests for ODAD_Adapter_WP_Terms.
 *
 * Requires a live WordPress test database (WP_UnitTestCase).
 * Each test runs inside a transaction that is rolled back on tearDown.
 *
 * @package ODAD\Tests\Integration
 */

class WPTermsAdapterTest extends WP_UnitTestCase {

    private ODAD_Adapter_WP_Terms $adapter;

    public function setUp(): void {
        parent::setUp();
        $this->adapter = new ODAD_Adapter_WP_Terms( 'category', 'Categories' );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function make_ctx(): ODAD_Query_Context {
        $ctx      = new ODAD_Query_Context();
        $ctx->top = 100;
        return $ctx;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * insert() returns an integer key; get_entity() returns the entity with the
     * correct Name.
     */
    public function test_insert_and_get(): void {
        $rand = mt_rand( 10000, 99999 );
        $key  = $this->adapter->insert( [
            'Name'     => 'Test Cat ' . $rand,
            'Slug'     => 'test-cat-' . $rand,
            'Taxonomy' => 'category',
        ] );

        $this->assertIsInt( $key, 'insert() should return an integer term ID.' );
        $this->assertGreaterThan( 0, $key );

        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );
        $this->assertIsArray( $entity );
        $this->assertSame( 'Test Cat ' . $rand, $entity['Name'] );
    }

    /**
     * update() changes the Name; get_entity() returns the new value.
     */
    public function test_update(): void {
        $rand = mt_rand( 10000, 99999 );
        $key  = $this->adapter->insert( [
            'Name' => 'Original Cat ' . $rand,
            'Slug' => 'orig-cat-' . $rand,
        ] );

        $result = $this->adapter->update( $key, [ 'Name' => 'Renamed Cat ' . $rand ] );
        $this->assertTrue( $result );

        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );
        $this->assertSame( 'Renamed Cat ' . $rand, $entity['Name'] );
    }

    /**
     * delete() removes the term; get_entity() returns null afterwards.
     */
    public function test_delete(): void {
        $rand = mt_rand( 10000, 99999 );
        $key  = $this->adapter->insert( [
            'Name' => 'Delete Cat ' . $rand,
            'Slug' => 'del-cat-' . $rand,
        ] );
        $this->assertIsInt( $key );

        $deleted = $this->adapter->delete( $key );
        $this->assertTrue( $deleted );

        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );
        $this->assertNull( $entity, 'get_entity() should return null after delete.' );
    }

    /**
     * get_collection() for 'category' adapter does not include post_tag terms.
     *
     * A 'category' term is inserted via the adapter and a 'post_tag' term is
     * inserted directly via wp_insert_term(). The adapter's get_collection()
     * must only return terms whose taxonomy is 'category'.
     */
    public function test_get_collection_for_correct_taxonomy(): void {
        $rand = mt_rand( 10000, 99999 );

        // Insert a category through our adapter.
        $cat_key = $this->adapter->insert( [
            'Name' => 'Filter Cat ' . $rand,
            'Slug' => 'filter-cat-' . $rand,
        ] );
        $this->assertIsInt( $cat_key );

        // Insert a post_tag directly via WP — must NOT appear in the collection.
        $tag_result = wp_insert_term( 'Test Tag ' . $rand, 'post_tag' );
        $this->assertIsArray( $tag_result, 'wp_insert_term() should return an array.' );
        $tag_id = (int) $tag_result['term_id'];

        $collection = $this->adapter->get_collection( $this->make_ctx() );
        $this->assertIsArray( $collection );

        // Collect all IDs returned.
        $returned_ids = array_column( $collection, 'ID' );

        // The category we inserted must be present.
        $this->assertContains(
            $cat_key,
            $returned_ids,
            'The inserted category term should appear in get_collection().'
        );

        // The post_tag must NOT appear.
        $this->assertNotContains(
            $tag_id,
            $returned_ids,
            'A post_tag term must not appear in a category adapter\'s get_collection().'
        );
    }
}
