<?php
/**
 * Integration tests for the OData REST endpoints registered by ODAD_Router.
 *
 * Each test dispatches requests through a fresh WP_REST_Server instance that
 * is re-initialised via the 'rest_api_init' action.  This matches the approach
 * used throughout WP core's own REST API test suite.
 *
 * @package ODAD\Tests\Integration
 */

class ODataEndpointTest extends WP_UnitTestCase {

    /** @var WP_REST_Server */
    protected WP_REST_Server $server;

    public function setUp(): void {
        parent::setUp();

        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server();
        do_action( 'rest_api_init' );
    }

    public function tearDown(): void {
        global $wp_rest_server;
        $wp_rest_server = null;

        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function get_request( string $route, array $params = [] ): WP_REST_Response {
        $request = new WP_REST_Request( 'GET', $route );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }
        return $this->server->dispatch( $request );
    }

    private function post_request( string $route, array $body = [] ): WP_REST_Response {
        $request = new WP_REST_Request( 'POST', $route );
        $request->set_body_params( $body );
        return $this->server->dispatch( $request );
    }

    private function create_admin(): int {
        return $this->factory->user->create( [ 'role' => 'administrator' ] );
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * GET /odata/v4/ → 200 with @odata.context and value keys.
     */
    public function test_service_document(): void {
        $response = $this->get_request( '/odata/v4/' );
        $data     = $response->get_data();

        $this->assertSame( 200, $response->get_status() );
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( '@odata.context', $data );
        $this->assertArrayHasKey( 'value',          $data );
    }

    /**
     * GET /odata/v4/$metadata → 200 (XML CSDL document).
     *
     * The metadata endpoint is public (no auth required by the router).
     */
    public function test_metadata_xml(): void {
        $response = $this->get_request( '/odata/v4/$metadata' );

        $this->assertSame(
            200,
            $response->get_status(),
            '$metadata endpoint must return 200.'
        );
    }

    /**
     * Anonymous GET /odata/v4/Posts → 403 (public access is denied by default).
     */
    public function test_posts_collection_requires_auth(): void {
        wp_set_current_user( 0 ); // ensure anonymous

        $response = $this->get_request( '/odata/v4/Posts' );
        $status   = $response->get_status();

        $this->assertContains(
            $status,
            [ 401, 403 ],
            'Anonymous request to /Posts must be rejected with 401 or 403.'
        );
    }

    /**
     * Authenticated admin GET /odata/v4/Posts → 200 with @odata.context key.
     */
    public function test_posts_collection_as_admin(): void {
        wp_set_current_user( $this->create_admin() );

        $response = $this->get_request( '/odata/v4/Posts' );
        $data     = $response->get_data();

        $this->assertSame( 200, $response->get_status() );
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( '@odata.context', $data );
    }

    /**
     * GET /odata/v4/Posts?$top=2&$skip=0 → 200, value contains ≤ 2 items.
     */
    public function test_top_and_skip(): void {
        wp_set_current_user( $this->create_admin() );

        // Ensure at least 3 posts exist so pagination is meaningful.
        $this->factory->post->create_many( 3, [ 'post_status' => 'publish' ] );

        $request = new WP_REST_Request( 'GET', '/odata/v4/Posts' );
        $request->set_param( '$top',  '2' );
        $request->set_param( '$skip', '0' );

        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();

        $this->assertSame( 200, $response->get_status() );
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( 'value', $data );
        $this->assertLessThanOrEqual(
            2,
            count( $data['value'] ),
            '$top=2 must return at most 2 items.'
        );
    }

    /**
     * GET /odata/v4/Posts?$filter=Title eq 'Unique-ODAD-Test' → result contains that post.
     */
    public function test_filter_eq(): void {
        wp_set_current_user( $this->create_admin() );

        $unique_title = 'Unique-ODAD-Test-' . mt_rand( 10000, 99999 );
        $this->factory->post->create( [
            'post_title'  => $unique_title,
            'post_status' => 'publish',
        ] );

        $request = new WP_REST_Request( 'GET', '/odata/v4/Posts' );
        $request->set_param( '$filter', "Title eq '{$unique_title}'" );

        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();

        $this->assertSame( 200, $response->get_status() );
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( 'value', $data );

        $titles = array_column( $data['value'], 'Title' );
        $this->assertContains(
            $unique_title,
            $titles,
            "\$filter=Title eq '{$unique_title}' must return a matching post."
        );
    }

    /**
     * POST /odata/v4/Posts as admin.
     *
     * The router's handle_create() is currently a 501 stub (entity creation is
     * not yet implemented in Phase 1).  The test asserts 501 to match the
     * current implementation state; update to assert 201 once the write layer
     * is wired to the REST endpoint.
     */
    public function test_create_post_as_admin(): void {
        wp_set_current_user( $this->create_admin() );

        $response = $this->post_request( '/odata/v4/Posts', [
            'Title'  => 'New Post via REST',
            'Status' => 'publish',
        ] );

        // handle_create() is a 501 stub until the write handler is wired up.
        // Expected status: 501 (current), or 201 once implemented.
        $this->assertContains(
            $response->get_status(),
            [ 201, 501 ],
            'POST /Posts as admin should return 201 (created) or 501 (not implemented).'
        );
    }

    /**
     * GET /odata/v4/Posts?$count=true → response has @odata.count key.
     */
    public function test_count(): void {
        wp_set_current_user( $this->create_admin() );

        $this->factory->post->create( [ 'post_status' => 'publish' ] );

        $request = new WP_REST_Request( 'GET', '/odata/v4/Posts' );
        $request->set_param( '$count', 'true' );

        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();

        $this->assertSame( 200, $response->get_status() );
        $this->assertIsArray( $data );
        $this->assertArrayHasKey(
            '@odata.count',
            $data,
            'Response to $count=true must include @odata.count.'
        );
    }
}
