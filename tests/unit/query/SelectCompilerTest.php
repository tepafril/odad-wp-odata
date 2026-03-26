<?php
/**
 * Unit tests for WPOS_Select_Compiler.
 */

use PHPUnit\Framework\TestCase;

class SelectCompilerTest extends TestCase {

    private WPOS_Select_Compiler $compiler;

    /** @var array<string,string> */
    private array $column_map = [
        'ID'     => 'p.ID',
        'Title'  => 'p.post_title',
        'Status' => 'p.post_status',
    ];

    protected function setUp(): void {
        $this->compiler = new WPOS_Select_Compiler();
    }

    // ── Selected properties map to correct column names ───────────────────────

    public function test_selected_properties_map_to_columns(): void {
        $result = $this->compiler->compile( [ 'Title', 'Status' ], $this->column_map );

        $this->assertStringContainsString( 'p.post_title', $result );
        $this->assertStringContainsString( 'p.post_status', $result );
    }

    // ── Key property always included ─────────────────────────────────────────

    public function test_key_property_always_included(): void {
        // Select only Title — ID should be prepended automatically.
        $result = $this->compiler->compile( [ 'Title' ], $this->column_map );

        $this->assertStringContainsString( 'p.ID', $result );
        $this->assertStringContainsString( 'p.post_title', $result );
    }

    public function test_key_not_duplicated_when_already_selected(): void {
        $result = $this->compiler->compile( [ 'ID', 'Title' ], $this->column_map );

        // p.ID should appear exactly once
        $this->assertSame( 1, substr_count( $result, 'p.ID' ) );
    }

    // ── Unknown property throws exception ─────────────────────────────────────

    public function test_unknown_property_throws_exception(): void {
        $this->expectException( WPOS_Select_Exception::class );
        $this->compiler->compile( [ 'NonExistent' ], $this->column_map );
    }

    // ── Empty select returns all columns ─────────────────────────────────────

    public function test_empty_select_returns_all_columns(): void {
        $result = $this->compiler->compile( [], $this->column_map );

        $this->assertStringContainsString( 'p.ID', $result );
        $this->assertStringContainsString( 'p.post_title', $result );
        $this->assertStringContainsString( 'p.post_status', $result );
    }

    // ── Empty column map returns wildcard ─────────────────────────────────────

    public function test_empty_column_map_returns_wildcard(): void {
        $result = $this->compiler->compile( [], [] );

        $this->assertSame( '*', $result );
    }
}
