<?php
/**
 * Integration tests for WPOS_Adapter_WP_Users.
 *
 * Requires a live WordPress test database (WP_UnitTestCase).
 * Each test runs inside a transaction that is rolled back on tearDown.
 *
 * @package WPOS\Tests\Integration
 */

class WPUsersAdapterTest extends WP_UnitTestCase {

    private WPOS_Adapter_WP_Users $adapter;

    public function setUp(): void {
        parent::setUp();
        $this->adapter = new WPOS_Adapter_WP_Users();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function make_ctx(): WPOS_Query_Context {
        $ctx      = new WPOS_Query_Context();
        $ctx->top = 100;
        return $ctx;
    }

    /**
     * Build a unique user payload so tests never collide on login/email.
     */
    private function unique_user_payload(): array {
        $rand = mt_rand( 10000, 99999 );
        return [
            'DisplayName' => 'Test User ' . $rand,
            'Login'       => 'wpos_test_user_' . $rand,
            'Email'       => 'wpos_' . $rand . '@test.com',
            'Password'    => 'secret123',
        ];
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * insert() returns an integer user ID.
     */
    public function test_insert_and_get(): void {
        $payload = $this->unique_user_payload();
        $key     = $this->adapter->insert( $payload );

        $this->assertIsInt( $key, 'insert() should return an integer user ID.' );
        $this->assertGreaterThan( 0, $key );
    }

    /**
     * get_entity() must never expose user_pass or the plain-text Password key.
     */
    public function test_user_pass_never_in_results(): void {
        $key = $this->adapter->insert( $this->unique_user_payload() );
        $this->assertIsInt( $key );

        $entity = $this->adapter->get_entity( $key, $this->make_ctx() );
        $this->assertIsArray( $entity );

        $this->assertArrayNotHasKey(
            'user_pass',
            $entity,
            'get_entity() must never return the raw user_pass hash.'
        );
        $this->assertArrayNotHasKey(
            'Password',
            $entity,
            'get_entity() must never return the plain-text Password input field.'
        );
    }

    /**
     * get_collection() must not include user_pass in any row.
     */
    public function test_get_collection_excludes_password(): void {
        // Ensure at least one user exists.
        $this->adapter->insert( $this->unique_user_payload() );

        $collection = $this->adapter->get_collection( $this->make_ctx() );
        $this->assertIsArray( $collection );
        $this->assertNotEmpty( $collection );

        foreach ( $collection as $row ) {
            $this->assertArrayNotHasKey(
                'user_pass',
                $row,
                'No row in get_collection() should expose user_pass.'
            );
        }
    }
}
