<?php

declare(strict_types=1);

namespace MemoryQueryBuilder;

use Closure;
use MemoryQueryBuilder\Clauses\HavingClause;
use MemoryQueryBuilder\Clauses\OrderByClause;
use MemoryQueryBuilder\Clauses\WhereClause;
use MemoryQueryBuilder\Exceptions\InvalidQueryException;
use MemoryQueryBuilder\Exceptions\ItemNotFoundException;
use MemoryQueryBuilder\Paginator\LengthAwarePaginator;
use MemoryQueryBuilder\Support\Comparator;
use MemoryQueryBuilder\Support\PropertyAccessor;

/**
 * MemoryQueryBuilder – Eloquent-style in-memory query builder.
 *
 * Usage:
 *   $result = MemoryQueryBuilder::from($array)
 *       ->where('status', 'active')
 *       ->where('age', '>=', 18)
 *       ->orderByDesc('created_at')
 *       ->limit(10)
 *       ->get();
 */
class MemoryQueryBuilder
{
    /** @var array Raw input dataset */
    private array $data;

    /** @var WhereClause[] */
    private array $wheres = [];

    /** @var HavingClause[] */
    private array $havings = [];

    /** @var OrderByClause[] */
    private array $orders = [];

    /** @var string[] Columns to select (null = all) */
    private ?array $selects = null;

    /** @var string[] Columns to group by */
    private array $groupBys = [];

    private ?int $limitValue  = null;
    private ?int $offsetValue = null;
    private bool $distinctFlag = false;
    private ?string $distinctColumn = null;
    private bool $randomOrder = false;
    private ?int $randomSeed  = null;

    // -------------------------------------------------------------------------
    // Constructor / Factory
    // -------------------------------------------------------------------------

    public function __construct(array|\Traversable $data = [])
    {
        $this->data = is_array($data) ? $data : iterator_to_array($data, false);
    }

    /**
     * Static factory – the main entry point.
     */
    public static function from(array|\Traversable $data): static
    {
        return new static($data);
    }

    /**
     * Clone the builder to allow forking without mutation.
     */
    public function newQuery(array|\Traversable $data): static
    {
        return new static($data);
    }

    // -------------------------------------------------------------------------
    // WHERE clauses
    // -------------------------------------------------------------------------

    /**
     * Add a WHERE condition.
     *
     * Signatures:
     *   ->where('column', 'value')               // operator defaults to '='
     *   ->where('column', '>=', 'value')
     *   ->where(function($q) { ... })            // nested group
     */
    public function where(string|Closure $column, mixed $operatorOrValue = null, mixed $value = null, string $boolean = 'and'): static
    {
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // Two-argument form: where('col', 'val') → operator defaults to '='
        if ($value === null && $operatorOrValue !== null) {
            [$operator, $value] = ['=', $operatorOrValue];
        } else {
            $operator = (string) $operatorOrValue;
        }

        $this->wheres[] = WhereClause::condition($column, $operator, $value, $boolean);
        return $this;
    }

    public function orWhere(string|Closure $column, mixed $operatorOrValue = null, mixed $value = null): static
    {
        return $this->where($column, $operatorOrValue, $value, 'or');
    }

    /**
     * Add a nested WHERE group using a Closure.
     */
    public function whereNested(Closure $callback, string $boolean = 'and'): static
    {
        $subBuilder = new static($this->data);
        $callback($subBuilder);
        $this->wheres[] = WhereClause::group($subBuilder->wheres, $boolean);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Dedicated WHERE helpers
    // -------------------------------------------------------------------------

    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $operator = $not ? 'not in' : 'in';
        $this->wheres[] = WhereClause::condition($column, $operator, $values, $boolean);
        return $this;
    }

    public function orWhereIn(string $column, array $values): static
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereNotIn(string $column, array $values): static
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = WhereClause::nullCheck($column, isNull: !$not, boolean: $boolean);
        return $this;
    }

    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'or');
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNotNull(string $column): static
    {
        return $this->whereNotNull($column, 'or');
    }

    public function whereBetween(string $column, array $range, string $boolean = 'and', bool $not = false): static
    {
        $operator = $not ? 'not between' : 'between';
        $this->wheres[] = WhereClause::condition($column, $operator, $range, $boolean);
        return $this;
    }

    public function orWhereBetween(string $column, array $range): static
    {
        return $this->whereBetween($column, $range, 'or');
    }

    public function whereNotBetween(string $column, array $range, string $boolean = 'and'): static
    {
        return $this->whereBetween($column, $range, $boolean, true);
    }

    public function orWhereNotBetween(string $column, array $range): static
    {
        return $this->whereNotBetween($column, $range, 'or');
    }

    public function whereLike(string $column, string $pattern, bool $caseSensitive = false, string $boolean = 'and'): static
    {
        $operator = $caseSensitive ? 'like' : 'ilike';
        $this->wheres[] = WhereClause::condition($column, $operator, $pattern, $boolean);
        return $this;
    }

    public function whereNotLike(string $column, string $pattern, bool $caseSensitive = false, string $boolean = 'and'): static
    {
        $operator = $caseSensitive ? 'not like' : 'not ilike';
        $this->wheres[] = WhereClause::condition($column, $operator, $pattern, $boolean);
        return $this;
    }

    public function whereContains(string $column, string $value, bool $caseSensitive = false, string $boolean = 'and'): static
    {
        $operator = $caseSensitive ? 'contains' : 'icontains';
        $this->wheres[] = WhereClause::condition($column, $operator, $value, $boolean);
        return $this;
    }

    public function whereStartsWith(string $column, string $value, string $boolean = 'and'): static
    {
        $this->wheres[] = WhereClause::condition($column, 'starts_with', $value, $boolean);
        return $this;
    }

    public function whereEndsWith(string $column, string $value, string $boolean = 'and'): static
    {
        $this->wheres[] = WhereClause::condition($column, 'ends_with', $value, $boolean);
        return $this;
    }

    /**
     * Filter by a date portion. $portion can be 'date', 'year', 'month', 'day', 'time'.
     */
    public function whereDate(string $column, string $operator, mixed $value, string $portion = 'date', string $boolean = 'and'): static
    {
        // Wrap in a closure that extracts the date portion before comparing
        $this->where(function (self $q) use ($column, $operator, $value, $portion, $boolean) {
            // We'll add a synthetic condition using a special 'date:PORTION' operator convention
            $q->wheres[] = WhereClause::condition($column, "date:{$portion}:{$operator}", $value, $boolean);
        }, boolean: $boolean);
        return $this;
    }

    public function whereYear(string $column, string $operator, mixed $value, string $boolean = 'and'): static
    {
        $this->wheres[] = WhereClause::condition($column, "date:year:{$operator}", $value, $boolean);
        return $this;
    }

    public function whereMonth(string $column, string $operator, mixed $value, string $boolean = 'and'): static
    {
        $this->wheres[] = WhereClause::condition($column, "date:month:{$operator}", $value, $boolean);
        return $this;
    }

    public function whereDay(string $column, string $operator, mixed $value, string $boolean = 'and'): static
    {
        $this->wheres[] = WhereClause::condition($column, "date:day:{$operator}", $value, $boolean);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Conditional helpers
    // -------------------------------------------------------------------------

    /**
     * Apply a callback only when $condition is truthy.
     * Optionally apply $otherwise when $condition is falsy.
     */
    public function when(mixed $condition, callable $callback, ?callable $otherwise = null): static
    {
        if ($condition) {
            $callback($this, $condition);
        } elseif ($otherwise !== null) {
            $otherwise($this, $condition);
        }

        return $this;
    }

    /**
     * Apply a callback only when $condition is falsy.
     */
    public function unless(mixed $condition, callable $callback, ?callable $otherwise = null): static
    {
        return $this->when(!$condition, $callback, $otherwise);
    }

    // -------------------------------------------------------------------------
    // ORDERING
    // -------------------------------------------------------------------------

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $this->orders[] = new OrderByClause($column, $direction);
        return $this;
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function inRandomOrder(?int $seed = null): static
    {
        $this->randomOrder = true;
        $this->randomSeed  = $seed;
        return $this;
    }

    // -------------------------------------------------------------------------
    // SELECT / DISTINCT
    // -------------------------------------------------------------------------

    public function select(string ...$columns): static
    {
        $this->selects = $columns;
        return $this;
    }

    public function addSelect(string ...$columns): static
    {
        $this->selects = array_merge($this->selects ?? [], $columns);
        return $this;
    }

    public function distinct(?string $column = null): static
    {
        $this->distinctFlag   = true;
        $this->distinctColumn = $column;
        return $this;
    }

    // -------------------------------------------------------------------------
    // LIMIT / OFFSET / FORPAGE
    // -------------------------------------------------------------------------

    public function limit(int $value): static
    {
        $this->limitValue = max(0, $value);
        return $this;
    }

    public function take(int $value): static
    {
        return $this->limit($value);
    }

    public function offset(int $value): static
    {
        $this->offsetValue = max(0, $value);
        return $this;
    }

    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    public function forPage(int $page, int $perPage): static
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    // -------------------------------------------------------------------------
    // GROUP BY / HAVING
    // -------------------------------------------------------------------------

    public function groupBy(string ...$columns): static
    {
        $this->groupBys = array_merge($this->groupBys, $columns);
        return $this;
    }

    public function having(string $column, mixed $operatorOrValue = null, mixed $value = null, string $boolean = 'and'): static
    {
        if ($value === null && $operatorOrValue !== null) {
            [$operator, $value] = ['=', $operatorOrValue];
        } else {
            $operator = (string) $operatorOrValue;
        }

        $this->havings[] = new HavingClause($column, $operator, $value, $boolean);
        return $this;
    }

    public function orHaving(string $column, mixed $operatorOrValue = null, mixed $value = null): static
    {
        return $this->having($column, $operatorOrValue, $value, 'or');
    }

    // -------------------------------------------------------------------------
    // EXECUTION: get() and friends
    // -------------------------------------------------------------------------

    /**
     * Execute the query and return a Collection of matching items.
     */
    public function get(): Collection
    {
        $results = $this->runQuery();
        return new Collection($results);
    }

    /**
     * Get the first matching item or return $default.
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        $results = $this->runQuery();

        if ($callback !== null) {
            foreach ($results as $item) {
                if ($callback($item)) {
                    return $item;
                }
            }
            return $default;
        }

        return $results[0] ?? $default;
    }

    /**
     * Get the first matching item or throw ItemNotFoundException.
     */
    public function firstOrFail(): mixed
    {
        $result = $this->first();

        if ($result === null) {
            throw new ItemNotFoundException();
        }

        return $result;
    }

    /**
     * Get the last matching item.
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        $results = $this->runQuery();

        if ($callback !== null) {
            $found = $default;
            foreach ($results as $item) {
                if ($callback($item)) {
                    $found = $item;
                }
            }
            return $found;
        }

        return empty($results) ? $default : $results[count($results) - 1];
    }

    /**
     * Find an item by primary key (default: 'id').
     */
    public function find(mixed $id, string $primaryKey = 'id'): mixed
    {
        return $this->where($primaryKey, '=', $id)->first();
    }

    /**
     * Find an item by primary key or throw ItemNotFoundException.
     */
    public function findOrFail(mixed $id, string $primaryKey = 'id'): mixed
    {
        $result = $this->find($id, $primaryKey);

        if ($result === null) {
            throw new ItemNotFoundException("Item with {$primaryKey} = {$id} not found.");
        }

        return $result;
    }

    /**
     * Return the value of a single column from the first result.
     */
    public function value(string $column): mixed
    {
        $item = $this->first();
        return $item !== null ? PropertyAccessor::get($item, $column) : null;
    }

    /**
     * Return a Collection of values for a given column.
     * If $keyColumn is given, use it as keys.
     */
    public function pluck(string $valueColumn, ?string $keyColumn = null): Collection
    {
        $results = $this->runQuery();
        $plucked = [];

        foreach ($results as $item) {
            $value = PropertyAccessor::get($item, $valueColumn);

            if ($keyColumn !== null) {
                $key = PropertyAccessor::get($item, $keyColumn);
                $plucked[$key] = $value;
            } else {
                $plucked[] = $value;
            }
        }

        return new Collection($plucked);
    }

    /**
     * Returns true if at least one item matches.
     */
    public function exists(): bool
    {
        return !empty($this->runQuery());
    }

    /**
     * Returns true if no items match.
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    // -------------------------------------------------------------------------
    // AGGREGATIONS
    // -------------------------------------------------------------------------

    public function count(?string $column = null): int
    {
        $results = $this->runQuery();

        if ($column !== null) {
            // COUNT non-null values
            return count(array_filter($results, fn($item) => PropertyAccessor::get($item, $column) !== null));
        }

        return count($results);
    }

    public function sum(string $column): int|float
    {
        $results = $this->runQuery();
        return array_sum(array_map(fn($item) => (float) PropertyAccessor::get($item, $column, 0), $results));
    }

    public function avg(string $column): int|float
    {
        $results = $this->runQuery();

        if (empty($results)) {
            return 0;
        }

        return $this->sum($column) / count($results);
    }

    public function min(string $column): mixed
    {
        $results = $this->runQuery();

        if (empty($results)) {
            return null;
        }

        $values = array_map(fn($item) => PropertyAccessor::get($item, $column), $results);
        return min($values);
    }

    public function max(string $column): mixed
    {
        $results = $this->runQuery();

        if (empty($results)) {
            return null;
        }

        $values = array_map(fn($item) => PropertyAccessor::get($item, $column), $results);
        return max($values);
    }

    // -------------------------------------------------------------------------
    // PAGINATION
    // -------------------------------------------------------------------------

    /**
     * Paginate results.
     */
    public function paginate(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        // Get all filtered+sorted results (without limit/offset) to count total
        $allResults = $this->runQuery(applyLimitOffset: false);
        $total      = count($allResults);

        // Slice the page
        $offset = ($page - 1) * $perPage;
        $items  = array_slice($allResults, $offset, $perPage);

        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    // -------------------------------------------------------------------------
    // CHUNK
    // -------------------------------------------------------------------------

    /**
     * Process results in chunks. Callback receives a Collection per chunk.
     * Return false from callback to stop processing.
     */
    public function chunk(int $size, callable $callback): bool
    {
        $results = $this->runQuery();
        $chunks  = array_chunk($results, $size);

        foreach ($chunks as $chunk) {
            if ($callback(new Collection($chunk)) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alias for get()->toArray()
     */
    public function toArray(): array
    {
        return $this->get()->toArray();
    }

    /**
     * Alias for get()->toJson()
     */
    public function toJson(int $flags = 0): string
    {
        return $this->get()->toJson($flags);
    }

    // -------------------------------------------------------------------------
    // INTERNAL: Query execution engine
    // -------------------------------------------------------------------------

    /**
     * Run the full query pipeline: filter → group → order → select → limit/offset.
     */
    private function runQuery(bool $applyLimitOffset = true): array
    {
        // 1. Filter
        $results = array_values(array_filter($this->data, fn($item) => $this->matchesWheres($item, $this->wheres)));

        // 2. Group By
        if (!empty($this->groupBys)) {
            $results = $this->applyGroupBy($results);
        }

        // 3. Sort
        if ($this->randomOrder) {
            if ($this->randomSeed !== null) {
                srand($this->randomSeed);
            }
            shuffle($results);
        } elseif (!empty($this->orders)) {
            usort($results, fn($a, $b) => $this->compareItems($a, $b));
        }

        // 4. Select projection (must happen before distinct so distinct keys are correct)
        if ($this->selects !== null) {
            $results = array_map(fn($item) => $this->applySelect($item), $results);
        }

        // 5. Distinct (operates on projected items)
        if ($this->distinctFlag) {
            $results = $this->applyDistinct($results);
        }

        // 6. Limit / Offset
        if ($applyLimitOffset) {
            $offset  = $this->offsetValue ?? 0;
            $limit   = $this->limitValue;
            $results = array_slice($results, $offset, $limit);
        }

        return $results;
    }

    /**
     * Evaluate a list of WhereClause objects against a single item.
     * Implements AND/OR chaining with short-circuit evaluation.
     *
     * @param WhereClause[] $clauses
     */
    private function matchesWheres(mixed $item, array $clauses): bool
    {
        if (empty($clauses)) {
            return true;
        }

        $result = null;

        foreach ($clauses as $clause) {
            $matches = $this->evaluateClause($item, $clause);

            if ($result === null) {
                $result = $matches;
            } elseif (strtolower($clause->boolean) === 'or') {
                $result = $result || $matches;
            } else {
                $result = $result && $matches;
            }
        }

        return (bool) $result;
    }

    /**
     * Evaluate a single WhereClause.
     */
    private function evaluateClause(mixed $item, WhereClause $clause): bool
    {
        return match ($clause->type) {
            WhereClause::TYPE_CONDITION => $this->evaluateCondition($item, $clause),
            WhereClause::TYPE_NULL      => $this->evaluateNullCheck($item, $clause),
            WhereClause::TYPE_GROUP     => $this->matchesWheres($item, $clause->children),
            default                     => throw new InvalidQueryException("Unknown clause type: {$clause->type}"),
        };
    }

    private function evaluateCondition(mixed $item, WhereClause $clause): bool
    {
        $column   = $clause->column;
        $operator = $clause->operator;
        $value    = $clause->value;

        $leftValue = PropertyAccessor::get($item, $column);

        // Handle date: prefixed operators
        if (str_starts_with($operator, 'date:')) {
            return $this->evaluateDateCondition($leftValue, $operator, $value);
        }

        return Comparator::evaluate($leftValue, $operator, $value);
    }

    private function evaluateNullCheck(mixed $item, WhereClause $clause): bool
    {
        $value = PropertyAccessor::get($item, $clause->column);
        return $clause->isNull ? $value === null : $value !== null;
    }

    /**
     * Evaluate date:PORTION:OPERATOR conditions.
     * Format: 'date:year:>=', 'date:month:=', 'date:day:>', 'date:date:='
     */
    private function evaluateDateCondition(mixed $rawValue, string $operator, mixed $compareValue): bool
    {
        [, $portion, $realOperator] = explode(':', $operator, 3);

        // Parse the raw value to a timestamp
        if ($rawValue instanceof \DateTimeInterface) {
            $ts = $rawValue->getTimestamp();
        } else {
            $ts = is_int($rawValue) ? $rawValue : strtotime((string) $rawValue);
            if ($ts === false) {
                return false;
            }
        }

        $extracted = match ($portion) {
            'year'  => (int) date('Y', $ts),
            'month' => (int) date('n', $ts),
            'day'   => (int) date('j', $ts),
            'time'  => date('H:i:s', $ts),
            'date'  => date('Y-m-d', $ts),
            default => date('Y-m-d', $ts),
        };

        return Comparator::evaluate($extracted, $realOperator, $compareValue);
    }

    /**
     * Multi-column comparison for usort.
     */
    private function compareItems(mixed $a, mixed $b): int
    {
        foreach ($this->orders as $order) {
            $aVal = PropertyAccessor::get($a, $order->column);
            $bVal = PropertyAccessor::get($b, $order->column);

            $cmp = $aVal <=> $bVal;

            if ($cmp !== 0) {
                return $order->direction === 'desc' ? -$cmp : $cmp;
            }
        }

        return 0;
    }

    /**
     * Apply GROUP BY and reduce items to grouped representations.
     * Returns an array of ['_group_key' => string, '_items' => array, ...aggregates] items.
     * Also applies HAVING filters.
     */
    private function applyGroupBy(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            // Build composite group key
            $keyParts = [];
            foreach ($this->groupBys as $col) {
                $keyParts[] = (string) PropertyAccessor::get($item, $col);
            }
            $groupKey = implode('|', $keyParts);

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    '_group_key' => $groupKey,
                    '_items'     => [],
                ];

                // Seed group columns with their values
                foreach ($this->groupBys as $col) {
                    $groups[$groupKey][$col] = PropertyAccessor::get($item, $col);
                }
            }

            $groups[$groupKey]['_items'][] = $item;
        }

        // Compute a count aggregate for each group (useful for having)
        foreach ($groups as &$group) {
            $group['count'] = count($group['_items']);
        }

        // Apply HAVING
        if (!empty($this->havings)) {
            $groups = array_filter($groups, fn($group) => $this->matchesHaving($group));
        }

        return array_values($groups);
    }

    private function matchesHaving(array $group): bool
    {
        $result = null;

        foreach ($this->havings as $having) {
            $value   = PropertyAccessor::get($group, $having->column);
            $matches = Comparator::evaluate($value, $having->operator, $having->value);

            if ($result === null) {
                $result = $matches;
            } elseif (strtolower($having->boolean) === 'or') {
                $result = $result || $matches;
            } else {
                $result = $result && $matches;
            }
        }

        return (bool) $result;
    }

    /**
     * Remove duplicate items. If $distinctColumn is set, deduplicate by that column.
     */
    private function applyDistinct(array $items): array
    {
        if ($this->distinctColumn !== null) {
            $seen   = [];
            $result = [];

            foreach ($items as $item) {
                $key = serialize(PropertyAccessor::get($item, $this->distinctColumn));
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $result[]   = $item;
                }
            }

            return $result;
        }

        // Full-item deduplication
        $seen   = [];
        $result = [];

        foreach ($items as $item) {
            $key = serialize($item);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[]   = $item;
            }
        }

        return $result;
    }

    /**
     * Project an item to only selected columns.
     * Supports aliasing: 'user.name as author_name'
     */
    private function applySelect(mixed $item): array
    {
        $projected = [];

        foreach ($this->selects as $spec) {
            // Check for alias: 'column as alias'
            if (stripos($spec, ' as ') !== false) {
                [$column, $alias] = preg_split('/\s+as\s+/i', $spec, 2);
                $projected[trim($alias)] = PropertyAccessor::get($item, trim($column));
            } else {
                $projected[$spec] = PropertyAccessor::get($item, $spec);
            }
        }

        return $projected;
    }
}
