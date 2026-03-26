<?php
/**
 * Unit tests for WPOS_Orderby_Compiler.
 */

use PHPUnit\Framework\TestCase;

class OrderbyCompilerTest extends TestCase {

    private WPOS_Orderby_Compiler $compiler;

    /** @var array<string,string> */
    private array $column_map = [
        'Title'  => 'post_title',
        'Status' => 'post_status',
        'ID'     => 'ID',
    ];

    protected function setUp(): void {
        $this->compiler = new WPOS_Orderby_Compiler();
    }

    // ── Ascending (default) ───────────────────────────────────────────────────

    public function test_property_without_direction_defaults_to_asc(): void {
        $result = $this->compiler->compile( 'Title', $this->column_map );

        $this->assertSame( 'post_title ASC', $result );
    }

    // ── Descending ────────────────────────────────────────────────────────────

    public function test_property_with_desc_direction(): void {
        $result = $this->compiler->compile( 'Title desc', $this->column_map );

        $this->assertSame( 'post_title DESC', $result );
    }

    // ── Multiple properties ───────────────────────────────────────────────────

    public function test_multiple_properties_with_mixed_directions(): void {
        $result = $this->compiler->compile( 'Title,Status desc', $this->column_map );

        $this->assertStringContainsString( 'post_title ASC', $result );
        $this->assertStringContainsString( 'post_status DESC', $result );
    }

    public function test_multiple_properties_comma_separated_with_spaces(): void {
        $result = $this->compiler->compile( 'Title asc, Status desc', $this->column_map );

        $parts = explode( ', ', $result );
        $this->assertCount( 2, $parts );
        $this->assertSame( 'post_title ASC',   $parts[0] );
        $this->assertSame( 'post_status DESC', $parts[1] );
    }

    // ── Unknown property ─────────────────────────────────────────────────────

    public function test_unknown_property_throws_exception(): void {
        $this->expectException( WPOS_Orderby_Exception::class );
        $this->compiler->compile( 'NonExistent', $this->column_map );
    }

    // ── Empty string ─────────────────────────────────────────────────────────

    public function test_empty_string_returns_empty_string(): void {
        $result = $this->compiler->compile( '', $this->column_map );

        $this->assertSame( '', $result );
    }

    public function test_whitespace_only_returns_empty_string(): void {
        $result = $this->compiler->compile( '   ', $this->column_map );

        $this->assertSame( '', $result );
    }
}
