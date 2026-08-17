<?php

declare(strict_types=1);

namespace MemoryQueryBuilder;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * A fluent collection returned by MemoryQueryBuilder::get().
 * Implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable.
 * Provides Eloquent Collection-style utility methods.
 *
 * @template TValue
 */
class Collection implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable
{
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    // -------------------------------------------------------------------------
    // Static factory
    // -------------------------------------------------------------------------

    public static function make(array $items = []): static
    {
        return new static($items);
    }

    // -------------------------------------------------------------------------
    // Core iteration / access
    // -------------------------------------------------------------------------

    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
        $this->items = array_values($this->items);
    }

    // -------------------------------------------------------------------------
    // Transformation
    // -------------------------------------------------------------------------

    /**
     * Apply a callback to each item and return a new Collection.
     */
    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    /**
     * Filter items keeping those where callback returns true.
     */
    public function filter(?callable $callback = null): static
    {
        if ($callback === null) {
            return new static(array_values(array_filter($this->items)));
        }

        return new static(array_values(array_filter($this->items, $callback)));
    }

    /**
     * Reduce items to a single value.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Apply a callback to each item (side effects only), returns $this.
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Apply callback to each item in-place, returns $this.
     */
    public function transform(callable $callback): static
    {
        $this->items = array_values(array_map($callback, $this->items));
        return $this;
    }

    /**
     * Sort items by a column or callback.
     */
    public function sortBy(callable|string $callback, bool $descending = false): static
    {
        $items = $this->items;

        usort($items, function ($a, $b) use ($callback, $descending) {
            $aVal = is_callable($callback) ? $callback($a) : Support\PropertyAccessor::get($a, $callback);
            $bVal = is_callable($callback) ? $callback($b) : Support\PropertyAccessor::get($b, $callback);

            $result = $aVal <=> $bVal;
            return $descending ? -$result : $result;
        });

        return new static($items);
    }

    public function sortByDesc(callable|string $callback): static
    {
        return $this->sortBy($callback, descending: true);
    }

    /**
     * Group items by a column or callback into a plain array (column => Collection).
     *
     * @return array<string, static>
     */
    public function groupBy(callable|string $callback): array
    {
        $groups = [];

        foreach ($this->items as $item) {
            $key = is_callable($callback) ? $callback($item) : Support\PropertyAccessor::get($item, $callback);
            $key = (string) $key;

            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }

            $groups[$key][] = $item;
        }

        return array_map(fn($g) => new static($g), $groups);
    }

    /**
     * Pluck a column from each item.
     * If $keyColumn is given, return an array keyed by that column.
     */
    public function pluck(string $valueColumn, ?string $keyColumn = null): static
    {
        $results = [];

        foreach ($this->items as $item) {
            $value = Support\PropertyAccessor::get($item, $valueColumn);

            if ($keyColumn !== null) {
                $key = Support\PropertyAccessor::get($item, $keyColumn);
                $results[$key] = $value;
            } else {
                $results[] = $value;
            }
        }

        return new static($results);
    }

    /**
     * Convert collection items to stdClass objects.
     * Items that are already objects remain unchanged.
     */
    public function toObjects(bool $recursive = false): static
    {
        return $this->map(function ($item) use ($recursive) {
            if (!is_array($item)) {
                return $item;
            }

            if ($recursive) {
                return json_decode(json_encode($item));
            }

            return (object) $item;
        });
    }

    /**
     * Alias of toObjects().
     */
    public function toObject(bool $recursive = false): static
    {
        return $this->toObjects($recursive);
    }

    /**
     * Convert collection items to associative arrays.
     * Items that are already arrays remain unchanged.
     */
    public function toArrays(bool $recursive = false): static
    {
        return $this->map(function ($item) use ($recursive) {
            if (is_array($item)) {
                if ($recursive) {
                    return json_decode(json_encode($item), true);
                }
                return $item;
            }

            if ($recursive) {
                return json_decode(json_encode($item), true);
            }

            if ($item instanceof JsonSerializable || $item instanceof \stdClass) {
                return (array) $item;
            }

            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }

            if (is_object($item)) {
                return (array) $item;
            }

            return $item;
        });
    }

    /**
     * Alias of toArrays().
     */
    public function toAssocArray(bool $recursive = false): static
    {
        return $this->toArrays($recursive);
    }

    /**
     * Alias of toArrays().
     */
    public function toAssoc(bool $recursive = false): static
    {
        return $this->toArrays($recursive);
    }

    // -------------------------------------------------------------------------
    // Retrieval
    // -------------------------------------------------------------------------

    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return $this->items[0] ?? $default;
        }

        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return $default;
    }

    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? $default : $this->items[count($this->items) - 1];
        }

        $found = $default;
        foreach ($this->items as $item) {
            if ($callback($item)) {
                $found = $item;
            }
        }

        return $found;
    }

    public function flatten(int $depth = INF): static
    {
        $result = [];
        array_walk_recursive($this->items, function ($value) use (&$result) {
            $result[] = $value;
        });
        return new static($result);
    }

    public function values(): static
    {
        return new static(array_values($this->items));
    }

    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    public function unique(?string $column = null): static
    {
        if ($column === null) {
            return new static(array_values(array_unique($this->items)));
        }

        $seen = [];
        $result = [];

        foreach ($this->items as $item) {
            $key = Support\PropertyAccessor::get($item, $column);
            $keyStr = serialize($key);
            if (!isset($seen[$keyStr])) {
                $seen[$keyStr] = true;
                $result[] = $item;
            }
        }

        return new static($result);
    }

    public function take(int $limit): static
    {
        if ($limit < 0) {
            return new static(array_slice($this->items, $limit));
        }

        return new static(array_slice($this->items, 0, $limit));
    }

    public function skip(int $offset): static
    {
        return new static(array_slice($this->items, $offset));
    }

    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_slice($this->items, $offset, $length));
    }

    public function contains(mixed $callback): bool
    {
        if (is_callable($callback)) {
            foreach ($this->items as $item) {
                if ($callback($item)) {
                    return true;
                }
            }
            return false;
        }

        return in_array($callback, $this->items, strict: false);
    }

    // -------------------------------------------------------------------------
    // Aggregation
    // -------------------------------------------------------------------------

    public function sum(?string $column = null): int|float
    {
        $values = $column
            ? array_map(fn($item) => (float) Support\PropertyAccessor::get($item, $column, 0), $this->items)
            : array_map(fn($item) => (float) $item, $this->items);

        return array_sum($values);
    }

    public function avg(?string $column = null): int|float
    {
        if (empty($this->items)) {
            return 0;
        }

        return $this->sum($column) / count($this->items);
    }

    public function min(?string $column = null): mixed
    {
        if (empty($this->items)) {
            return null;
        }

        $values = $column
            ? array_map(fn($item) => Support\PropertyAccessor::get($item, $column), $this->items)
            : $this->items;

        return min($values);
    }

    public function max(?string $column = null): mixed
    {
        if (empty($this->items)) {
            return null;
        }

        $values = $column
            ? array_map(fn($item) => Support\PropertyAccessor::get($item, $column), $this->items)
            : $this->items;

        return max($values);
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function toArray(): array
    {
        return $this->items;
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->items, $flags | JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): array
    {
        return $this->items;
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}
