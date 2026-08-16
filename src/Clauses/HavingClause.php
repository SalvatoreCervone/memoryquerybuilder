<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Clauses;

/**
 * Represents a HAVING condition applied after GROUP BY aggregation.
 * Similar structure to WhereClause but operates on aggregate result keys.
 */
class HavingClause
{
    public function __construct(
        public readonly string $column,
        public readonly string $operator,
        public readonly mixed $value,
        public readonly string $boolean = 'and', // 'and' | 'or'
    ) {}
}
