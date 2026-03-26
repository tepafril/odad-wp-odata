<?php
/**
 * Unit tests for WPOS_Filter_Compiler.
 */

use PHPUnit\Framework\TestCase;

class FilterCompilerTest extends TestCase {

    private WPOS_Filter_Parser   $parser;
    private WPOS_Filter_Compiler $compiler;

    /** @var array<string,string> */
    private array $column_map = [
        'Title'  => 'post_title',
        'Status' => 'post_status',
        'ID'     => 'ID',
    ];

    protected function setUp(): void {
        $this->parser   = new WPOS_Filter_Parser();
        $this->compiler = new WPOS_Filter_Compiler();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Parse an OData filter string and compile it against the standard column map.
     *
     * @return array{sql: string, params: array}
     */
    private function compile( string $filter, ?array $column_map = null ): array {
        $ast = $this->parser->parse( $filter );
        return $this->compiler->compile( $ast, $column_map ?? $this->column_map );
    }

    // ── Equality ─────────────────────────────────────────────────────────────

    public function test_eq_string_produces_placeholder_and_param(): void {
        $result = $this->compile( "Title eq 'foo'" );

        $this->assertStringContainsString( '= %s', $result['sql'] );
        $this->assertStringContainsString( 'post_title', $result['sql'] );
        $this->assertSame( [ 'foo' ], $result['params'] );
    }

    // ── Null comparison ───────────────────────────────────────────────────────

    public function test_eq_null_produces_is_null(): void {
        $result = $this->compile( 'ID eq null' );

        $this->assertStringContainsString( 'IS NULL', $result['sql'] );
        $this->assertStringContainsString( 'ID', $result['sql'] );
        $this->assertSame( [], $result['params'] );
    }

    public function test_ne_null_produces_is_not_null(): void {
        $result = $this->compile( 'ID ne null' );

        $this->assertStringContainsString( 'IS NOT NULL', $result['sql'] );
        $this->assertSame( [], $result['params'] );
    }

    // ── IN operator ───────────────────────────────────────────────────────────

    public function test_in_operator_produces_in_clause(): void {
        $result = $this->compile( "Status in ('publish', 'draft')" );

        $this->assertStringContainsString( 'IN (%s, %s)', $result['sql'] );
        $this->assertStringContainsString( 'post_status', $result['sql'] );
        $this->assertSame( [ 'publish', 'draft' ], $result['params'] );
    }

    // ── contains / startswith ─────────────────────────────────────────────────

    public function test_contains_produces_like_with_concat(): void {
        $result = $this->compile( "contains(Title, 'foo')" );

        $this->assertStringContainsString( 'LIKE', $result['sql'] );
        $this->assertStringContainsString( "CONCAT('%'", $result['sql'] );
        $this->assertSame( [ 'foo' ], $result['params'] );
    }

    public function test_startswith_produces_like_concat(): void {
        $result = $this->compile( "startswith(Title, 'foo')" );

        $this->assertStringContainsString( 'LIKE', $result['sql'] );
        $this->assertStringContainsString( "CONCAT(", $result['sql'] );
        $this->assertStringContainsString( "'%'", $result['sql'] );
        $this->assertSame( [ 'foo' ], $result['params'] );
    }

    // ── Logical operators ─────────────────────────────────────────────────────

    public function test_and_wraps_in_parentheses(): void {
        $result = $this->compile( "Title eq 'foo' and Status eq 'publish'" );

        $this->assertStringContainsString( 'AND', $result['sql'] );
        $this->assertStringContainsString( '(', $result['sql'] );
        $this->assertCount( 2, $result['params'] );
    }

    public function test_or_wraps_in_parentheses(): void {
        $result = $this->compile( "Title eq 'foo' or Status eq 'draft'" );

        $this->assertStringContainsString( 'OR', $result['sql'] );
        $this->assertStringContainsString( '(', $result['sql'] );
        $this->assertCount( 2, $result['params'] );
    }

    // ── Unknown property ─────────────────────────────────────────────────────

    public function test_unknown_property_throws_exception(): void {
        $this->expectException( WPOS_Filter_Compile_Exception::class );
        $this->compile( "UnknownProp eq 'value'" );
    }

    public function test_unknown_property_in_function_throws_exception(): void {
        $this->expectException( WPOS_Filter_Compile_Exception::class );
        $this->compile( "contains(UnknownProp, 'value')" );
    }
}
