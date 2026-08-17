# MemoryQueryBuilder

> **Eloquent-style in-memory query builder for PHP 8.1+**
> Filter, sort, aggregate and paginate any array or object collection with a fluent, chainable API — no database required.

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-132%20passed-brightgreen)](#running-tests)

```php
$result = MemoryQueryBuilder::from($myArray)
    ->where('status', 'active')
    ->where('age', '>=', 18)
    ->orderByDesc('created_at')
    ->limit(10)
    ->get();
```

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Data Sources](#data-sources)
- [WHERE Clauses](#where-clauses)
- [Ordering](#ordering)
- [Select & Distinct](#select--distinct)
- [Limit, Offset & Pagination](#limit-offset--pagination)
- [Group By & Having](#group-by--having)
- [Aggregations](#aggregations)
- [Execution Methods](#execution-methods)
- [Collection API](#collection-api)
- [Running Tests](#running-tests)

---

## Installation

```bash
composer require salvatorecervone/memoryquerybuilder
```

Or clone directly:

```bash
git clone https://github.com/SalvatoreCervone/memoryquerybuilder.git
cd memoryquerybuilder
composer install
```

---

## Quick Start

```php
use MemoryQueryBuilder\MemoryQueryBuilder;

$users = [
    ['id' => 1, 'name' => 'Alice', 'age' => 30, 'role' => 'admin',  'status' => 'active'],
    ['id' => 2, 'name' => 'Bob',   'age' => 17, 'role' => 'user',   'status' => 'inactive'],
    ['id' => 3, 'name' => 'Carol', 'age' => 25, 'role' => 'editor', 'status' => 'active'],
];

// Filter + order
$result = MemoryQueryBuilder::from($users)
    ->where('status', 'active')
    ->where('age', '>=', 18)
    ->orderBy('name')
    ->get(); // returns a Collection

// Aggregations
echo MemoryQueryBuilder::from($users)->avg('age');  // 24.0
echo MemoryQueryBuilder::from($users)->count();     // 3

// Pagination
$page = MemoryQueryBuilder::from($users)->paginate(perPage: 2, page: 1);
echo $page->total();     // 3
echo $page->lastPage();  // 2
```

---

## Data Sources

MemoryQueryBuilder works with **any iterable data source**:

```php
// Plain associative arrays
MemoryQueryBuilder::from([['id' => 1, 'name' => 'Alice'], ...]);

// stdClass objects
$obj = new stdClass();
$obj->id = 1; $obj->name = 'Alice';
MemoryQueryBuilder::from([$obj]);

// Objects with getter methods (private/protected properties)
// 'name' resolves to getName(), 'is_active' resolves to isIsActive()
class User {
    public function __construct(private string $name, private bool $isActive) {}
    public function getName(): string { return $this->name; }
    public function isIsActive(): bool { return $this->isActive; }
}
MemoryQueryBuilder::from([$user1, $user2]);

// Generators / Traversable
$generator = (function() {
    yield ['id' => 1, 'val' => 10];
    yield ['id' => 2, 'val' => 20];
})();
MemoryQueryBuilder::from($generator);

// Mixed arrays + objects in the same dataset ✓
MemoryQueryBuilder::from([$array, $stdClass, $entity]);
```

### Dot-Notation for Nested Data

Access nested fields at any depth using `.` notation:

```php
$data = [
    ['user' => ['name' => 'Alice', 'address' => ['city' => 'Rome']]],
    ['user' => ['name' => 'Bob',   'address' => ['city' => 'Milan']]],
];

MemoryQueryBuilder::from($data)
    ->where('user.address.city', 'Rome')
    ->pluck('user.name')
    ->select('user.name as author', 'user.address.city as city')
    ->get();
```

---

## WHERE Clauses

### Basic

```php
->where('column', 'value')             // operator defaults to '='
->where('column', '>=', 100)
->orWhere('role', 'admin')
->orWhere('score', '>', 90)
```

### Operators Supported

| Operator | Description |
|---|---|
| `=`, `==`, `===` | Equality |
| `!=`, `<>`, `!==` | Inequality |
| `<`, `<=`, `>`, `>=` | Comparisons |
| `like`, `not like` | SQL-style pattern (`%`, `_`), case-sensitive |
| `ilike`, `not ilike` | Case-insensitive LIKE |
| `contains`, `icontains` | Substring match |
| `starts_with`, `ends_with` | Prefix / suffix match |
| `in`, `not in` | Value in array |
| `between`, `not between` | Range check `[min, max]` |
| `regexp`, `not regexp` | Regular expression |

```php
->where('name', 'like', 'A%')
->where('email', 'regexp', '^[a-z]+@example\.com$')
->where('role', 'in', ['admin', 'editor'])
->where('age', 'between', [18, 65])
```

### Dedicated Helpers

```php
->whereIn('role', ['admin', 'editor'])
->whereNotIn('status', ['cancelled', 'refunded'])
->orWhereIn('tag', ['featured'])
->orWhereNotIn('status', ['archived'])

->whereNull('deleted_at')
->whereNotNull('email')
->orWhereNull('verified_at')

->whereBetween('age', [18, 65])
->whereNotBetween('score', [0, 50])
->orWhereBetween('created_at', ['2024-01-01', '2024-12-31'])

->whereLike('email', '%@example.com')         // case-insensitive by default
->whereLike('name', 'Alice', caseSensitive: true)
->whereNotLike('email', '%@spam.com')

->whereContains('bio', 'developer')           // case-insensitive by default
->whereStartsWith('name', 'Al')
->whereEndsWith('email', '.org')

->whereYear('created_at', '=', 2024)
->whereMonth('created_at', '>=', 6)
->whereDay('created_at', '<=', 15)
```

### Nested Groups

```php
// (status = 'active') AND (role = 'admin' OR score >= 90)
MemoryQueryBuilder::from($users)
    ->where('status', 'active')
    ->where(function (MemoryQueryBuilder $q) {
        $q->where('role', 'admin')
          ->orWhere('score', '>=', 90);
    })
    ->get();
```

### Conditional Queries

```php
$search   = 'alice';  // or null to skip
$minScore = 50;

MemoryQueryBuilder::from($users)
    ->when($search,   fn($q, $v) => $q->whereLike('name', "%{$v}%"))
    ->when($minScore, fn($q, $v) => $q->where('score', '>=', $v))
    ->unless($isAdmin, fn($q)   => $q->where('public', true))
    ->get();
```

---

## Ordering

```php
->orderBy('name')                    // ASC (default)
->orderBy('name', 'desc')
->orderByDesc('created_at')

// Multi-column ordering
->orderBy('department')->orderByDesc('salary')

// Random order
->inRandomOrder()
->inRandomOrder(seed: 42)            // reproducible random
```

---

## Select & Distinct

```php
// Select specific columns
->select('id', 'name', 'email')

// Dot-notation + aliasing
->select('id', 'user.name as author', 'user.address.city as city')

// Add to existing selection
->addSelect('extra_col')

// Remove duplicates
->distinct()                         // full-item deduplication
->distinct('category')               // deduplicate by specific column
```

---

## Limit, Offset & Pagination

```php
->limit(10)   // or ->take(10)
->offset(20)  // or ->skip(20)
->forPage(page: 2, perPage: 10)

// Full pagination with metadata
$paginator = MemoryQueryBuilder::from($data)
    ->where('status', 'active')
    ->orderBy('name')
    ->paginate(perPage: 15, page: 1);

$paginator->total();          // total matching items
$paginator->perPage();        // items per page
$paginator->currentPage();    // current page number
$paginator->lastPage();       // number of last page
$paginator->from();           // first item index on this page (1-based)
$paginator->to();             // last item index on this page
$paginator->hasMorePages();   // bool
$paginator->items();          // array of items on this page
$paginator->toArray();        // ['total' => ..., 'per_page' => ..., 'data' => [...], ...]
json_encode($paginator);      // implements JsonSerializable
```

---

## Group By & Having

```php
$result = MemoryQueryBuilder::from($sales)
    ->groupBy('department')
    ->having('count', '>', 2)
    ->orHaving('department', 'Finance')
    ->get();

// Each group item includes:
// - the group-by column(s): $group['department']
// - '_group_key': composite key string
// - '_items': array of original items in the group
// - 'count': number of items in the group
foreach ($result as $group) {
    echo "{$group['department']}: {$group['count']} employees";
}
```

---

## Aggregations

```php
$q = MemoryQueryBuilder::from($orders)->where('status', 'paid');

$q->count();              // number of matching items
$q->count('column');      // count non-null values in column
$q->sum('amount');        // float
$q->avg('price');         // float (0 if empty)
$q->min('score');         // mixed (null if empty)
$q->max('score');         // mixed (null if empty)
```

---

## Execution Methods

```php
->get(): Collection                   // all matching items
->first(): mixed                      // first match or null
->firstOrFail(): mixed                // first match or throws ItemNotFoundException
->last(): mixed                       // last match or null
->find(42): mixed                     // find by primary key (default: 'id')
->find(42, 'uuid'): mixed             // find by custom key
->findOrFail(42): mixed               // find or throws ItemNotFoundException
->value('column'): mixed              // value from first match
->pluck('name'): Collection           // list of values
->pluck('name', 'id'): Collection     // assoc: id => name
->exists(): bool
->doesntExist(): bool
->chunk(100, fn($chunk) => ...): bool // process in batches; return false to stop
->toArray(): array
->toJson(): string
```

---

## Collection API

`get()` returns a `Collection` object that implements `ArrayAccess`, `IteratorAggregate`, `Countable`, and `JsonSerializable`.

```php
$col = MemoryQueryBuilder::from($data)->get();

// Transformation & Type Conversion
$col->map(fn($item) => $item['name']);
$col->filter(fn($item) => $item['active']);
$col->reduce(fn($carry, $item) => $carry + $item['score'], 0);
$col->each(fn($item) => processItem($item));  // return false to break
$col->transform(fn($item) => [...$item, 'extra' => true]);  // in-place
$col->toObjects();                           // convert array items to stdClass objects
$col->toObjects(recursive: true);            // deep convert nested arrays to objects
$col->toArrays();                            // convert objects to associative arrays
$col->toArrays(recursive: true);             // deep convert nested objects to arrays

// Sorting
$col->sortBy('name');
$col->sortByDesc('score');
$col->sortBy(fn($item) => strlen($item['name']));

// Grouping & Slicing
$col->groupBy('department');     // returns array<string, Collection>
$col->unique('email');
$col->take(5);
$col->skip(10);
$col->slice(offset: 2, length: 5);

// Search
$col->first();
$col->first(fn($item) => $item['role'] === 'admin');
$col->last();
$col->contains(fn($item) => $item['id'] === 42);
$col->contains('Alice');                         // simple value search

// Pluck
$col->pluck('name');
$col->pluck('name', 'id');

// Aggregations
$col->sum('amount');
$col->avg('score');
$col->min('price');
$col->max('price');
$col->count();
$col->isEmpty();
$col->isNotEmpty();

// Serialization
$col->toArray();
$col->toJson();
(string) $col;           // same as toJson()
json_encode($col);       // implements JsonSerializable

// ArrayAccess
$col[0];
$col[] = $newItem;
unset($col[2]);
isset($col[0]);

// Iterable
foreach ($col as $item) { ... }
```

---

## Running Tests

```bash
git clone https://github.com/SalvatoreCervone/memoryquerybuilder.git
cd memoryquerybuilder
composer install
vendor/bin/phpunit --testdox
```

```
OK (132 tests, 218 assertions)
```

---

## License

MIT — feel free to use, modify, and distribute.
