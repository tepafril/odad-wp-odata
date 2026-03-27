<?php
/**
 * Integration tests for ODAD_Admin_Entity_Config.
 *
 * Requires the full bootstrapped container (available after plugins_loaded).
 *
 * @package ODAD\Tests\Integration
 */

class AdminEntityConfigTest extends WP_UnitTestCase {

    private ODAD_Admin_Entity_Config $config;

    public function setUp(): void {
        parent::setUp();
        $this->config = ODAD_container()->get( ODAD_Admin_Entity_Config::class );
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * get_config() returns an array that contains all expected default keys.
     */
    public function test_get_config_returns_defaults(): void {
        $config = $this->config->get_config( 'Posts' );

        $this->assertIsArray( $config );

        $expected_keys = [
            'enabled',
            'label',
            'exposed_properties',
            'allow_insert',
            'allow_update',
            'allow_delete',
            'max_top',
            'require_auth',
        ];

        foreach ( $expected_keys as $key ) {
            $this->assertArrayHasKey(
                $key,
                $config,
                "get_config('Posts') must contain the key '{$key}'."
            );
        }
    }

    /**
     * Dispatching ODAD_Event_Admin_Entity_Config_Saved on the event bus causes
     * the ODAD_Subscriber_Admin_Config_Saved to fire the
     * 'ODAD_admin_entity_config_saved' WP action.
     */
    public function test_save_dispatches_event(): void {
        $bus = ODAD_container()->get( ODAD_Event_Bus::class );

        $fired         = false;
        $captured_set  = null;

        $listener = static function ( string $entity_set ) use ( &$fired, &$captured_set ): void {
            $fired        = true;
            $captured_set = $entity_set;
        };

        add_action( 'ODAD_admin_entity_config_saved', $listener, 10, 2 );

        try {
            $event = new ODAD_Event_Admin_Entity_Config_Saved(
                entity_set: 'Posts',
                config:     [ 'enabled' => true, 'allow_insert' => false ],
            );

            $bus->dispatch( $event );

            $this->assertTrue(
                $fired,
                'ODAD_admin_entity_config_saved WP action must fire after event dispatch.'
            );
            $this->assertSame( 'Posts', $captured_set );
        } finally {
            remove_action( 'ODAD_admin_entity_config_saved', $listener, 10 );
        }
    }

    /**
     * Dispatching ODAD_Event_Admin_Entity_Config_Saved causes the metadata cache
     * to be busted (both transients deleted).
     */
    public function test_save_busts_metadata_cache(): void {
        $bus   = ODAD_container()->get( ODAD_Event_Bus::class );
        $cache = ODAD_container()->get( ODAD_Metadata_Cache::class );

        // Prime the cache.
        $cache->set_xml( '<edmx:Edmx />' );
        $cache->set_json( '{}' );

        $this->assertNotNull( $cache->get_xml(),  'Cache must be warm before dispatch.' );
        $this->assertNotNull( $cache->get_json(), 'Cache must be warm before dispatch.' );

        // Dispatch the saved event; the subscriber chain should call cache->bust().
        $bus->dispatch( new ODAD_Event_Admin_Entity_Config_Saved(
            entity_set: 'Posts',
            config:     [],
        ) );

        $this->assertNull(
            $cache->get_xml(),
            'Metadata XML cache must be null (busted) after config-saved event.'
        );
        $this->assertNull(
            $cache->get_json(),
            'Metadata JSON cache must be null (busted) after config-saved event.'
        );
    }
}
