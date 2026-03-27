<?php
/**
 * Integration tests for ODAD_Adapter_WP_Posts.
 *
 * Requires a live WordPress test database (WP_UnitTestCase).
 * Each test runs inside a transaction that is rolled back on tearDown.
 *
 * @package WPOS\Tests\Integration
 */

class WPPostsAdapterTest extends WP_UnitTestCase {

    private ODAD_Adapter_WP_Posts $adapter;

    public function setUp(): void {
        parent::setUp();
        $this->adapter = new ODAD_Adapter_WP_Posts( 'post', 'Posts' );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function make_ctx(): ODAD_Query_Context {
        $ctx      = new ODAD_Query_Context();
        $ctx->top = 100;
        return $ctx;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * insert() returns an integer key; get_entity() returns the matching entity.
     */
    public function test_insert_and_get(): void {
        $key = $this->adapter->insert( [
            'Title'  => 'Test Post',
            'Status' => 'publish',
            'Type'   => 'post',
        ] );

        $this->assertIsInt( $key, 'insert() should return an integer post ID.' );
        $this->assertGreaterThan( 0, $key );

        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );

        $this->assertIsArray( $entity );
        $this->assertSame( 'Test Post', $entity['Title'] );
    }

    /**
     * update() changes a property that is then readable via get_entity().
     */
    public function test_update(): void {
        $key = $this->adapter->insert( [
            'Title'  => 'Original Title',
            'Status' => 'publish',
        ] );

        $result = $this->adapter->update( $key, [ 'Title' => 'Updated Title' ] );
        $this->assertTrue( $result );

        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );
        $this->assertSame( 'Updated Title', $entity['Title'] );
    }

    /**
     * delete() removes the entity; subsequent get_entity() returns null.
     */
    public function test_delete(): void {
        $key = $this->adapter->insert( [
            'Title'  => 'To Be Deleted',
            'Status' => 'publish',
        ] );

        $deleted = $this->adapter->delete( $key );
        $this->assertTrue( $deleted );

        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );
        $this->assertNull( $entity, 'get_entity() should return null after delete.' );
    }

    /**
     * get_collection() returns at least the entities that were just inserted.
     */
    public function test_get_collection(): void {
        $this->adapter->insert( [ 'Title' => 'Collection Post 1', 'Status' => 'publish' ] );
        $this->adapter->insert( [ 'Title' => 'Collection Post 2', 'Status' => 'publish' ] );

        $collection = $this->adapter->get_collection( $this->make_ctx() );

        $this->assertIsArray( $collection );
        $this->assertGreaterThanOrEqual( 2, count( $collection ) );
    }

    /**
     * get_count() reflects newly inserted posts.
     */
    public function test_get_count(): void {
        $before = $this->adapter->get_count( $this->make_ctx() );

        $this->adapter->insert( [ 'Title' => 'Count Post A', 'Status' => 'publish' ] );
        $this->adapter->insert( [ 'Title' => 'Count Post B', 'Status' => 'publish' ] );

        $after = $this->adapter->get_count( $this->make_ctx() );

        $this->assertGreaterThanOrEqual( $before + 2, $after );
    }

    /**
     * No result row may expose the raw user_pass column.
     */
    public function test_no_user_pass_in_results(): void {
        $this->adapter->insert( [ 'Title' => 'Privacy Post', 'Status' => 'publish' ] );

        $collection = $this->adapter->get_collection( $this->make_ctx() );

        foreach ( $collection as $row ) {
            $this->assertArrayNotHasKey(
                'user_pass',
                $row,
                'Posts collection must never expose user_pass.'
            );
        }
    }

    /**
     * Results use OData property names (Title, Status) not WP column names
     * (post_title, post_status).
     */
    public function test_odata_property_names(): void {
        $key    = $this->adapter->insert( [
            'Title'  => 'OData Named Post',
            'Status' => 'publish',
        ] );
        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );

        $this->assertArrayHasKey( 'Title',  $entity, 'Property should be "Title", not "post_title".' );
        $this->assertArrayHasKey( 'Status', $entity, 'Property should be "Status", not "post_status".' );

        $this->assertArrayNotHasKey( 'post_title',  $entity );
        $this->assertArrayNotHasKey( 'post_status', $entity );
    }
}
