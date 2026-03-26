<?php
defined( 'ABSPATH' ) || exit;

/**
 * Compiles an OData $compute clause into SQL computed column expressions.
 *
 * Supported arithmetic operators: add, sub, mul, divby.
 * No WordPress calls are made in this class.
 */
class WPOS_Compute_Compiler {

    /**
     * Map of OData arithmetic operator tokens to their SQL equivalents.
     *
     * @var array<string,string>
     */
    private const OPERATOR_MAP = [
        'add'   => '+',
        'sub'   => '-',
        'mul'   => '*',
        'divby' => '/',
    ];

    /**
     * Compile an OData $compute string into SQL computed column expressions.
     *
     * Each clause has the form:  <expression> as <Alias>
     * where <expression> may contain property names and arithmetic operators.
     *
     * Example:
     *   $compute = 'Price mul Quantity as Total, Price add Tax as GrossPrice'
     *   returns [
     *       'columns' => ['p.price * p.quantity AS Total', 'p.price + p.tax AS GrossPrice'],
     *       'aliases' => ['Total', 'GrossPrice'],
     *   ]
     *
     * @param string               $compute    Raw $compute query option value.
     * @param array<string,string> $column_map Map of OData property name → SQL column expression.
     * @return array{columns: string[], aliases: string[]}
     *         'columns' — SQL column expressions ready to append to a SELECT list.
     *         'aliases' — New OData property names introduced by the compute clause.
     * @throws InvalidArgumentException If a property name is not found in $column_map or
     *                                  a clause lacks a valid "as <Alias>" definition.
     */
    public function compile( string $compute, array $column_map ): array {
        $compute = trim( $compute );

        if ( '' === $compute ) {
            return [ 'columns' => [], 'aliases' => [] ];
        }

        $columns = [];
        $aliases = [];

        // Split on commas that are NOT inside parentheses (basic split for v1).
        $clauses = $this->split_clauses( $compute );

        foreach ( $clauses as $clause ) {
            $clause = trim( $clause );

            if ( '' === $clause ) {
                continue;
            }

            // Locate the "as <Alias>" suffix (case-insensitive).
            if ( ! preg_match( '/^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $clause, $m ) ) {
                throw new InvalidArgumentException(
                    sprintf( 'Invalid $compute clause (missing "as <Alias>"): "%s"', $clause )
                );
            }

            $expression_raw = trim( $m[1] );
            $alias          = $m[2];  // Keep the casing supplied by the caller.

            $sql_expression = $this->compile_expression( $expression_raw, $column_map );

            $columns[] = $sql_expression . ' AS ' . $alias;
            $aliases[] = $alias;
        }

        return [ 'columns' => $columns, 'aliases' => $aliases ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Split a $compute string on top-level commas (not inside parentheses).
     *
     * @param string $compute Raw $compute value.
     * @return string[]
     */
    private function split_clauses( string $compute ): array {
        $clauses = [];
        $depth   = 0;
        $current = '';

        for ( $i = 0, $len = strlen( $compute ); $i < $len; $i++ ) {
            $char = $compute[ $i ];

            if ( '(' === $char ) {
                $depth++;
                $current .= $char;
            } elseif ( ')' === $char ) {
                $depth--;
                $current .= $char;
            } elseif ( ',' === $char && 0 === $depth ) {
                $clauses[] = $current;
                $current   = '';
            } else {
                $current .= $char;
            }
        }

        if ( '' !== $current ) {
            $clauses[] = $current;
        }

        return $clauses;
    }

    /**
     * Translate a single arithmetic expression (without the alias) into SQL.
     *
     * Tokens are split by whitespace; each token is either an operator keyword
     * or a property / numeric literal.
     *
     * @param string               $expression_raw Raw expression, e.g. "Price mul Quantity".
     * @param array<string,string> $column_map
     * @return string SQL expression, e.g. "p.price * p.quantity".
     * @throws InvalidArgumentException On unknown property or invalid token sequence.
     */
    private function compile_expression( string $expression_raw, array $column_map ): string {
        $tokens = preg_split( '/\s+/', trim( $expression_raw ), -1, PREG_SPLIT_NO_EMPTY );

        if ( empty( $tokens ) ) {
            throw new InvalidArgumentException( 'Empty $compute expression.' );
        }

        $sql_parts = [];
        $expect_operand = true;  // Alternating: operand → operator → operand ...

        foreach ( $tokens as $token ) {
            if ( $expect_operand ) {
                $sql_parts[]    = $this->resolve_operand( $token, $column_map );
                $expect_operand = false;
            } else {
                // Expect an operator.
                $lower = strtolower( $token );
                if ( ! isset( self::OPERATOR_MAP[ $lower ] ) ) {
                    throw new InvalidArgumentException(
                        sprintf( 'Unknown $compute operator: "%s"', $token )
                    );
                }
                $sql_parts[]    = self::OPERATOR_MAP[ $lower ];
                $expect_operand = true;
            }
        }

        if ( $expect_operand ) {
            throw new InvalidArgumentException(
                sprintf( 'Incomplete $compute expression: "%s"', $expression_raw )
            );
        }

        return implode( ' ', $sql_parts );
    }

    /**
     * Resolve a single operand token to its SQL equivalent.
     *
     * Accepts:
     * - A property name present in $column_map.
     * - A numeric literal (integer or decimal).
     *
     * @param string               $token
     * @param array<string,string> $column_map
     * @return string SQL column reference or numeric literal.
     * @throws InvalidArgumentException For unknown property names.
     */
    private function resolve_operand( string $token, array $column_map ): string {
        // Numeric literal — safe to embed directly.
        if ( is_numeric( $token ) ) {
            return $token;
        }

        if ( ! array_key_exists( $token, $column_map ) ) {
            throw new InvalidArgumentException(
                sprintf( 'Unknown $compute property: "%s"', $token )
            );
        }

        return $column_map[ $token ];
    }
}
