<?php
defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for all AST nodes produced by the OData $filter parser.
 */
abstract class ODAD_AST_Node {}

/**
 * Binary operator node: $left op $right
 *
 * @property string        $op    One of: eq, ne, lt, le, gt, ge, and, or,
 *                                add, sub, mul, div, divby, mod
 * @property ODAD_AST_Node $left
 * @property ODAD_AST_Node $right
 */
class ODAD_AST_Binary extends ODAD_AST_Node {
    public function __construct(
        public readonly string       $op,
        public readonly ODAD_AST_Node $left,
        public readonly ODAD_AST_Node $right,
    ) {}
}

/**
 * Unary operator node: op $operand
 *
 * @property string        $op      One of: not, -
 * @property ODAD_AST_Node $operand
 */
class ODAD_AST_Unary extends ODAD_AST_Node {
    public function __construct(
        public readonly string        $op,
        public readonly ODAD_AST_Node $operand,
    ) {}
}

/**
 * Property path reference, e.g. Title or Author/Name (navigation path).
 *
 * @property string $path  Slash-separated property path.
 */
class ODAD_AST_Property extends ODAD_AST_Node {
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
class ODAD_AST_Literal extends ODAD_AST_Node {
    public function __construct(
        public readonly string $type,
        public readonly mixed  $value,
    ) {}
}

/**
 * Function call node: name(arg1, arg2, …)
 *
 * @property string          $name Function name (lower-cased OData canonical name).
 * @property ODAD_AST_Node[] $args Argument nodes.
 */
class ODAD_AST_Function extends ODAD_AST_Node {
    public function __construct(
        public readonly string $name,
        public readonly array  $args,
    ) {}
}

/**
 * `in` membership test: property in (v1, v2, …)
 *
 * @property ODAD_AST_Node    $property  Left-hand side (usually a property path).
 * @property ODAD_AST_Literal[] $values  Right-hand side literal list.
 */
class ODAD_AST_In extends ODAD_AST_Node {
    public function __construct(
        public readonly ODAD_AST_Node $property,
        public readonly array          $values,
    ) {}
}

/**
 * Lambda operator node: collection/any(v:expr) or collection/all(v:expr)
 *
 * @property string        $operator   'any' or 'all'
 * @property ODAD_AST_Node $collection The collection expression (property path).
 * @property string        $variable   Lambda variable identifier, e.g. 'd'.
 * @property ODAD_AST_Node $expression Body expression.
 */
class ODAD_AST_Lambda extends ODAD_AST_Node {
    public function __construct(
        public readonly string        $operator,
        public readonly ODAD_AST_Node $collection,
        public readonly string        $variable,
        public readonly ODAD_AST_Node $expression,
    ) {}
}
