<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Paginator;

use JsonSerializable;

interface PaginatorInterface extends JsonSerializable
{
    public function items(): array;
    public function total(): int;
    public function perPage(): int;
    public function currentPage(): int;
    public function lastPage(): int;
    public function from(): int;
    public function to(): int;
    public function hasMorePages(): bool;
    public function toArray(): array;
}
