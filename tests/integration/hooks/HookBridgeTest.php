<?php
/**
 * Integration tests for ODAD_Hook_Bridge and related WP filter/action extension points.
 *
 * These tests rely on the full bootstrapped plugin container which is set up by
 * plugins_loaded (triggered automatically by the WP test suite bootstrap).
 *
 * @package ODAD\Tests\Integration
 */

class HookBridgeTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Container is already available via ODAD_container() — plugins_loaded
        // was fired during test-suite bootstrap.
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * The 'ODAD_register_entity_sets' action hook is registered during init.
     *
     * We verify it was registered (has_action returns a truthy value) rather
     * than firing init again (which would double-register everything).
     */
    public function test_ODAD_register_entity_sets_fires_on_init(): void {
        // The bridge registers 'ODAD_register_entity_sets' on init (priority 1)
        // at plugins_loaded time. By the time setUp() runs, init has already
        // fired, so we check that WordPress knows the hook was registered.
        $this->assertTrue(
            has_action( 'ODAD_register_entity_sets' ) !== false,
            "'ODAD_register_entity_sets' action must be registered."
        );
    }

    /**
     * Adding a filter on 'ODAD_can_read' that returns false causes
     * ODAD_Subscriber_Permission_Check to deny access.
     *
     * We fire the event directly through the event bus so we don't need a
     * full HTTP request.
     */
    public function test_ODAD_can_read_filter(): void {
        // Register a filter that denies all reads.
        add_filter( 'ODAD_can_read', '__return_false', 99, 3 );

        try {
            $bus = ODAD_container()->get( ODAD_Event_Bus::class );

            // Create a user with admin capability (so domain check says yes)
            // but our filter overrides to false.
            $admin_id   = $this->factory->user->create( [ 'role' => 'administrator' ] );
            $admin_user = get_userdata( $admin_id );

            $event = new ODAD_Event_Permission_Check(
                entity_set: 'Posts',
                operation:  'read',
                user:       $admin_user,
                granted:    true, // pre-set to true; filter should override.
            );

            $bus->dispatch( $event );

            $this->assertFalse(
                $event->granted,
                'ODAD_can_read returning false must deny permission.'
            );
        } finally {
            remove_filter( 'ODAD_can_read', '__return_false', 99 );
        }
    }

    /**
     * A 'ODAD_before_insert' filter that modifies the payload is applied before
     * the write is persisted.
     *
     * We dispatch ODAD_Event_Write_Before through the event bus (as the write
     * handler does) and assert that our filter's mutation appears on the event.
     */
    public function test_ODAD_before_insert_filter_modifies_payload(): void {
        $filter_fn = static function ( array $payload ): array {
            $payload['Status'] = 'draft';
            return $payload;
        };

        add_filter( 'ODAD_before_insert', $filter_fn, 10, 3 );

        try {
            $bus      = ODAD_container()->get( ODAD_Event_Bus::class );
            $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
            $admin    = get_userdata( $admin_id );

            $event = new ODAD_Event_Write_Before(
                entity_set: 'Posts',
                operation:  'insert',
                user:       $admin,
                payload:    [ 'Title' => 'Hook Test Post', 'Status' => 'publish' ],
            );

            $bus->dispatch( $event );

            $this->assertSame(
                'draft',
                $event->payload['Status'],
                'ODAD_before_insert filter must be able to change payload Status to "draft".'
            );
        } finally {
            remove_filter( 'ODAD_before_insert', $filter_fn, 10 );
        }
    }

    /**
     * Calling ODAD_Metadata_Cache::bust() deletes both transients so that the
     * next call to get_xml() / get_json() returns null (cache miss).
     */
    public function test_schema_changed_busts_cache(): void {
        $cache = ODAD_container()->get( ODAD_Metadata_Cache::class );

        // Prime the cache with dummy data.
        $cache->set_xml( '<edmx:Edmx />' );
        $cache->set_json( '{}' );

        $this->assertNotNull( $cache->get_xml(),  'Cache should be warm after set_xml().' );
        $this->assertNotNull( $cache->get_json(), 'Cache should be warm after set_json().' );

        // Bust via the public API.
        $cache->bust();

        $this->assertNull( $cache->get_xml(),  'get_xml() must return null after bust().' );
        $this->assertNull( $cache->get_json(), 'get_json() must return null after bust().' );
    }
}
