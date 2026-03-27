# Task 3.1 — OData $filter Tokenizer + Recursive Descent AST Parser

## Dependencies
- All Phase 1 tasks (no specific Phase 2 dependency)

## Goal
Build a custom recursive descent parser for OData v4.01 `$filter` expressions.
Output is an AST (abstract syntax tree) of node objects. This is pure PHP with
no WordPress dependencies.

**This is the most complex single task in the project. Budget accordingly.**

---

## Files to Create

### `src/query/class-odad-filter-parser.php`

The parser takes a raw `$filter` string and returns an AST root node.

```php
class ODAD_Filter_Parser {
    public function parse( string $filter_expression ): ODAD_AST_Node;
}
```

---

## AST Node Types

Create a file `src/query/class-odad-ast-node.php` with all node classes:

```php
abstract class ODAD_AST_Node {}

/** Binary operator: $left op $right */
class ODAD_AST_Binary extends ODAD_AST_Node {
    public function __construct(
        public string       $op,    // 'eq','ne','lt','le','gt','ge','and','or','add','sub','mul','div','divby','mod'
        public ODAD_AST_Node $left,
        public ODAD_AST_Node $right,
    ) {}
}

/** Unary operator: op $operand */
class ODAD_AST_Unary extends ODAD_AST_Node {
    public function __construct(
        public string        $op,       // 'not', '-'
        public ODAD_AST_Node $operand,
    ) {}
}

/** Property reference: e.g. Title, Author/Name */
class ODAD_AST_Property extends ODAD_AST_Node {
    public function __construct(
        public string $path,    // 'Title' or 'Author/Name' (nav path)
    ) {}
}

/** Literal value */
class ODAD_AST_Literal extends ODAD_AST_Node {
    public function __construct(
        public string $type,    // 'string'|'int'|'float'|'bool'|'null'|'datetime'|'guid'|'duration'
        public mixed  $value,
    ) {}
}

/** Function call: name(arg1, arg2, ...) */
class ODAD_AST_Function extends ODAD_AST_Node {
    public function __construct(
        public string $name,
        public array  $args,    // ODAD_AST_Node[]
    ) {}
}

/** `in` operator: property in (v1, v2, ...) */
class ODAD_AST_In extends ODAD_AST_Node {
    public function __construct(
        public ODAD_AST_Node $property,
        public array          $values,   // ODAD_AST_Literal[]
    ) {}
}

/** Lambda operator: any/all */
class ODAD_AST_Lambda extends ODAD_AST_Node {
    public function __construct(
        public string        $operator,    // 'any' | 'all'
        public ODAD_AST_Node $collection,
        public string        $variable,    // lambda variable, e.g. 'd'
        public ODAD_AST_Node $expression,
    ) {}
}
```

---

## OData v4.01 Operators to Support

### Comparison (binary)
`eq`, `ne`, `lt`, `le`, `gt`, `ge`

### Logical (binary)
`and`, `or`

### Logical (unary)
`not`

### Arithmetic (binary)
`add`, `sub`, `mul`, `div`, `divby`, `mod`

### New in v4.01
`in` — e.g. `Status in ('draft', 'publish')`

### String Functions
`contains`, `startswith`, `endswith`, `length`, `indexof`, `substring`,
`tolower`, `toupper`, `trim`, `concat`, `matchesPattern`

### Date/Time Functions
`year`, `month`, `day`, `hour`, `minute`, `second`, `fractionalseconds`,
`date`, `time`, `totaloffsetminutes`, `now`, `mindatetime`, `maxdatetime`

### Math Functions
`round`, `floor`, `ceiling`, `isof`, `cast`

### Collection Functions (v4.01)
`hassubset`, `hassubsequence`

### Conditional
`case(when:then, ...)` — P3 priority (implement as stub that throws UnsupportedFeatureException)

### Lambda
`any`, `all`

---

## Operator Precedence (low → high)

1. `or`
2. `and`
3. `not`
4. `eq`, `ne`, `lt`, `le`, `gt`, `ge`
5. `in`
6. `add`, `sub`
7. `mul`, `div`, `divby`, `mod`
8. Unary `-`
9. Function calls, property access, literals, `(…)`

---

## Literal Parsing Rules

| OData Literal | Example | PHP value |
|---|---|---|
| Integer | `42` | `int 42` |
| Float | `3.14` | `float 3.14` |
| String | `'hello'` | `string 'hello'` (single quotes, escape `''` → `'`) |
| Boolean | `true`, `false` | `bool true/false` |
| Null | `null` | `null` |
| DateTimeOffset | `2024-01-15T10:30:00Z` | `string` (stored as-is) |
| GUID | `guid'...'` or `...-...-...-...` | `string` |
| Duration | `duration'P1D'` | `string` |

---

## Error Handling

Throw `ODAD_Filter_Parse_Exception` (create this class) with a descriptive message
including the position in the expression where parsing failed.

```php
class ODAD_Filter_Parse_Exception extends \InvalidArgumentException {
    public function __construct(
        string $message,
        public readonly int $position,
        public readonly string $expression,
    ) {
        parent::__construct( $message );
    }
}
```

---

## Acceptance Criteria

- `parse('Title eq \'Hello\'')` returns `ODAD_AST_Binary(eq, ODAD_AST_Property(Title), ODAD_AST_Literal(string, Hello))`.
- `parse('Status in (\'draft\', \'publish\')')` returns `ODAD_AST_In`.
- `parse('not (Status eq \'draft\')')` returns `ODAD_AST_Unary(not, ...)`.
- `parse('year(PublishedDate) gt 2023 and contains(Title, \'news\')')` returns nested `ODAD_AST_Binary(and, ...)`.
- Operator precedence is correct: `a eq 1 or b eq 2 and c eq 3` parses as `or(eq(a,1), and(eq(b,2), eq(c,3)))`.
- Invalid expressions throw `ODAD_Filter_Parse_Exception` with a position.
- No WordPress calls anywhere in this file.
- All unit tests (no WP bootstrap needed) pass for the above cases.
