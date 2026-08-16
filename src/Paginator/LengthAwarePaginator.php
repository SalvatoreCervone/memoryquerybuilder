<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Paginator;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A paginator with full metadata: total, per_page, current_page, last_page, from, to, data.
 */
class LengthAwarePaginator implements PaginatorInterface, IteratorAggregate, Countable
{
    private array $items;
    private int $total;
    private int $perPage;
    private int $currentPage;

    public function __construct(array $items, int $total, int $perPage, int $currentPage = 1)
    {
        $this->items       = array_values($items);
        $this->total       = $total;
        $this->perPage     = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
    }

    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return (int) max(1, ceil($this->total / $this->perPage));
    }

    public function from(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->from() + count($this->items) - 1);
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function toArray(): array
    {
        return [
            'total'         => $this->total(),
            'per_page'      => $this->perPage(),
            'current_page'  => $this->currentPage(),
            'last_page'     => $this->lastPage(),
            'from'          => $this->from(),
            'to'            => $this->to(),
            'has_more_pages' => $this->hasMorePages(),
            'data'          => $this->items(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
