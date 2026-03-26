<?php
/**
 * Unit tests for WPOS_Field_ACL.
 */

use PHPUnit\Framework\TestCase;

class FieldACLTest extends TestCase {

    private WPOS_Schema_Registry $registry;
    private WPOS_Field_ACL       $acl;

    protected function setUp(): void {
        $this->registry = new WPOS_Schema_Registry();

        // Register a minimal Users entity set with Email gated by 'list_users'.
        $this->registry->register( 'Users', [
            'entity_type'  => 'UserEntityType',
            'key_property' => 'ID',
            'properties'   => [
                'ID'    => [ 'type' => 'Edm.Int32' ],
                'Name'  => [ 'type' => 'Edm.String' ],
                'Email' => [
                    'type'                => 'Edm.String',
                    'required_capability' => 'list_users',
                ],
                'Status' => [ 'type' => 'Edm.String', 'read_only' => true ],
            ],
        ] );

        $this->acl = new WPOS_Field_ACL(
            permission_engine: null,
            schema_registry:   $this->registry,
        );
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function make_user( int $id, array $caps ): WP_User {
        return new WP_User( $id, [], $caps );
    }

    // ── apply() strips / keeps capability-gated fields ────────────────────────

    public function test_apply_strips_email_for_user_without_list_users(): void {
        $user = $this->make_user( 1, [ 'read' => true ] );
        $rows = [
            [ 'ID' => 1, 'Name' => 'Alice', 'Email' => 'alice@example.com' ],
        ];

        $result = $this->acl->apply( $rows, 'Users', $user, 'read' );

        $this->assertArrayNotHasKey( 'Email', $result[0] );
    }

    public function test_apply_keeps_email_for_user_with_list_users(): void {
        $user = $this->make_user( 2, [ 'list_users' => true ] );
        $rows = [
            [ 'ID' => 2, 'Name' => 'Bob', 'Email' => 'bob@example.com' ],
        ];

        $result = $this->acl->apply( $rows, 'Users', $user, 'read' );

        $this->assertArrayHasKey( 'Email', $result[0] );
        $this->assertSame( 'bob@example.com', $result[0]['Email'] );
    }

    // ── Key property is never stripped ───────────────────────────────────────

    public function test_apply_never_strips_key_property(): void {
        // Even with no capabilities, ID must be present.
        $user = $this->make_user( 3, [] );
        $rows = [
            [ 'ID' => 3, 'Name' => 'Carol', 'Email' => 'carol@example.com' ],
        ];

        $result = $this->acl->apply( $rows, 'Users', $user, 'read' );

        $this->assertArrayHasKey( 'ID', $result[0] );
    }

    // ── validate_write() ─────────────────────────────────────────────────────

    public function test_validate_write_throws_on_always_read_only_field(): void {
        $user = $this->make_user( 4, [ 'list_users' => true, 'edit_users' => true ] );

        $this->expectException( WPOS_Field_ACL_Exception::class );

        // 'ID' is in ALWAYS_READ_ONLY.
        $this->acl->validate_write( [ 'ID' => 99, 'Name' => 'Dave' ], 'Users', $user, 'update' );
    }

    public function test_validate_write_throws_on_schema_read_only_field(): void {
        $user = $this->make_user( 5, [ 'list_users' => true, 'edit_users' => true ] );

        $this->expectException( WPOS_Field_ACL_Exception::class );

        // 'Status' is marked read_only in the schema.
        $this->acl->validate_write( [ 'Name' => 'Eve', 'Status' => 'active' ], 'Users', $user, 'update' );
    }

    public function test_validate_write_throws_when_user_lacks_capability_for_field(): void {
        // User does not have 'list_users', so writing 'Email' is forbidden.
        $user = $this->make_user( 6, [ 'read' => true ] );

        $this->expectException( WPOS_Field_ACL_Exception::class );

        $this->acl->validate_write( [ 'Name' => 'Frank', 'Email' => 'frank@example.com' ], 'Users', $user, 'update' );
    }

    public function test_validate_write_accepts_valid_payload(): void {
        // User has list_users; writes Name and Email — both allowed.
        $user = $this->make_user( 7, [ 'list_users' => true ] );

        // Must not throw.
        $this->acl->validate_write( [ 'Name' => 'Grace', 'Email' => 'grace@example.com' ], 'Users', $user, 'update' );

        $this->assertTrue( true ); // Reached here = no exception.
    }
}
