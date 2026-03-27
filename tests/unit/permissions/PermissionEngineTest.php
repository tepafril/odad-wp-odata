<?php
/**
 * Unit tests for ODAD_Permission_Engine.
 */

use PHPUnit\Framework\TestCase;

class PermissionEngineTest extends TestCase {

    private ODAD_Capability_Map    $capability_map;
    private ODAD_Permission_Engine $engine;

    protected function setUp(): void {
        $this->capability_map = new ODAD_Capability_Map();
        $this->engine         = new ODAD_Permission_Engine( $this->capability_map );
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Build a WP_User stub with specific capabilities.
     *
     * @param array<string, bool> $caps
     */
    private function make_user( int $id, array $roles, array $caps ): WP_User {
        return new WP_User( $id, $roles, $caps );
    }

    // ── Entity-level checks ───────────────────────────────────────────────────

    public function test_user_with_read_cap_can_read_posts(): void {
        $user = $this->make_user( 1, [ 'subscriber' ], [ 'read' => true ] );

        $this->assertTrue( $this->engine->can_read( 'Posts', $user ) );
    }

    public function test_user_without_edit_posts_cannot_insert_posts(): void {
        $user = $this->make_user( 2, [ 'subscriber' ], [ 'read' => true ] );

        $this->assertFalse( $this->engine->can_insert( 'Posts', $user ) );
    }

    public function test_user_with_edit_posts_can_insert_posts(): void {
        $user = $this->make_user( 3, [ 'editor' ], [ 'read' => true, 'edit_posts' => true ] );

        $this->assertTrue( $this->engine->can_insert( 'Posts', $user ) );
    }

    /**
     * For an entity set not in the default map or custom map, the capability
     * should follow the convention: ODAD_{entity_set_lower}_{operation}.
     */
    public function test_convention_fallback_for_unknown_entity_set(): void {
        // The convention for 'Employees' / 'read' is 'ODAD_employees_read'.
        // A user without that cap should be denied.
        $user = $this->make_user( 4, [ 'subscriber' ], [ 'read' => true ] );

        $this->assertFalse( $this->engine->can_read( 'Employees', $user ) );

        // Grant the convention cap and confirm access.
        $user_with_cap = $this->make_user( 5, [ 'subscriber' ], [
            'read'              => true,
            'ODAD_employees_read' => true,
        ] );

        $this->assertTrue( $this->engine->can_read( 'Employees', $user_with_cap ) );
    }

    // ── Row-level security ────────────────────────────────────────────────────

    public function test_apply_row_filter_adds_post_status_condition_for_non_admin(): void {
        $user = $this->make_user( 10, [ 'subscriber' ], [ 'read' => true ] );
        $ctx  = new ODAD_Query_Context();

        $this->engine->apply_row_filter( 'Posts', $user, $ctx );

        $this->assertNotEmpty( $ctx->extra_conditions );
        $joined = implode( ' ', $ctx->extra_conditions );
        $this->assertStringContainsString( 'post_status', $joined );
        $this->assertStringContainsString( 'post_author', $joined );
    }

    public function test_apply_row_filter_adds_no_conditions_for_admin(): void {
        $user = $this->make_user( 1, [ 'administrator' ], [ 'administrator' => true ] );
        $ctx  = new ODAD_Query_Context();

        $this->engine->apply_row_filter( 'Posts', $user, $ctx );

        $this->assertEmpty( $ctx->extra_conditions );
    }

    public function test_apply_row_filter_returns_ctx(): void {
        $user = $this->make_user( 2, [ 'subscriber' ], [ 'read' => true ] );
        $ctx  = new ODAD_Query_Context();

        $returned = $this->engine->apply_row_filter( 'Posts', $user, $ctx );

        $this->assertSame( $ctx, $returned );
    }

    public function test_apply_row_filter_adds_no_conditions_for_unknown_entity_set(): void {
        $user = $this->make_user( 3, [ 'subscriber' ], [ 'read' => true ] );
        $ctx  = new ODAD_Query_Context();

        $this->engine->apply_row_filter( 'Employees', $user, $ctx );

        $this->assertEmpty( $ctx->extra_conditions );
    }
}
