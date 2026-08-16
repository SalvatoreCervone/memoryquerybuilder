<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Clauses;

/**
 * Represents a single WHERE condition or a nested group of conditions.
 * A clause can be:
 *  - A standard condition: ['column', 'operator', 'value', 'and'|'or']
 *  - A nested group (Closure): ['group', [WhereClause, ...], 'and'|'or']
 *  - A null check: ['null', 'column', true|false(isNull), 'and'|'or']
 */
class WhereClause
{
    public const TYPE_CONDITION = 'condition';
    public const TYPE_GROUP     = 'group';
    public const TYPE_NULL      = 'null';
    public const TYPE_RAW       = 'raw';

    public function __construct(
        public readonly string $type,
        public readonly string $boolean, // 'and' | 'or'
        public readonly ?string $column   = null,
        public readonly ?string $operator = null,
        public readonly mixed $value      = null,
        /** @var WhereClause[] */
        public readonly array $children   = [],
        public readonly bool $isNull      = true, // for TYPE_NULL: true = IS NULL, false = IS NOT NULL
    ) {}

    /**
     * Create a standard condition clause.
     */
    public static function condition(string $column, string $operator, mixed $value, string $boolean = 'and'): self
    {
        return new self(
            type: self::TYPE_CONDITION,
            boolean: $boolean,
            column: $column,
            operator: $operator,
            value: $value,
        );
    }

    /**
     * Create a nested group clause from a Closure.
     *
     * @param WhereClause[] $children
     */
    public static function group(array $children, string $boolean = 'and'): self
    {
        return new self(
            type: self::TYPE_GROUP,
            boolean: $boolean,
            children: $children,
        );
    }

    /**
     * Create an IS NULL / IS NOT NULL clause.
     */
    public static function nullCheck(string $column, bool $isNull = true, string $boolean = 'and'): self
    {
        return new self(
            type: self::TYPE_NULL,
            boolean: $boolean,
            column: $column,
            isNull: $isNull,
        );
    }
}
