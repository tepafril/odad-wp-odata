<?php
/**
 * Unit tests for WPOS_Filter_Parser.
 */

use PHPUnit\Framework\TestCase;

class FilterParserTest extends TestCase {

    private WPOS_Filter_Parser $parser;

    protected function setUp(): void {
        $this->parser = new WPOS_Filter_Parser();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parse( string $expr ): WPOS_AST_Node {
        return $this->parser->parse( $expr );
    }

    // ── Simple binary comparisons ─────────────────────────────────────────────

    public function test_simple_eq_expression(): void {
        $node = $this->parse( "Title eq 'foo'" );

        $this->assertInstanceOf( WPOS_AST_Binary::class, $node );
        $this->assertSame( 'eq', $node->op );
        $this->assertInstanceOf( WPOS_AST_Property::class, $node->left );
        $this->assertSame( 'Title', $node->left->path );
        $this->assertInstanceOf( WPOS_AST_Literal::class, $node->right );
        $this->assertSame( 'string', $node->right->type );
        $this->assertSame( 'foo', $node->right->value );
    }

    public function test_ne_operator(): void {
        $node = $this->parse( "Status ne 'draft'" );

        $this->assertInstanceOf( WPOS_AST_Binary::class, $node );
        $this->assertSame( 'ne', $node->op );
    }

    public function test_lt_operator(): void {
        $node = $this->parse( 'Rating lt 5' );

        $this->assertInstanceOf( WPOS_AST_Binary::class, $node );
        $this->assertSame( 'lt', $node->op );
        $this->assertInstanceOf( WPOS_AST_Literal::class, $node->right );
        $this->assertSame( 'int', $node->right->type );
        $this->assertSame( 5, $node->right->value );
    }

    public function test_le_operator(): void {
        $node = $this->parse( 'Rating le 10' );

        $this->assertInstanceOf( WPOS_AST_Binary::class, $node );
        $this->assertSame( 'le', $node->op );
    }

    public function test_gt_operator(): void {
        $node = $this->parse( 'Rating gt 0' );

        $this->assertInstanceOf( WPOS_AST_Binary::class, $node );
        $this->assertSame( 'gt', $node->op );
    }

    public function test_ge_operator(): void {
        $node = $this->parse( 'Rating ge 1' );

        $this->assertInstanceOf( WPOS_AST_Binary::class, $node );
        $this->assertSame( 'ge', $node->op );
    }

    // ── Operator precedence: and binds tighter than or ────────────────────────

    /**
     * Expression: a eq 1 or b eq 2 and c eq 3
     * Expected tree (and binds tighter):
     *   or
     *   ├── (a eq 1)
     *   └── and
     *       ├── (b eq 2)
     *       └── (c eq 3)
     */
    public function test_and_binds_tighter_than_or(): void {
        $node = $this->parse( 'a eq 1 or b eq 2 and c eq 3' );

        $this->assertInstanceOf( WPOS_AST_Binary::class, $node );
        $this->assertSame( 'or', $node->op );

        // Left of or: a eq 1
        $left = $node->left;
        $this->assertInstanceOf( WPOS_AST_Binary::class, $left );
        $this->assertSame( 'eq', $left->op );
        $this->assertSame( 'a', $left->left->path );

        // Right of or: and node
        $right = $node->right;
        $this->assertInstanceOf( WPOS_AST_Binary::class, $right );
        $this->assertSame( 'and', $right->op );
        $this->assertSame( 'b', $right->left->left->path );
        $this->assertSame( 'c', $right->right->left->path );
    }

    // ── not unary ─────────────────────────────────────────────────────────────

    public function test_not_unary(): void {
        $node = $this->parse( "not Title eq 'foo'" );

        $this->assertInstanceOf( WPOS_AST_Unary::class, $node );
        $this->assertSame( 'not', $node->op );
        $this->assertInstanceOf( WPOS_AST_Binary::class, $node->operand );
    }

    // ── in operator ───────────────────────────────────────────────────────────

    public function test_in_operator(): void {
        $node = $this->parse( "Status in ('publish', 'draft')" );

        $this->assertInstanceOf( WPOS_AST_In::class, $node );
        $this->assertInstanceOf( WPOS_AST_Property::class, $node->property );
        $this->assertSame( 'Status', $node->property->path );
        $this->assertCount( 2, $node->values );
        $this->assertSame( 'publish', $node->values[0]->value );
        $this->assertSame( 'draft',   $node->values[1]->value );
    }

    // ── Function calls ────────────────────────────────────────────────────────

    public function test_contains_function(): void {
        $node = $this->parse( "contains(Title, 'foo')" );

        $this->assertInstanceOf( WPOS_AST_Function::class, $node );
        $this->assertSame( 'contains', $node->name );
        $this->assertCount( 2, $node->args );
        $this->assertInstanceOf( WPOS_AST_Property::class, $node->args[0] );
        $this->assertSame( 'Title', $node->args[0]->path );
        $this->assertInstanceOf( WPOS_AST_Literal::class, $node->args[1] );
        $this->assertSame( 'foo', $node->args[1]->value );
    }

    public function test_year_function_in_comparison(): void {
        $node = $this->parse( 'year(PublishedDate) gt 2020' );

        $this->assertInstanceOf( WPOS_AST_Binary::class, $node );
        $this->assertSame( 'gt', $node->op );
        $this->assertInstanceOf( WPOS_AST_Function::class, $node->left );
        $this->assertSame( 'year', $node->left->name );
        $this->assertInstanceOf( WPOS_AST_Literal::class, $node->right );
        $this->assertSame( 2020, $node->right->value );
    }

    // ── null literal ─────────────────────────────────────────────────────────

    public function test_null_literal(): void {
        $node = $this->parse( 'ParentID eq null' );

        $this->assertInstanceOf( WPOS_AST_Binary::class, $node );
        $this->assertSame( 'eq', $node->op );
        $this->assertInstanceOf( WPOS_AST_Literal::class, $node->right );
        $this->assertSame( 'null', $node->right->type );
        $this->assertNull( $node->right->value );
    }

    // ── Nested parentheses ────────────────────────────────────────────────────

    public function test_nested_parens_change_precedence(): void {
        // (a eq 1 or b eq 2) and c eq 3
        // Root should be 'and', left should be 'or'
        $node = $this->parse( '(a eq 1 or b eq 2) and c eq 3' );

        $this->assertInstanceOf( WPOS_AST_Binary::class, $node );
        $this->assertSame( 'and', $node->op );
        $this->assertInstanceOf( WPOS_AST_Binary::class, $node->left );
        $this->assertSame( 'or', $node->left->op );
    }

    // ── Error handling ────────────────────────────────────────────────────────

    public function test_invalid_expression_throws_exception(): void {
        $this->expectException( WPOS_Filter_Parse_Exception::class );
        $this->parse( 'eq eq eq' );
    }

    public function test_unclosed_paren_throws_exception(): void {
        $this->expectException( WPOS_Filter_Parse_Exception::class );
        $this->parse( "(Title eq 'foo'" );
    }
}
