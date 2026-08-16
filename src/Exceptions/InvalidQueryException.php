<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a query clause or operator is invalid or unsupported.
 */
class InvalidQueryException extends InvalidArgumentException
{
    public function __construct(string $message = 'Invalid query expression or unsupported operator.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
