<?php
defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for all AST nodes produced by the OData $filter parser.
 */
abstract class WPOS_AST_Node {}

/**
 * Binary operator node: $left op $right
 *
 * @property string        $op    One of: eq, ne, lt, le, gt, ge, and, or,
 *                                add, sub, mul, div, divby, mod
 * @property WPOS_AST_Node $left
 * @property WPOS_AST_Node $right
 */
class WPOS_AST_Binary extends WPOS_AST_Node {
    public function __construct(
        public readonly string       $op,
        public readonly WPOS_AST_Node $left,
        public readonly WPOS_AST_Node $right,
    ) {}
}

/**
 * Unary operator node: op $operand
 *
 * @property string        $op      One of: not, -
 * @property WPOS_AST_Node $operand
 */
class WPOS_AST_Unary extends WPOS_AST_Node {
    public function __construct(
        public readonly string        $op,
        public readonly WPOS_AST_Node $operand,
    ) {}
}

/**
 * Property path reference, e.g. Title or Author/Name (navigation path).
 *
 * @property string $path  Slash-separated property path.
 */
class WPOS_AST_Property extends WPOS_AST_Node {
    public function __construct(
        public readonly string $path,
    ) {}
}

/**
 * Literal value node.
 *
 * @property string $type  One of: string, int, float, bool, null, datetime, guid, duration
 * @property mixed  $value The PHP value (int, float, string, bool, or null).
 */
class WPOS_AST_Literal extends WPOS_AST_Node {
    public function __construct(
        public readonly string $type,
        public readonly mixed  $value,
    ) {}
}

/**
 * Function call node: name(arg1, arg2, …)
 *
 * @property string          $name Function name (lower-cased OData canonical name).
 * @property WPOS_AST_Node[] $args Argument nodes.
 */
class WPOS_AST_Function extends WPOS_AST_Node {
    public function __construct(
        public readonly string $name,
        public readonly array  $args,
    ) {}
}

/**
 * `in` membership test: property in (v1, v2, …)
 *
 * @property WPOS_AST_Node    $property  Left-hand side (usually a property path).
 * @property WPOS_AST_Literal[] $values  Right-hand side literal list.
 */
class WPOS_AST_In extends WPOS_AST_Node {
    public function __construct(
        public readonly WPOS_AST_Node $property,
        public readonly array          $values,
    ) {}
}

/**
 * Lambda operator node: collection/any(v:expr) or collection/all(v:expr)
 *
 * @property string        $operator   'any' or 'all'
 * @property WPOS_AST_Node $collection The collection expression (property path).
 * @property string        $variable   Lambda variable identifier, e.g. 'd'.
 * @property WPOS_AST_Node $expression Body expression.
 */
class WPOS_AST_Lambda extends WPOS_AST_Node {
    public function __construct(
        public readonly string        $operator,
        public readonly WPOS_AST_Node $collection,
        public readonly string        $variable,
        public readonly WPOS_AST_Node $expression,
    ) {}
}
