<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Clauses;

/**
 * Represents a single ORDER BY column specification.
 */
class OrderByClause
{
    public function __construct(
        public readonly string $column,
        public readonly string $direction = 'asc', // 'asc' | 'desc'
    ) {}
}
