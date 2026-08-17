<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Tests\Unit;

use MemoryQueryBuilder\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    private array $data;

    protected function setUp(): void
    {
        $this->data = [
            ['id' => 1, 'name' => 'Alice', 'score' => 80, 'dept' => 'IT'],
            ['id' => 2, 'name' => 'Bob',   'score' => 55, 'dept' => 'HR'],
            ['id' => 3, 'name' => 'Carol', 'score' => 90, 'dept' => 'IT'],
            ['id' => 4, 'name' => 'Dave',  'score' => 70, 'dept' => 'HR'],
        ];
    }

    private function col(): Collection
    {
        return Collection::make($this->data);
    }

    // -------------------------------------------------------------------------
    // Core
    // -------------------------------------------------------------------------

    public function testCount(): void
    {
        $this->assertEquals(4, $this->col()->count());
    }

    public function testIsEmpty(): void
    {
        $this->assertFalse($this->col()->isEmpty());
        $this->assertTrue(Collection::make()->isEmpty());
    }

    public function testIsNotEmpty(): void
    {
        $this->assertTrue($this->col()->isNotEmpty());
    }

    public function testIterable(): void
    {
        $count = 0;
        foreach ($this->col() as $item) {
            $count++;
        }
        $this->assertEquals(4, $count);
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    public function testArrayAccess(): void
    {
        $col = $this->col();
        $this->assertEquals('Alice', $col[0]['name']);
        $this->assertTrue(isset($col[1]));
        $this->assertFalse(isset($col[99]));
    }

    public function testArrayAccessSet(): void
    {
        $col   = $this->col();
        $col[] = ['id' => 5, 'name' => 'Eve'];
        $this->assertEquals(5, $col->count());
    }

    public function testArrayAccessUnset(): void
    {
        $col = $this->col();
        unset($col[0]);
        $this->assertEquals(3, $col->count());
    }

    // -------------------------------------------------------------------------
    // Transformation
    // -------------------------------------------------------------------------

    public function testMap(): void
    {
        $names = $this->col()->map(fn($item) => $item['name'])->toArray();
        $this->assertEquals(['Alice', 'Bob', 'Carol', 'Dave'], $names);
    }

    public function testFilter(): void
    {
        $result = $this->col()->filter(fn($item) => $item['score'] >= 70);
        $this->assertCount(3, $result); // Alice (80), Carol (90), Dave (70)
    }

    public function testFilterWithoutCallback(): void
    {
        $data   = Collection::make([1, null, 2, false, 3, '']);
        $result = $data->filter();
        $this->assertCount(3, $result);
    }

    public function testReduce(): void
    {
        $total = $this->col()->reduce(fn($carry, $item) => $carry + $item['score'], 0);
        $this->assertEquals(295, $total);
    }

    public function testEach(): void
    {
        $names = [];
        $this->col()->each(function ($item) use (&$names) {
            $names[] = $item['name'];
        });
        $this->assertEquals(['Alice', 'Bob', 'Carol', 'Dave'], $names);
    }

    public function testEachBreaksOnFalse(): void
    {
        $count = 0;
        $this->col()->each(function ($item) use (&$count) {
            $count++;
            return false;
        });
        $this->assertEquals(1, $count);
    }

    public function testSortBy(): void
    {
        $sorted = $this->col()->sortBy('score')->map(fn($i) => $i['name'])->toArray();
        $this->assertEquals(['Bob', 'Dave', 'Alice', 'Carol'], $sorted);
    }

    public function testSortByDesc(): void
    {
        $sorted = $this->col()->sortByDesc('score')->map(fn($i) => $i['score'])->toArray();
        $this->assertEquals([90, 80, 70, 55], $sorted);
    }

    public function testGroupBy(): void
    {
        $groups = $this->col()->groupBy('dept');
        $this->assertArrayHasKey('IT', $groups);
        $this->assertArrayHasKey('HR', $groups);
        $this->assertCount(2, $groups['IT']);
        $this->assertCount(2, $groups['HR']);
    }

    public function testPluck(): void
    {
        $names = $this->col()->pluck('name')->toArray();
        $this->assertEquals(['Alice', 'Bob', 'Carol', 'Dave'], $names);
    }

    public function testPluckWithKey(): void
    {
        $keyed = $this->col()->pluck('name', 'id')->toArray();
        // keyed by 'id' values: 1 => Alice, 2 => Bob, 3 => Carol, 4 => Dave
        $this->assertEquals(['Alice', 'Bob', 'Carol', 'Dave'], array_values($keyed));
        $this->assertEquals([1, 2, 3, 4], array_keys($keyed));
    }

    // -------------------------------------------------------------------------
    // Retrieval
    // -------------------------------------------------------------------------

    public function testFirst(): void
    {
        $this->assertEquals('Alice', $this->col()->first()['name']);
    }

    public function testFirstWithCallback(): void
    {
        $result = $this->col()->first(fn($item) => $item['dept'] === 'HR');
        $this->assertEquals('Bob', $result['name']);
    }

    public function testFirstDefault(): void
    {
        $result = $this->col()->first(fn($item) => $item['score'] > 999, 'fallback');
        $this->assertEquals('fallback', $result);
    }

    public function testLast(): void
    {
        $this->assertEquals('Dave', $this->col()->last()['name']);
    }

    public function testLastWithCallback(): void
    {
        $result = $this->col()->last(fn($item) => $item['dept'] === 'HR');
        $this->assertEquals('Dave', $result['name']);
    }

    public function testTake(): void
    {
        $result = $this->col()->take(2);
        $this->assertCount(2, $result);
    }

    public function testSkip(): void
    {
        $result = $this->col()->skip(3);
        $this->assertCount(1, $result);
        $this->assertEquals('Dave', $result->first()['name']);
    }

    public function testContains(): void
    {
        $this->assertTrue($this->col()->contains(fn($item) => $item['name'] === 'Carol'));
        $this->assertFalse($this->col()->contains(fn($item) => $item['name'] === 'Zara'));
    }

    public function testUnique(): void
    {
        $data   = Collection::make([['dept' => 'IT'], ['dept' => 'HR'], ['dept' => 'IT']]);
        $result = $data->unique('dept');
        $this->assertCount(2, $result);
    }

    // -------------------------------------------------------------------------
    // Aggregation
    // -------------------------------------------------------------------------

    public function testSum(): void
    {
        $this->assertEquals(295, $this->col()->sum('score'));
    }

    public function testAvg(): void
    {
        $this->assertEquals(295 / 4, $this->col()->avg('score'));
    }

    public function testMin(): void
    {
        $this->assertEquals(55, $this->col()->min('score'));
    }

    public function testMax(): void
    {
        $this->assertEquals(90, $this->col()->max('score'));
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function testToJson(): void
    {
        $json = $this->col()->toJson();
        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertCount(4, $decoded);
    }

    public function testToString(): void
    {
        $col = $this->col();
        $this->assertJson((string) $col);
    }

    public function testToObjects(): void
    {
        $objectsCol = $this->col()->toObjects();
        $first = $objectsCol->first();

        $this->assertIsObject($first);
        $this->assertEquals(1, $first->id);
        $this->assertEquals('Alice', $first->name);

        // Idempotent if already objects
        $sameCol = $objectsCol->toObjects();
        $this->assertIsObject($sameCol->first());
        $this->assertEquals('Alice', $sameCol->first()->name);
    }

    public function testToObjectsRecursive(): void
    {
        $data = [
            ['id' => 1, 'meta' => ['role' => 'admin', 'details' => ['level' => 5]]],
        ];
        $col = Collection::make($data)->toObjects(recursive: true);
        $first = $col->first();

        $this->assertIsObject($first);
        $this->assertIsObject($first->meta);
        $this->assertIsObject($first->meta->details);
        $this->assertEquals(5, $first->meta->details->level);
    }

    public function testToArrays(): void
    {
        $obj1 = (object) ['id' => 1, 'name' => 'Alice'];
        $obj2 = (object) ['id' => 2, 'name' => 'Bob'];

        $col = Collection::make([$obj1, $obj2])->toArrays();
        $first = $col->first();

        $this->assertIsArray($first);
        $this->assertEquals(1, $first['id']);
        $this->assertEquals('Alice', $first['name']);

        // Idempotent if already arrays
        $sameCol = $col->toArrays();
        $this->assertIsArray($sameCol->first());
        $this->assertEquals('Alice', $sameCol->first()['name']);
    }

    public function testToArraysRecursive(): void
    {
        $obj = (object) [
            'id' => 1,
            'meta' => (object) ['role' => 'admin', 'details' => (object) ['level' => 5]],
        ];
        $col = Collection::make([$obj])->toArrays(recursive: true);
        $first = $col->first();

        $this->assertIsArray($first);
        $this->assertIsArray($first['meta']);
        $this->assertIsArray($first['meta']['details']);
        $this->assertEquals(5, $first['meta']['details']['level']);
    }

    public function testObjectArrayAliases(): void
    {
        $data = [['id' => 1, 'name' => 'Alice']];

        $this->assertIsObject(Collection::make($data)->toObject()->first());
        $this->assertIsArray(Collection::make([(object) ['id' => 1]])->toAssocArray()->first());
        $this->assertIsArray(Collection::make([(object) ['id' => 1]])->toAssoc()->first());
    }
}
