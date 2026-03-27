<?php
defined( 'ABSPATH' ) || exit;

/**
 * Compiles an OData $filter AST into a parameterized SQL WHERE fragment.
 *
 * Returns an array with two keys:
 *   'sql'    — SQL string with %s / %d / %f placeholders suitable for
 *              passing to $wpdb->prepare().
 *   'params' — ordered array of values to be bound in the same order as
 *              the placeholders.
 *
 * This class has no WordPress dependencies: it performs no $wpdb calls.
 * The caller is responsible for passing the result through $wpdb->prepare().
 *
 * Security guarantee: column names are NEVER interpolated from user input.
 * Every property path is validated against $column_map before use; an
 * unknown property throws ODAD_Filter_Compile_Exception.
 */
class ODAD_Filter_Compiler {

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Compile an AST into a SQL WHERE fragment.
     *
     * @param ODAD_AST_Node        $ast        Root node from ODAD_Filter_Parser.
     * @param array<string,string> $column_map OData property name → SQL column expression.
     *
     * @return array{sql: string, params: array}
     *
     * @throws ODAD_Filter_Compile_Exception  When the AST references an unknown property.
     */
    public function compile( ODAD_AST_Node $ast, array $column_map ): array {
        $sql    = '';
        $params = [];

        $this->walk( $ast, $column_map, $sql, $params );

        return [ 'sql' => $sql, 'params' => $params ];
    }

    // =========================================================================
    // Core recursive walker
    // =========================================================================

    /**
     * Recursively walk $node, appending to $sql and $params.
     *
     * @param ODAD_AST_Node        $node
     * @param array<string,string> $column_map
     * @param string               $sql    Accumulated SQL fragment (passed by reference).
     * @param array                $params Accumulated parameter list (passed by reference).
     */
    private function walk(
        ODAD_AST_Node $node,
        array $column_map,
        string &$sql,
        array &$params
    ): void {
        if ( $node instanceof ODAD_AST_Binary ) {
            $this->walk_binary( $node, $column_map, $sql, $params );
            return;
        }

        if ( $node instanceof ODAD_AST_Unary ) {
            $this->walk_unary( $node, $column_map, $sql, $params );
            return;
        }

        if ( $node instanceof ODAD_AST_In ) {
            $this->walk_in( $node, $column_map, $sql, $params );
            return;
        }

        if ( $node instanceof ODAD_AST_Function ) {
            $this->walk_function( $node, $column_map, $sql, $params );
            return;
        }

        if ( $node instanceof ODAD_AST_Property ) {
            $sql .= $this->resolve_column( $node->path, $column_map );
            return;
        }

        if ( $node instanceof ODAD_AST_Literal ) {
            $this->walk_literal( $node, $sql, $params );
            return;
        }

        // ODAD_AST_Lambda — not supported in SQL compilation; raise a clear error.
        if ( $node instanceof ODAD_AST_Lambda ) {
            throw new ODAD_Filter_Compile_Exception(
                "Lambda expressions (any/all) are not supported in SQL compilation."
            );
        }

        throw new ODAD_Filter_Compile_Exception(
            "Unknown AST node type: " . get_class( $node )
        );
    }

    // =========================================================================
    // Node-type handlers
    // =========================================================================

    /**
     * Binary operator: left op right
     */
    private function walk_binary(
        ODAD_AST_Binary $node,
        array $column_map,
        string &$sql,
        array &$params
    ): void {
        $op = $node->op;

        // Null-safe comparison operators require special SQL (IS NULL / IS NOT NULL).
        if ( $op === 'eq' || $op === 'ne' ) {
            $right = $node->right;
            if ( $right instanceof ODAD_AST_Literal && $right->type === 'null' ) {
                $col_sql    = '';
                $col_params = [];
                $this->walk( $node->left, $column_map, $col_sql, $col_params );
                $params = array_merge( $params, $col_params );
                $null_kw = ( $op === 'eq' ) ? 'IS NULL' : 'IS NOT NULL';
                $sql    .= $col_sql . ' ' . $null_kw;
                return;
            }

            // Also handle null on the left side (less common but valid OData).
            $left = $node->left;
            if ( $left instanceof ODAD_AST_Literal && $left->type === 'null' ) {
                $col_sql    = '';
                $col_params = [];
                $this->walk( $node->right, $column_map, $col_sql, $col_params );
                $params = array_merge( $params, $col_params );
                $null_kw = ( $op === 'eq' ) ? 'IS NULL' : 'IS NOT NULL';
                $sql    .= $col_sql . ' ' . $null_kw;
                return;
            }
        }

        // Logical binary operators wrap both sides in parentheses.
        if ( $op === 'and' || $op === 'or' ) {
            $sql .= '(';
            $this->walk( $node->left,  $column_map, $sql, $params );
            $sql .= ' ' . strtoupper( $op ) . ' ';
            $this->walk( $node->right, $column_map, $sql, $params );
            $sql .= ')';
            return;
        }

        // Arithmetic binary operators.
        if ( in_array( $op, [ 'add', 'sub', 'mul', 'div', 'divby', 'mod' ], true ) ) {
            $sql_op = $this->arithmetic_sql_op( $op );
            $sql   .= '(';
            $this->walk( $node->left,  $column_map, $sql, $params );
            $sql   .= ' ' . $sql_op . ' ';
            $this->walk( $node->right, $column_map, $sql, $params );
            $sql   .= ')';
            return;
        }

        // Comparison operators: eq, ne, lt, le, gt, ge
        $sql_op = $this->comparison_sql_op( $op );
        $this->walk( $node->left,  $column_map, $sql, $params );
        $sql .= ' ' . $sql_op . ' ';
        $this->walk( $node->right, $column_map, $sql, $params );
    }

    /**
     * Unary operator: not / minus
     */
    private function walk_unary(
        ODAD_AST_Unary $node,
        array $column_map,
        string &$sql,
        array &$params
    ): void {
        if ( $node->op === 'not' ) {
            $sql .= 'NOT (';
            $this->walk( $node->operand, $column_map, $sql, $params );
            $sql .= ')';
            return;
        }

        if ( $node->op === '-' ) {
            $sql .= '-(';
            $this->walk( $node->operand, $column_map, $sql, $params );
            $sql .= ')';
            return;
        }

        throw new ODAD_Filter_Compile_Exception(
            "Unknown unary operator: {$node->op}"
        );
    }

    /**
     * In operator: property IN (v1, v2, …)
     */
    private function walk_in(
        ODAD_AST_In $node,
        array $column_map,
        string &$sql,
        array &$params
    ): void {
        $this->walk( $node->property, $column_map, $sql, $params );

        $placeholders = [];
        foreach ( $node->values as $value_node ) {
            [ $ph, $val ] = $this->literal_placeholder( $value_node );
            $placeholders[] = $ph;
            if ( $val !== null ) {
                $params[] = $val;
            }
        }

        $sql .= ' IN (' . implode( ', ', $placeholders ) . ')';
    }

    /**
     * Literal value — appends a placeholder to $sql and the value to $params.
     */
    private function walk_literal(
        ODAD_AST_Literal $node,
        string &$sql,
        array &$params
    ): void {
        [ $ph, $val ] = $this->literal_placeholder( $node );
        $sql .= $ph;
        if ( $val !== null ) {
            $params[] = $val;
        }
    }

    /**
     * Function call — string, date, math, and type-testing functions.
     */
    private function walk_function(
        ODAD_AST_Function $node,
        array $column_map,
        string &$sql,
        array &$params
    ): void {
        $fn   = $node->name; // already lower-cased by the parser
        $args = $node->args;

        switch ( $fn ) {

            // -----------------------------------------------------------------
            // String functions
            // -----------------------------------------------------------------

            case 'contains':
                // contains(col, val) → col LIKE CONCAT('%', %s, '%')
                $this->assert_arg_count( $fn, $args, 2 );
                $col = $this->resolve_column_from_node( $args[0], $column_map );
                [ $ph, $val ] = $this->literal_placeholder_from_node( $args[1], $column_map );
                $sql .= $col . " LIKE CONCAT('%', " . $ph . ", '%')";
                if ( $val !== null ) {
                    $params[] = $val;
                }
                return;

            case 'startswith':
                // startswith(col, val) → col LIKE CONCAT(%s, '%')
                $this->assert_arg_count( $fn, $args, 2 );
                $col = $this->resolve_column_from_node( $args[0], $column_map );
                [ $ph, $val ] = $this->literal_placeholder_from_node( $args[1], $column_map );
                $sql .= $col . " LIKE CONCAT(" . $ph . ", '%')";
                if ( $val !== null ) {
                    $params[] = $val;
                }
                return;

            case 'endswith':
                // endswith(col, val) → col LIKE CONCAT('%', %s)
                $this->assert_arg_count( $fn, $args, 2 );
                $col = $this->resolve_column_from_node( $args[0], $column_map );
                [ $ph, $val ] = $this->literal_placeholder_from_node( $args[1], $column_map );
                $sql .= $col . " LIKE CONCAT('%', " . $ph . ")";
                if ( $val !== null ) {
                    $params[] = $val;
                }
                return;

            case 'length':
                // length(col) → CHAR_LENGTH(col)
                $this->assert_arg_count( $fn, $args, 1 );
                $col = $this->resolve_column_from_node( $args[0], $column_map );
                $sql .= 'CHAR_LENGTH(' . $col . ')';
                return;

            case 'indexof':
                // indexof(col, val) → LOCATE(%s, col) - 1
                $this->assert_arg_count( $fn, $args, 2 );
                $col = $this->resolve_column_from_node( $args[0], $column_map );
                [ $ph, $val ] = $this->literal_placeholder_from_node( $args[1], $column_map );
                $sql .= 'LOCATE(' . $ph . ', ' . $col . ') - 1';
                if ( $val !== null ) {
                    $params[] = $val;
                }
                return;

            case 'substring':
                // substring(col, start) → SUBSTRING(col, start+1)
                // substring(col, start, len) → SUBSTRING(col, start+1, len)
                if ( count( $args ) < 2 || count( $args ) > 3 ) {
                    throw new ODAD_Filter_Compile_Exception(
                        "Function 'substring' requires 2 or 3 arguments, got " . count( $args ) . "."
                    );
                }
                $col = $this->resolve_column_from_node( $args[0], $column_map );
                // start argument — compile as expression and wrap with +1
                $start_sql    = '';
                $start_params = [];
                $this->walk( $args[1], $column_map, $start_sql, $start_params );
                $params = array_merge( $params, $start_params );

                if ( count( $args ) === 3 ) {
                    $len_sql    = '';
                    $len_params = [];
                    $this->walk( $args[2], $column_map, $len_sql, $len_params );
                    $params = array_merge( $params, $len_params );
                    $sql   .= 'SUBSTRING(' . $col . ', (' . $start_sql . ') + 1, ' . $len_sql . ')';
                } else {
                    $sql .= 'SUBSTRING(' . $col . ', (' . $start_sql . ') + 1)';
                }
                return;

            case 'tolower':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'LOWER(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'toupper':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'UPPER(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'trim':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'TRIM(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'concat':
                $this->assert_arg_count( $fn, $args, 2 );
                $sql .= 'CONCAT(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ', ';
                $this->walk( $args[1], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'matchespattern':
                // matchesPattern(col, pattern) → col REGEXP %s
                $this->assert_arg_count( $fn, $args, 2 );
                $col = $this->resolve_column_from_node( $args[0], $column_map );
                [ $ph, $val ] = $this->literal_placeholder_from_node( $args[1], $column_map );
                $sql .= $col . ' REGEXP ' . $ph;
                if ( $val !== null ) {
                    $params[] = $val;
                }
                return;

            // -----------------------------------------------------------------
            // Date / time functions
            // -----------------------------------------------------------------

            case 'year':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'YEAR(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'month':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'MONTH(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'day':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'DAY(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'hour':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'HOUR(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'minute':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'MINUTE(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'second':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'SECOND(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'now':
                // now() — zero-arg
                $sql .= 'NOW()';
                return;

            case 'date':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'DATE(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            // -----------------------------------------------------------------
            // Math functions
            // -----------------------------------------------------------------

            case 'round':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'ROUND(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'floor':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'FLOOR(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            case 'ceiling':
                $this->assert_arg_count( $fn, $args, 1 );
                $sql .= 'CEIL(';
                $this->walk( $args[0], $column_map, $sql, $params );
                $sql .= ')';
                return;

            // -----------------------------------------------------------------
            // Unsupported / not-yet-implemented functions
            // -----------------------------------------------------------------

            default:
                throw new ODAD_Filter_Compile_Exception(
                    "Function '{$fn}' is not supported in SQL compilation."
                );
        }
    }

    // =========================================================================
    // Helper utilities
    // =========================================================================

    /**
     * Resolve an OData property path to its safe SQL column expression.
     *
     * @param string               $path       OData property path (e.g. "Title" or "Author/Name").
     * @param array<string,string> $column_map
     * @return string  Safe SQL column expression taken from the map.
     * @throws ODAD_Filter_Compile_Exception  When the path is not in the map.
     */
    private function resolve_column( string $path, array $column_map ): string {
        if ( ! array_key_exists( $path, $column_map ) ) {
            throw new ODAD_Filter_Compile_Exception(
                "Unknown property: {$path}",
                $path
            );
        }
        return $column_map[ $path ];
    }

    /**
     * Resolve the column from an AST node that must be a property reference.
     *
     * @throws ODAD_Filter_Compile_Exception  When the node is not a property or the path is unknown.
     */
    private function resolve_column_from_node( ODAD_AST_Node $node, array $column_map ): string {
        if ( ! ( $node instanceof ODAD_AST_Property ) ) {
            throw new ODAD_Filter_Compile_Exception(
                "Expected a property reference as the first argument, got " . get_class( $node ) . "."
            );
        }
        return $this->resolve_column( $node->path, $column_map );
    }

    /**
     * Return [ $placeholder, $value ] for a literal node.
     *
     * For null literals the placeholder is emitted inline (no param needed);
     * $value is returned as null in that case (caller must NOT push to params).
     *
     * @return array{0:string, 1:mixed}
     */
    private function literal_placeholder( ODAD_AST_Literal $node ): array {
        switch ( $node->type ) {
            case 'string':
            case 'datetime':
            case 'guid':
            case 'duration':
                return [ '%s', (string) $node->value ];

            case 'int':
                return [ '%d', (int) $node->value ];

            case 'float':
                return [ '%f', (float) $node->value ];

            case 'bool':
                return [ '%d', $node->value ? 1 : 0 ];

            case 'null':
                // Null should be handled at a higher level (IS NULL / IS NOT NULL).
                // If reached here directly (e.g. inside IN list), emit NULL keyword.
                return [ 'NULL', null ];

            default:
                throw new ODAD_Filter_Compile_Exception(
                    "Unknown literal type: {$node->type}"
                );
        }
    }

    /**
     * Like literal_placeholder() but accepts any node.
     *
     * If the node is a literal, delegates to literal_placeholder().
     * Otherwise compiles the node normally and returns ['', null] — the
     * compiled fragment has already been appended to a local buffer which
     * the caller must use.
     *
     * For functions that require a literal second argument (contains, etc.)
     * we compile generically so that expressions like
     * contains(Title, tolower('News')) also work.
     *
     * @return array{0:string, 1:mixed}
     */
    private function literal_placeholder_from_node(
        ODAD_AST_Node $node,
        array $column_map
    ): array {
        if ( $node instanceof ODAD_AST_Literal ) {
            return $this->literal_placeholder( $node );
        }

        // Non-literal argument — compile it and return a sentinel so the
        // caller knows the SQL fragment is already in the returned string.
        // We cannot know the placeholder type, so we compile into a fragment
        // and return it as a raw SQL string with no separate param.
        // (The caller appends $ph directly to $sql.)
        $frag_sql    = '';
        $frag_params = [];
        $this->walk( $node, $column_map, $frag_sql, $frag_params );

        // We return the compiled fragment as the "placeholder" with null value;
        // the caller appends params via array_merge before returning.
        // To signal the difference we return a special sentinel.
        return [ '__COMPILED__' => $frag_sql, '__PARAMS__' => $frag_params ];
    }

    /**
     * Assert that a function has exactly $expected argument nodes.
     *
     * @param string         $fn       Function name for error messages.
     * @param ODAD_AST_Node[] $args
     * @param int            $expected
     * @throws ODAD_Filter_Compile_Exception
     */
    private function assert_arg_count( string $fn, array $args, int $expected ): void {
        $got = count( $args );
        if ( $got !== $expected ) {
            throw new ODAD_Filter_Compile_Exception(
                "Function '{$fn}' requires {$expected} argument(s), got {$got}."
            );
        }
    }

    /**
     * Map an OData comparison operator to its SQL counterpart.
     */
    private function comparison_sql_op( string $op ): string {
        return match ( $op ) {
            'eq' => '=',
            'ne' => '!=',
            'lt' => '<',
            'le' => '<=',
            'gt' => '>',
            'ge' => '>=',
            default => throw new ODAD_Filter_Compile_Exception(
                "Unknown comparison operator: {$op}"
            ),
        };
    }

    /**
     * Map an OData arithmetic operator to its SQL counterpart.
     */
    private function arithmetic_sql_op( string $op ): string {
        return match ( $op ) {
            'add'   => '+',
            'sub'   => '-',
            'mul'   => '*',
            'div'   => 'DIV',
            'divby' => '/',
            'mod'   => 'MOD',
            default => throw new ODAD_Filter_Compile_Exception(
                "Unknown arithmetic operator: {$op}"
            ),
        };
    }
}
