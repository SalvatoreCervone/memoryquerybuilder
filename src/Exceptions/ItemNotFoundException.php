<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Exceptions;

use RuntimeException;

/**
 * Thrown when a required item is not found in the dataset.
 * Used by firstOrFail() and findOrFail().
 */
class ItemNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'No matching item found in the dataset.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
