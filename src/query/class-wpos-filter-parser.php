<?php
defined( 'ABSPATH' ) || exit;

/**
 * OData v4.01 $filter recursive-descent parser.
 *
 * Converts a raw OData $filter string into an AST whose root is a
 * WPOS_AST_Node subclass instance. No WordPress dependencies; pure PHP.
 *
 * Operator precedence (lowest → highest):
 *   1. or
 *   2. and
 *   3. not  (unary)
 *   4. eq, ne, lt, le, gt, ge  (comparison)
 *   5. in
 *   6. add, sub
 *   7. mul, div, divby, mod
 *   8. unary -
 *   9. function call / property / literal / (…)
 */
class WPOS_Filter_Parser {

    // -------------------------------------------------------------------------
    // Token type constants
    // -------------------------------------------------------------------------

    private const T_EOF          = 'EOF';
    private const T_IDENT        = 'IDENT';       // identifier / keyword
    private const T_INT          = 'INT';
    private const T_FLOAT        = 'FLOAT';
    private const T_STRING       = 'STRING';       // single-quoted string (decoded)
    private const T_DATETIME     = 'DATETIME';
    private const T_GUID         = 'GUID';
    private const T_DURATION     = 'DURATION';
    private const T_LPAREN       = '(';
    private const T_RPAREN       = ')';
    private const T_COMMA        = ',';
    private const T_SLASH        = '/';
    private const T_COLON        = ':';
    private const T_MINUS        = '-';

    // -------------------------------------------------------------------------
    // Known OData keywords (operators that look like identifiers)
    // -------------------------------------------------------------------------

    private const BINARY_COMPARISON_OPS = [ 'eq', 'ne', 'lt', 'le', 'gt', 'ge' ];
    private const BINARY_ADDITIVE_OPS   = [ 'add', 'sub' ];
    private const BINARY_MULTIPLICATIVE_OPS = [ 'mul', 'div', 'divby', 'mod' ];

    /** All recognised function names (lower-case). */
    private const KNOWN_FUNCTIONS = [
        // String
        'contains', 'startswith', 'endswith', 'length', 'indexof',
        'substring', 'tolower', 'toupper', 'trim', 'concat', 'matchespattern',
        // Date / time
        'year', 'month', 'day', 'hour', 'minute', 'second', 'fractionalseconds',
        'date', 'time', 'totaloffsetminutes', 'now', 'mindatetime', 'maxdatetime',
        // Math
        'round', 'floor', 'ceiling', 'isof', 'cast',
        // Collection
        'hassubset', 'hassubsequence',
    ];

    /** Lambda operators. */
    private const LAMBDA_OPERATORS = [ 'any', 'all' ];

    // -------------------------------------------------------------------------
    // Internal state
    // -------------------------------------------------------------------------

    /** @var string The raw filter expression being parsed. */
    private string $expr = '';

    /** @var int Current read position in $expr. */
    private int $pos = 0;

    /**
     * Token buffer: each token is [ type, value, offset ].
     * type  — one of the T_* constants above
     * value — decoded PHP value for the token
     * offset — byte position of the first character of the token
     *
     * @var array<array{0:string,1:mixed,2:int}>
     */
    private array $tokens = [];

    /** @var int Index into $tokens pointing at the current token. */
    private int $tok_pos = 0;

    /** @var int Current parenthesis nesting depth (reset on each parse() call). */
    private int $depth = 0;

    /** Maximum allowed parenthesis nesting depth to prevent DoS. */
    private const MAX_DEPTH = 20;

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Parse an OData $filter expression and return its AST root node.
     *
     * @param string $filter_expression  Raw OData $filter string.
     * @return WPOS_AST_Node
     * @throws WPOS_Filter_Parse_Exception
     */
    public function parse( string $filter_expression ): WPOS_AST_Node {
        $this->expr    = $filter_expression;
        $this->pos     = 0;
        $this->tokens  = [];
        $this->tok_pos = 0;
        $this->depth   = 0;

        $this->tokenize();

        $node = $this->parse_or();

        if ( $this->current_type() !== self::T_EOF ) {
            $tok = $this->current_token();
            $this->error( "Unexpected token '{$tok[1]}' after end of expression", $tok[2] );
        }

        return $node;
    }

    // =========================================================================
    // Tokenizer
    // =========================================================================

    /**
     * Scan the entire expression into $this->tokens.
     *
     * Tokens are stored as [ type, value, offset ] triples.
     */
    private function tokenize(): void {
        $src = $this->expr;
        $len = strlen( $src );
        $i   = 0;

        while ( $i < $len ) {
            // Skip whitespace
            if ( $src[ $i ] === ' ' || $src[ $i ] === "\t" || $src[ $i ] === "\r" || $src[ $i ] === "\n" ) {
                $i++;
                continue;
            }

            $start = $i;
            $c     = $src[ $i ];

            // Single-character punctuation
            switch ( $c ) {
                case '(':
                    $this->tokens[] = [ self::T_LPAREN, '(', $start ];
                    $i++;
                    continue 2;
                case ')':
                    $this->tokens[] = [ self::T_RPAREN, ')', $start ];
                    $i++;
                    continue 2;
                case ',':
                    $this->tokens[] = [ self::T_COMMA, ',', $start ];
                    $i++;
                    continue 2;
                case '/':
                    $this->tokens[] = [ self::T_SLASH, '/', $start ];
                    $i++;
                    continue 2;
                case ':':
                    $this->tokens[] = [ self::T_COLON, ':', $start ];
                    $i++;
                    continue 2;
            }

            // Minus/dash — either unary minus, subtraction, or part of a
            // datetime/duration literal.  Emit as T_MINUS; the parser will
            // decide context.  (DateTimeOffset and GUID patterns that contain
            // dashes are handled in the identifier/number branches below.)
            if ( $c === '-' ) {
                // Check for negative number
                if ( $i + 1 < $len && ( ctype_digit( $src[ $i + 1 ] ) ) ) {
                    // Could be a negative number literal — read number starting
                    // from the sign.  We only do this if the previous token is
                    // not a value-producing token (identifier, literal, rparen).
                    // We emit T_MINUS and let parse_unary handle it so that
                    // subtraction (e.g. a - b) is properly parsed as binary.
                }
                $this->tokens[] = [ self::T_MINUS, '-', $start ];
                $i++;
                continue;
            }

            // Single-quoted string
            if ( $c === '\'' ) {
                [ $decoded, $consumed ] = $this->lex_string( $src, $i );
                $this->tokens[] = [ self::T_STRING, $decoded, $start ];
                $i += $consumed;
                continue;
            }

            // Number: integer or float
            // Also handles datetimes that start with a digit (ISO 8601):
            //   2024-01-15T10:30:00Z
            if ( ctype_digit( $c ) ) {
                [ $type, $value, $consumed ] = $this->lex_number_or_datetime( $src, $i );
                $this->tokens[] = [ $type, $value, $start ];
                $i += $consumed;
                continue;
            }

            // Identifier / keyword / boolean / null / guid'…' / duration'…'
            if ( ctype_alpha( $c ) || $c === '_' ) {
                [ $type, $value, $consumed ] = $this->lex_ident_or_keyword( $src, $i );
                $this->tokens[] = [ $type, $value, $start ];
                $i += $consumed;
                continue;
            }

            // Unknown character
            $this->error( "Unexpected character '{$c}'", $i );
        }

        $this->tokens[] = [ self::T_EOF, '', $len ];
    }

    // -------------------------------------------------------------------------
    // Lexer helpers
    // -------------------------------------------------------------------------

    /**
     * Lex a single-quoted OData string literal starting at $pos.
     * Returns [ $decoded_value, $chars_consumed ].
     * OData escaping: '' → ' (doubled single quotes).
     *
     * @return array{0:string,1:int}
     */
    private function lex_string( string $src, int $pos ): array {
        $len     = strlen( $src );
        $i       = $pos + 1; // skip opening quote
        $decoded = '';

        while ( $i < $len ) {
            if ( $src[ $i ] === '\'' ) {
                // Check for escaped quote ''
                if ( $i + 1 < $len && $src[ $i + 1 ] === '\'' ) {
                    $decoded .= '\'';
                    $i       += 2;
                } else {
                    $i++; // consume closing quote
                    break;
                }
            } else {
                $decoded .= $src[ $i ];
                $i++;
            }
        }

        return [ $decoded, $i - $pos ];
    }

    /**
     * Lex a number (integer or float) or an ISO 8601 datetime starting at $pos.
     *
     * ISO 8601 form handled: YYYY-MM-DDThh:mm:ss[.fff][Z|+hh:mm|-hh:mm]
     * We detect it by seeing 4 digits followed by a dash.
     *
     * @return array{0:string,1:mixed,2:int}
     */
    private function lex_number_or_datetime( string $src, int $pos ): array {
        $len = strlen( $src );
        $i   = $pos;

        // Consume leading digits
        while ( $i < $len && ctype_digit( $src[ $i ] ) ) {
            $i++;
        }

        $digit_str = substr( $src, $pos, $i - $pos );

        // Detect ISO 8601 datetime: 4-digit year immediately followed by '-'
        if ( strlen( $digit_str ) === 4 && $i < $len && $src[ $i ] === '-' ) {
            // Consume the rest of the datetime: date portion
            // Expected: YYYY-MM-DD
            if ( $i + 6 < $len
                && $src[ $i ] === '-'
                && ctype_digit( $src[ $i + 1 ] ?? '' )
                && ctype_digit( $src[ $i + 2 ] ?? '' )
                && $src[ $i + 3 ] === '-'
                && ctype_digit( $src[ $i + 4 ] ?? '' )
                && ctype_digit( $src[ $i + 5 ] ?? '' )
            ) {
                $i += 6; // consume -MM-DD
                // Optionally consume time portion: Thh:mm:ss...
                if ( $i < $len && ( $src[ $i ] === 'T' || $src[ $i ] === 't' ) ) {
                    $i++; // T
                    // hh:mm:ss
                    while ( $i < $len && ( ctype_digit( $src[ $i ] ) || $src[ $i ] === ':' ) ) {
                        $i++;
                    }
                    // Optional fractional seconds
                    if ( $i < $len && $src[ $i ] === '.' ) {
                        $i++;
                        while ( $i < $len && ctype_digit( $src[ $i ] ) ) {
                            $i++;
                        }
                    }
                    // Optional timezone Z or ±hh:mm
                    if ( $i < $len && ( $src[ $i ] === 'Z' || $src[ $i ] === 'z' ) ) {
                        $i++;
                    } elseif ( $i < $len && ( $src[ $i ] === '+' || $src[ $i ] === '-' ) ) {
                        $i++;
                        while ( $i < $len && ( ctype_digit( $src[ $i ] ) || $src[ $i ] === ':' ) ) {
                            $i++;
                        }
                    }
                }
                $dt_str = substr( $src, $pos, $i - $pos );
                return [ self::T_DATETIME, $dt_str, $i - $pos ];
            }
        }

        $is_float = false;

        // Decimal point
        if ( $i < $len && $src[ $i ] === '.' ) {
            $is_float = true;
            $i++;
            while ( $i < $len && ctype_digit( $src[ $i ] ) ) {
                $i++;
            }
        }

        // Optional exponent (e.g. 1.5e10)
        if ( $i < $len && ( $src[ $i ] === 'e' || $src[ $i ] === 'E' ) ) {
            $is_float = true;
            $i++;
            if ( $i < $len && ( $src[ $i ] === '+' || $src[ $i ] === '-' ) ) {
                $i++;
            }
            while ( $i < $len && ctype_digit( $src[ $i ] ) ) {
                $i++;
            }
        }

        $raw = substr( $src, $pos, $i - $pos );

        if ( $is_float ) {
            return [ self::T_FLOAT, (float) $raw, $i - $pos ];
        }

        return [ self::T_INT, (int) $raw, $i - $pos ];
    }

    /**
     * Lex an identifier, keyword, boolean, null, guid'…', or duration'…'.
     *
     * @return array{0:string,1:mixed,2:int}
     */
    private function lex_ident_or_keyword( string $src, int $pos ): array {
        $len = strlen( $src );
        $i   = $pos;

        while ( $i < $len && ( ctype_alnum( $src[ $i ] ) || $src[ $i ] === '_' ) ) {
            $i++;
        }

        $word = substr( $src, $pos, $i - $pos );

        // guid'...'
        if ( strtolower( $word ) === 'guid' && $i < $len && $src[ $i ] === '\'' ) {
            [ $inner, $consumed ] = $this->lex_string( $src, $i );
            return [ self::T_GUID, $inner, ( $i - $pos ) + $consumed ];
        }

        // duration'...'
        if ( strtolower( $word ) === 'duration' && $i < $len && $src[ $i ] === '\'' ) {
            [ $inner, $consumed ] = $this->lex_string( $src, $i );
            return [ self::T_DURATION, $inner, ( $i - $pos ) + $consumed ];
        }

        // boolean literals
        $lower = strtolower( $word );
        if ( $lower === 'true' )  return [ self::T_IDENT, 'true',  $i - $pos ];
        if ( $lower === 'false' ) return [ self::T_IDENT, 'false', $i - $pos ];
        if ( $lower === 'null' )  return [ self::T_IDENT, 'null',  $i - $pos ];

        // Everything else is an identifier (property name, operator keyword,
        // function name…).  We preserve the original casing so that property
        // names round-trip correctly; comparisons against keyword lists always
        // use lower-case.
        return [ self::T_IDENT, $word, $i - $pos ];
    }

    // =========================================================================
    // Token stream helpers
    // =========================================================================

    /** Return the current token triple. */
    private function current_token(): array {
        return $this->tokens[ $this->tok_pos ];
    }

    /** Return the type of the current token. */
    private function current_type(): string {
        return $this->tokens[ $this->tok_pos ][0];
    }

    /** Return the value of the current token. */
    private function current_value(): mixed {
        return $this->tokens[ $this->tok_pos ][1];
    }

    /** Return the offset of the current token. */
    private function current_offset(): int {
        return $this->tokens[ $this->tok_pos ][2];
    }

    /** Peek at the next token without advancing. */
    private function peek_token(): array {
        $next = $this->tok_pos + 1;
        return $this->tokens[ $next ] ?? [ self::T_EOF, '', strlen( $this->expr ) ];
    }

    /** Peek at the type of the next token. */
    private function peek_type(): string {
        return $this->peek_token()[0];
    }

    /** Peek at the value of the next token. */
    private function peek_value(): mixed {
        return $this->peek_token()[1];
    }

    /**
     * Advance past the current token and return it.
     */
    private function consume(): array {
        $tok = $this->tokens[ $this->tok_pos ];
        if ( $tok[0] !== self::T_EOF ) {
            $this->tok_pos++;
        }
        return $tok;
    }

    /**
     * Consume and return the current token if it matches $type; otherwise throw.
     */
    private function expect( string $type ): array {
        if ( $this->current_type() !== $type ) {
            $got = $this->current_value();
            $this->error( "Expected '{$type}' but got '{$got}'", $this->current_offset() );
        }
        return $this->consume();
    }

    /**
     * Return true (without consuming) if the current token is an identifier
     * whose lower-cased value equals $keyword.
     */
    private function is_keyword( string $keyword ): bool {
        return $this->current_type() === self::T_IDENT
            && strtolower( (string) $this->current_value() ) === $keyword;
    }

    /**
     * Return true (without consuming) if the current identifier value
     * (lower-cased) is in $list.
     */
    private function is_keyword_in( array $list ): bool {
        if ( $this->current_type() !== self::T_IDENT ) {
            return false;
        }
        return in_array( strtolower( (string) $this->current_value() ), $list, true );
    }

    // =========================================================================
    // Error helper
    // =========================================================================

    /**
     * Throw a WPOS_Filter_Parse_Exception.
     *
     * @param string $message
     * @param int    $offset   Byte offset in the expression.
     * @return never
     */
    private function error( string $message, int $offset ): never {
        throw new WPOS_Filter_Parse_Exception(
            $message . " (at position {$offset} in: {$this->expr})",
            $offset,
            $this->expr
        );
    }

    // =========================================================================
    // Recursive descent grammar
    //
    // parse_or          → parse_and  ('or'  parse_and)*
    // parse_and         → parse_not  ('and' parse_not)*
    // parse_not         → 'not' parse_not | parse_comparison
    // parse_comparison  → parse_in   (CMP_OP parse_in)?
    // parse_in          → parse_additive ('in' '(' value_list ')')?
    // parse_additive    → parse_multiplicative (('+' | 'add' | '-' | 'sub') parse_multiplicative)*
    // parse_multiplicative → parse_unary (('mul'|'div'|'divby'|'mod') parse_unary)*
    // parse_unary       → '-' parse_unary | parse_primary
    // parse_primary     → func_call | lambda | literal | property | '(' parse_or ')'
    // =========================================================================

    // -------------------------------------------------------------------------
    // Level 1 — or
    // -------------------------------------------------------------------------

    private function parse_or(): WPOS_AST_Node {
        $left = $this->parse_and();

        while ( $this->is_keyword( 'or' ) ) {
            $this->consume();
            $right = $this->parse_and();
            $left  = new WPOS_AST_Binary( 'or', $left, $right );
        }

        return $left;
    }

    // -------------------------------------------------------------------------
    // Level 2 — and
    // -------------------------------------------------------------------------

    private function parse_and(): WPOS_AST_Node {
        $left = $this->parse_not();

        while ( $this->is_keyword( 'and' ) ) {
            $this->consume();
            $right = $this->parse_not();
            $left  = new WPOS_AST_Binary( 'and', $left, $right );
        }

        return $left;
    }

    // -------------------------------------------------------------------------
    // Level 3 — not (unary logical)
    // -------------------------------------------------------------------------

    private function parse_not(): WPOS_AST_Node {
        if ( $this->is_keyword( 'not' ) ) {
            $this->consume();
            $operand = $this->parse_not();
            return new WPOS_AST_Unary( 'not', $operand );
        }

        return $this->parse_comparison();
    }

    // -------------------------------------------------------------------------
    // Level 4 — comparison: eq, ne, lt, le, gt, ge
    // -------------------------------------------------------------------------

    private function parse_comparison(): WPOS_AST_Node {
        $left = $this->parse_in();

        if ( $this->is_keyword_in( self::BINARY_COMPARISON_OPS ) ) {
            $op    = strtolower( (string) $this->current_value() );
            $this->consume();
            $right = $this->parse_in();
            return new WPOS_AST_Binary( $op, $left, $right );
        }

        return $left;
    }

    // -------------------------------------------------------------------------
    // Level 5 — in operator: expr 'in' '(' value, value, ... ')'
    // -------------------------------------------------------------------------

    private function parse_in(): WPOS_AST_Node {
        $left = $this->parse_additive();

        if ( $this->is_keyword( 'in' ) ) {
            $this->consume(); // consume 'in'
            $this->expect( self::T_LPAREN );

            $values = [];
            // Allow empty list (edge case) and trailing commas are not valid OData
            // but we skip gracefully.
            while ( $this->current_type() !== self::T_RPAREN
                    && $this->current_type() !== self::T_EOF
            ) {
                $values[] = $this->parse_literal();
                if ( $this->current_type() === self::T_COMMA ) {
                    $this->consume();
                }
            }

            $this->expect( self::T_RPAREN );
            return new WPOS_AST_In( $left, $values );
        }

        return $left;
    }

    // -------------------------------------------------------------------------
    // Level 6 — additive: add, sub
    // -------------------------------------------------------------------------

    private function parse_additive(): WPOS_AST_Node {
        $left = $this->parse_multiplicative();

        while ( $this->is_keyword_in( self::BINARY_ADDITIVE_OPS )
             || $this->current_type() === self::T_MINUS
        ) {
            if ( $this->current_type() === self::T_MINUS ) {
                $op = 'sub';
            } else {
                $op = strtolower( (string) $this->current_value() );
            }
            $this->consume();
            $right = $this->parse_multiplicative();
            $left  = new WPOS_AST_Binary( $op, $left, $right );
        }

        return $left;
    }

    // -------------------------------------------------------------------------
    // Level 7 — multiplicative: mul, div, divby, mod
    // -------------------------------------------------------------------------

    private function parse_multiplicative(): WPOS_AST_Node {
        $left = $this->parse_unary();

        while ( $this->is_keyword_in( self::BINARY_MULTIPLICATIVE_OPS ) ) {
            $op = strtolower( (string) $this->current_value() );
            $this->consume();
            $right = $this->parse_unary();
            $left  = new WPOS_AST_Binary( $op, $left, $right );
        }

        return $left;
    }

    // -------------------------------------------------------------------------
    // Level 8 — unary minus
    // -------------------------------------------------------------------------

    private function parse_unary(): WPOS_AST_Node {
        if ( $this->current_type() === self::T_MINUS ) {
            $this->consume();
            $operand = $this->parse_unary();
            return new WPOS_AST_Unary( '-', $operand );
        }

        return $this->parse_primary();
    }

    // -------------------------------------------------------------------------
    // Level 9 — primary: literal, property, function, lambda, '(' expr ')'
    // -------------------------------------------------------------------------

    private function parse_primary(): WPOS_AST_Node {
        $type  = $this->current_type();
        $value = $this->current_value();

        // Parenthesised sub-expression
        if ( $type === self::T_LPAREN ) {
            $this->depth++;
            if ( $this->depth > self::MAX_DEPTH ) {
                $this->error( 'Filter expression exceeds maximum nesting depth of ' . self::MAX_DEPTH, $this->current_offset() );
            }
            $this->consume();
            $node = $this->parse_or();
            $this->expect( self::T_RPAREN );
            $this->depth--;
            return $node;
        }

        // Numeric / string / typed literals
        if ( $type === self::T_INT ) {
            $this->consume();
            return new WPOS_AST_Literal( 'int', $value );
        }
        if ( $type === self::T_FLOAT ) {
            $this->consume();
            return new WPOS_AST_Literal( 'float', $value );
        }
        if ( $type === self::T_STRING ) {
            $this->consume();
            return new WPOS_AST_Literal( 'string', $value );
        }
        if ( $type === self::T_DATETIME ) {
            $this->consume();
            return new WPOS_AST_Literal( 'datetime', $value );
        }
        if ( $type === self::T_GUID ) {
            $this->consume();
            return new WPOS_AST_Literal( 'guid', $value );
        }
        if ( $type === self::T_DURATION ) {
            $this->consume();
            return new WPOS_AST_Literal( 'duration', $value );
        }

        // Identifier — could be: boolean/null literal, function, lambda, or property
        if ( $type === self::T_IDENT ) {
            $lower = strtolower( (string) $value );

            // Boolean / null literals
            if ( $lower === 'true' ) {
                $this->consume();
                return new WPOS_AST_Literal( 'bool', true );
            }
            if ( $lower === 'false' ) {
                $this->consume();
                return new WPOS_AST_Literal( 'bool', false );
            }
            if ( $lower === 'null' ) {
                $this->consume();
                return new WPOS_AST_Literal( 'null', null );
            }

            // Function call? An identifier followed immediately by '(' is a function.
            // Also handles lambda operators (any/all) which have a special syntax when
            // invoked as a navigation-path method (parsed at property level).
            if ( $this->peek_type() === self::T_LPAREN ) {
                return $this->parse_function_call();
            }

            // case(…) — stub that signals an unsupported feature
            if ( $lower === 'case' ) {
                $this->error( "The 'case' conditional expression is not supported in this version", $this->current_offset() );
            }

            // Property path (possibly navigation: Prop1/Prop2) or lambda invocation
            // in the form Collection/any(v:expr).
            return $this->parse_property_or_lambda();
        }

        $this->error( "Unexpected token '{$value}' (type {$type})", $this->current_offset() );
    }

    // -------------------------------------------------------------------------
    // Function call parser: name '(' arg, arg, … ')'
    //
    // Handles now() and mindatetime() / maxdatetime() as zero-arg functions.
    // Lambda operators (any/all) at the top level (not path-qualified) are
    // also treated as function calls here.
    // -------------------------------------------------------------------------

    private function parse_function_call(): WPOS_AST_Node {
        $name_tok = $this->expect( self::T_IDENT );
        $name     = (string) $name_tok[1];
        $lower    = strtolower( $name );
        $offset   = $name_tok[2];

        // Validate function name (allow lambda operators too)
        if ( ! in_array( $lower, self::KNOWN_FUNCTIONS, true )
             && ! in_array( $lower, self::LAMBDA_OPERATORS, true )
        ) {
            $this->error( "Unknown function '{$name}'", $offset );
        }

        $this->expect( self::T_LPAREN );

        // Lambda: any(v:expr) or all(v:expr) — standalone form
        if ( in_array( $lower, self::LAMBDA_OPERATORS, true ) ) {
            return $this->parse_lambda_body( $lower, new WPOS_AST_Literal( 'null', null ) );
        }

        $args = $this->parse_argument_list();

        $this->expect( self::T_RPAREN );

        return new WPOS_AST_Function( $lower, $args );
    }

    /**
     * Parse a comma-separated argument list (without the surrounding parens).
     *
     * @return WPOS_AST_Node[]
     */
    private function parse_argument_list(): array {
        $args = [];

        if ( $this->current_type() === self::T_RPAREN ) {
            return $args; // zero-argument function
        }

        $args[] = $this->parse_or();

        while ( $this->current_type() === self::T_COMMA ) {
            $this->consume();
            $args[] = $this->parse_or();
        }

        return $args;
    }

    // -------------------------------------------------------------------------
    // Property path parser
    //
    // Parses: Segment ( '/' Segment )* [ '/' lambda_op '(' lambda_body ')' ]
    //
    // where lambda_op is 'any' or 'all'.
    // -------------------------------------------------------------------------

    private function parse_property_or_lambda(): WPOS_AST_Node {
        $offset = $this->current_offset();
        $path   = $this->parse_path_segment();

        // Continue collecting path segments separated by '/'
        // Stop if the next segment is a lambda operator followed by '('
        while ( $this->current_type() === self::T_SLASH ) {
            $next_tok = $this->peek_token();

            // Next token after '/' — check if it's a lambda operator
            if ( $next_tok[0] === self::T_IDENT ) {
                $next_lower = strtolower( (string) $next_tok[1] );

                // Look two tokens ahead: if token after the identifier is '('
                // then this is a lambda invocation.
                $tok_after = $this->tokens[ $this->tok_pos + 2 ] ?? [ self::T_EOF, '', -1 ];

                if ( in_array( $next_lower, self::LAMBDA_OPERATORS, true )
                     && $tok_after[0] === self::T_LPAREN
                ) {
                    // Consume '/', lambda_op, '('
                    $this->consume(); // '/'
                    $lambda_op = strtolower( (string) $this->current_value() );
                    $this->consume(); // lambda op
                    $this->consume(); // '('

                    $collection = new WPOS_AST_Property( $path );
                    return $this->parse_lambda_body( $lambda_op, $collection );
                }
            }

            // Regular navigation path segment
            $this->consume(); // '/'

            if ( $this->current_type() !== self::T_IDENT ) {
                $this->error( "Expected property name after '/'", $this->current_offset() );
            }
            $path .= '/' . $this->current_value();
            $this->consume();
        }

        return new WPOS_AST_Property( $path );
    }

    /**
     * Parse the first (and only) segment of a property path.
     * The caller is responsible for collecting subsequent '/'-separated segments.
     */
    private function parse_path_segment(): string {
        $tok = $this->expect( self::T_IDENT );
        return (string) $tok[1];
    }

    // -------------------------------------------------------------------------
    // Lambda body parser
    //
    // After '(' has been consumed, parses: variable ':' expression ')'
    // The $collection node is the left-hand side already parsed.
    // -------------------------------------------------------------------------

    /**
     * @param string        $operator   'any' or 'all'
     * @param WPOS_AST_Node $collection Collection expression (may be a dummy
     *                                   Literal(null) for top-level lambdas).
     */
    private function parse_lambda_body( string $operator, WPOS_AST_Node $collection ): WPOS_AST_Node {
        // Variable identifier
        $var_tok  = $this->expect( self::T_IDENT );
        $variable = (string) $var_tok[1];

        $this->expect( self::T_COLON );

        $expr = $this->parse_or();

        $this->expect( self::T_RPAREN );

        return new WPOS_AST_Lambda( $operator, $collection, $variable, $expr );
    }

    // -------------------------------------------------------------------------
    // Literal-only parser (used inside 'in' value lists)
    // -------------------------------------------------------------------------

    /**
     * Parse a single literal value.  Only literals are valid inside an 'in' list.
     */
    private function parse_literal(): WPOS_AST_Node {
        $type  = $this->current_type();
        $value = $this->current_value();

        switch ( $type ) {
            case self::T_INT:
                $this->consume();
                return new WPOS_AST_Literal( 'int', $value );

            case self::T_FLOAT:
                $this->consume();
                return new WPOS_AST_Literal( 'float', $value );

            case self::T_STRING:
                $this->consume();
                return new WPOS_AST_Literal( 'string', $value );

            case self::T_DATETIME:
                $this->consume();
                return new WPOS_AST_Literal( 'datetime', $value );

            case self::T_GUID:
                $this->consume();
                return new WPOS_AST_Literal( 'guid', $value );

            case self::T_DURATION:
                $this->consume();
                return new WPOS_AST_Literal( 'duration', $value );

            case self::T_MINUS:
                // Negative numeric literal
                $this->consume();
                $inner = $this->parse_literal();
                if ( $inner instanceof WPOS_AST_Literal && $inner->type === 'int' ) {
                    return new WPOS_AST_Literal( 'int', -(int) $inner->value );
                }
                if ( $inner instanceof WPOS_AST_Literal && $inner->type === 'float' ) {
                    return new WPOS_AST_Literal( 'float', -(float) $inner->value );
                }
                $this->error( "Expected numeric literal after '-'", $this->current_offset() );

            case self::T_IDENT:
                $lower = strtolower( (string) $value );
                if ( $lower === 'true' ) {
                    $this->consume();
                    return new WPOS_AST_Literal( 'bool', true );
                }
                if ( $lower === 'false' ) {
                    $this->consume();
                    return new WPOS_AST_Literal( 'bool', false );
                }
                if ( $lower === 'null' ) {
                    $this->consume();
                    return new WPOS_AST_Literal( 'null', null );
                }
                $this->error( "Expected a literal value but got identifier '{$value}'", $this->current_offset() );

            default:
                $this->error( "Expected a literal value but got '{$value}' (type {$type})", $this->current_offset() );
        }
    }
}
