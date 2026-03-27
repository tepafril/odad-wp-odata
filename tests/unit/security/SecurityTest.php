<?php
/**
 * Security hardening unit tests for WP-OData Suite.
 *
 * These tests verify defence-in-depth mechanisms in the filter parser,
 * filter compiler, orderby compiler, select compiler, and batch handler.
 * No WordPress bootstrap is required — the unit bootstrap defines all stubs.
 */

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase {

    // ── Shared fixtures ───────────────────────────────────────────────────────

    /** @var array<string,string> */
    private array $column_map = [
        'ID'     => 'p.ID',
        'Title'  => 'p.post_title',
        'Status' => 'p.post_status',
    ];

    private ODAD_Filter_Parser   $parser;
    private ODAD_Filter_Compiler $compiler;

    protected function setUp(): void {
        $this->parser   = new ODAD_Filter_Parser();
        $this->compiler = new ODAD_Filter_Compiler();
    }

    // =========================================================================
    // Test 1 — SQL injection in filter value
    // =========================================================================

    /**
     * Parse and compile `Title eq '; DROP TABLE wp_posts; --'`.
     *
     * The resulting SQL must use a parameterized placeholder (%s) and the
     * dangerous string must appear only in the params array, never in the SQL.
     */
    public function test_sql_injection_in_filter_value(): void {
        $dangerous = "'; DROP TABLE wp_posts; --";
        $filter    = "Title eq '" . str_replace( "'", "''", $dangerous ) . "'";

        $ast    = $this->parser->parse( $filter );
        $result = $this->compiler->compile( $ast, $this->column_map );

        // The SQL fragment must contain a placeholder, not the raw dangerous string.
        $this->assertStringContainsString( '%s', $result['sql'],
            'SQL should use a parameterized placeholder for string values.' );

        // The literal dangerous string must NOT appear in the SQL fragment.
        $this->assertStringNotContainsString( 'DROP TABLE', $result['sql'],
            'SQL fragment must not contain the literal dangerous string.' );

        // The dangerous value must appear in the params array (ready for prepare()).
        $this->assertContains( $dangerous, $result['params'],
            'The dangerous string should be in the params array, not in SQL.' );
    }

    // =========================================================================
    // Test 2 — ORDER BY direction injection is rejected
    // =========================================================================

    /**
     * Attempt to compile an orderby with a malicious direction string.
     * The compiler must normalize it to ASC (or throw) and never emit the
     * raw dangerous SQL fragment.
     */
    public function test_orderby_injection_rejected(): void {
        $compiler = new ODAD_Orderby_Compiler();

        $dangerous_dir = "'; DROP TABLE wp_posts; --";

        // Build an orderby string: "Title <dangerous_direction>"
        $orderby = "Title {$dangerous_dir}";

        // The compiler should either normalize the direction to ASC/DESC or throw.
        // Either outcome is acceptable — what is NOT acceptable is emitting the
        // dangerous string verbatim in the SQL output.
        try {
            $sql = $compiler->compile( $orderby, $this->column_map );

            // If it didn't throw, the direction must be safely normalized.
            $this->assertStringNotContainsString( 'DROP TABLE', $sql,
                'Orderby SQL must not contain the raw injected direction string.' );

            // Direction must be either ASC or DESC.
            $this->assertMatchesRegularExpression(
                '/\b(ASC|DESC)\b/i',
                $sql,
                'Direction must be normalized to ASC or DESC.'
            );
        } catch ( ODAD_Orderby_Exception $e ) {
            // Throwing is also acceptable; the property segment before the
            // dangerous direction may or may not be valid depending on parsing.
            $this->addToAssertionCount( 1 );
        }
    }

    // =========================================================================
    // Test 3 — Filter max depth enforced
    // =========================================================================

    /**
     * Build a deeply nested filter expression (21 levels of parentheses) and
     * verify that the parser throws ODAD_Filter_Parse_Exception.
     */
    public function test_filter_max_depth_enforced(): void {
        // Build 21 levels of nested parentheses: (((... Title eq 'x' ...)))
        $depth      = 21;
        $inner      = "Title eq 'x'";
        $expression = str_repeat( '(', $depth ) . $inner . str_repeat( ')', $depth );

        $this->expectException( ODAD_Filter_Parse_Exception::class );

        $this->parser->parse( $expression );
    }

    // =========================================================================
    // Test 4 — SELECT with unknown property is rejected
    // =========================================================================

    /**
     * Attempt to compile a SELECT containing a property not in the column map.
     * The compiler must throw and the unknown property name must not appear in
     * any SQL output.
     */
    public function test_select_unknown_property_rejected(): void {
        $compiler        = new ODAD_Select_Compiler();
        $unknown_property = 'InjectedProp; DROP TABLE wp_posts; --';

        $threw = false;
        $sql   = '';

        try {
            $sql = $compiler->compile( [ $unknown_property ], $this->column_map );
        } catch ( ODAD_Select_Exception $e ) {
            $threw = true;
            // The exception message should not include the raw property in a way
            // that could be exploited — we just verify an exception was thrown.
        }

        $this->assertTrue( $threw,
            'ODAD_Select_Compiler must throw ODAD_Select_Exception for unknown properties.' );

        // If (hypothetically) no exception was thrown, SQL must not contain the property.
        $this->assertStringNotContainsString( 'DROP TABLE', $sql,
            'SQL output must not contain the injected property name.' );
    }

    // =========================================================================
    // Test 5 — Batch handler rejects external URLs
    // =========================================================================

    /**
     * Verify that ODAD_Batch_Handler::is_safe_url() (via Reflection) rejects
     * external URLs and accepts relative URLs.
     */
    public function test_batch_external_url_rejected(): void {
        // We need a ODAD_Batch_Handler instance. Use reflection to call the
        // private is_safe_url() method without constructing the full graph.
        $handler = $this->createBatchHandlerWithReflection();

        $reflect = new ReflectionClass( $handler );
        $method  = $reflect->getMethod( 'is_safe_url' );
        $method->setAccessible( true );

        // External URLs must be rejected.
        $this->assertFalse(
            $method->invoke( $handler, 'http://evil.example.com/steal-data' ),
            'HTTP URL to external host must be rejected.'
        );
        $this->assertFalse(
            $method->invoke( $handler, 'https://attacker.example.org/Posts' ),
            'HTTPS URL to external host must be rejected.'
        );

        // Relative paths starting with / must be allowed.
        $this->assertTrue(
            $method->invoke( $handler, '/wp-json/odata/v4/Posts' ),
            'Relative path starting with / must be allowed.'
        );
        $this->assertTrue(
            $method->invoke( $handler, '/odata/v4/Posts(1)' ),
            'Relative path starting with / must be allowed.'
        );

        // Bare relative paths (no scheme) must also be allowed.
        $this->assertTrue(
            $method->invoke( $handler, 'Posts(1)' ),
            'Bare relative path without scheme must be allowed.'
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Create a minimal ODAD_Batch_Handler for reflection testing.
     *
     * The constructor requires a router-resolver Closure and a
     * ODAD_Permission_Engine. We pass dummy stubs — neither is invoked by
     * is_safe_url().
     */
    private function createBatchHandlerWithReflection(): ODAD_Batch_Handler {
        $router_resolver = static function (): never {
            throw new \LogicException( 'Router must not be resolved in is_safe_url tests.' );
        };

        $permissions = $this->createStub( ODAD_Permission_Engine::class );

        return new ODAD_Batch_Handler( $router_resolver, $permissions );
    }
}
