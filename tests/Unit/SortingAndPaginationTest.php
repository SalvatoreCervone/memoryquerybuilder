<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Tests\Unit;

use MemoryQueryBuilder\MemoryQueryBuilder;
use PHPUnit\Framework\TestCase;

class SortingAndPaginationTest extends TestCase
{
    private array $products;

    protected function setUp(): void
    {
        $this->products = [
            ['id' => 1, 'name' => 'Banana',  'price' => 1.20, 'category' => 'fruit',    'stock' => 50],
            ['id' => 2, 'name' => 'Apple',   'price' => 0.80, 'category' => 'fruit',    'stock' => 10],
            ['id' => 3, 'name' => 'Carrot',  'price' => 0.60, 'category' => 'vegetable','stock' => 30],
            ['id' => 4, 'name' => 'Zucchini','price' => 1.50, 'category' => 'vegetable','stock' => 20],
            ['id' => 5, 'name' => 'Cherry',  'price' => 3.00, 'category' => 'fruit',    'stock' => 5],
            ['id' => 6, 'name' => 'Broccoli','price' => 2.00, 'category' => 'vegetable','stock' => 15],
        ];
    }

    private function q(): MemoryQueryBuilder
    {
        return MemoryQueryBuilder::from($this->products);
    }

    // -------------------------------------------------------------------------
    // Ordering
    // -------------------------------------------------------------------------

    public function testOrderByAsc(): void
    {
        $result = $this->q()->orderBy('price')->get();
        $prices = array_column($result->toArray(), 'price');
        $this->assertEquals([0.60, 0.80, 1.20, 1.50, 2.00, 3.00], $prices);
    }

    public function testOrderByDesc(): void
    {
        $result = $this->q()->orderByDesc('price')->get();
        $prices = array_column($result->toArray(), 'price');
        $this->assertEquals([3.00, 2.00, 1.50, 1.20, 0.80, 0.60], $prices);
    }

    public function testMultiColumnOrderBy(): void
    {
        $result = $this->q()
            ->orderBy('category')      // fruit < vegetable
            ->orderBy('price', 'desc') // within category, price desc
            ->get();

        $items = $result->toArray();

        // First 3 should be fruit, sorted by price desc
        $this->assertEquals('fruit', $items[0]['category']);
        $this->assertEquals('Cherry', $items[0]['name']); // 3.00
        $this->assertEquals('Banana', $items[1]['name']); // 1.20
        $this->assertEquals('Apple',  $items[2]['name']); // 0.80

        // Last 3 should be vegetable, sorted by price desc
        $this->assertEquals('vegetable', $items[3]['category']);
        $this->assertEquals('Broccoli',  $items[3]['name']); // 2.00
    }

    public function testOrderByString(): void
    {
        $result = $this->q()->orderBy('name')->get();
        $names  = array_column($result->toArray(), 'name');
        $this->assertEquals('Apple', $names[0]);
        $this->assertEquals('Banana', $names[1]);
        $this->assertEquals('Broccoli', $names[2]);
    }

    public function testInRandomOrder(): void
    {
        // Can't test actual randomness, just ensure we get all items back
        $result = $this->q()->inRandomOrder()->get();
        $this->assertCount(6, $result);
    }

    public function testInRandomOrderWithSeed(): void
    {
        // Same seed → same order
        $result1 = $this->q()->inRandomOrder(42)->get()->toArray();
        $result2 = $this->q()->inRandomOrder(42)->get()->toArray();
        $this->assertEquals($result1, $result2);
    }

    // -------------------------------------------------------------------------
    // Limit / Offset / Skip / Take
    // -------------------------------------------------------------------------

    public function testLimit(): void
    {
        $result = $this->q()->orderBy('id')->limit(3)->get();
        $this->assertCount(3, $result);
        $this->assertEquals(1, $result->toArray()[0]['id']);
        $this->assertEquals(3, $result->toArray()[2]['id']);
    }

    public function testTakeAlias(): void
    {
        $result = $this->q()->take(2)->get();
        $this->assertCount(2, $result);
    }

    public function testOffset(): void
    {
        $result = $this->q()->orderBy('id')->offset(3)->get();
        $this->assertCount(3, $result);
        $this->assertEquals(4, $result->toArray()[0]['id']);
    }

    public function testSkipAlias(): void
    {
        $result = $this->q()->orderBy('id')->skip(5)->get();
        $this->assertCount(1, $result);
        $this->assertEquals(6, $result->toArray()[0]['id']);
    }

    public function testLimitWithOffset(): void
    {
        $result = $this->q()->orderBy('id')->offset(1)->limit(3)->get();
        $ids    = array_column($result->toArray(), 'id');
        $this->assertEquals([2, 3, 4], $ids);
    }

    public function testForPage(): void
    {
        $result = $this->q()->orderBy('id')->forPage(2, 2)->get();
        $ids    = array_column($result->toArray(), 'id');
        $this->assertEquals([3, 4], $ids);
    }

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    public function testPaginateMetadata(): void
    {
        $paginator = $this->q()->orderBy('id')->paginate(2, 1);

        $this->assertEquals(6, $paginator->total());
        $this->assertEquals(2, $paginator->perPage());
        $this->assertEquals(1, $paginator->currentPage());
        $this->assertEquals(3, $paginator->lastPage());
        $this->assertEquals(1, $paginator->from());
        $this->assertEquals(2, $paginator->to());
        $this->assertTrue($paginator->hasMorePages());
    }

    public function testPaginateSecondPage(): void
    {
        $paginator = $this->q()->orderBy('id')->paginate(2, 2);
        $ids       = array_column($paginator->items(), 'id');

        $this->assertEquals([3, 4], $ids);
        $this->assertEquals(2, $paginator->currentPage());
        $this->assertEquals(3, $paginator->from());
        $this->assertEquals(4, $paginator->to());
    }

    public function testPaginateLastPage(): void
    {
        $paginator = $this->q()->orderBy('id')->paginate(2, 3);
        $this->assertFalse($paginator->hasMorePages());
        $this->assertEquals(3, $paginator->lastPage());
        $this->assertEquals(5, $paginator->from());
        $this->assertEquals(6, $paginator->to());
    }

    public function testPaginateToArray(): void
    {
        $paginator = $this->q()->paginate(3, 1);
        $array     = $paginator->toArray();

        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('per_page', $array);
        $this->assertArrayHasKey('current_page', $array);
        $this->assertArrayHasKey('last_page', $array);
        $this->assertArrayHasKey('from', $array);
        $this->assertArrayHasKey('to', $array);
        $this->assertArrayHasKey('has_more_pages', $array);
        $this->assertArrayHasKey('data', $array);
    }

    public function testPaginateWithFilter(): void
    {
        $paginator = $this->q()->where('category', 'fruit')->orderBy('price')->paginate(2, 1);
        $this->assertEquals(3, $paginator->total());
        $this->assertCount(2, $paginator->items());
    }

    // -------------------------------------------------------------------------
    // Select / Distinct
    // -------------------------------------------------------------------------

    public function testSelect(): void
    {
        $result = $this->q()->select('name', 'price')->get();
        $first  = $result->first();

        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('price', $first);
        $this->assertArrayNotHasKey('id', $first);
        $this->assertArrayNotHasKey('category', $first);
    }

    public function testSelectWithAlias(): void
    {
        $result = $this->q()->select('name', 'price as cost')->get();
        $first  = $result->first();

        $this->assertArrayHasKey('cost', $first);
        $this->assertArrayNotHasKey('price', $first);
    }

    public function testDistinct(): void
    {
        $result = $this->q()->select('category')->distinct()->get();
        $categories = array_column($result->toArray(), 'category');
        $this->assertCount(2, $categories);
        $this->assertContains('fruit', $categories);
        $this->assertContains('vegetable', $categories);
    }

    // -------------------------------------------------------------------------
    // Chunk
    // -------------------------------------------------------------------------

    public function testChunk(): void
    {
        $chunks = [];
        $this->q()->orderBy('id')->chunk(2, function ($chunk) use (&$chunks) {
            $chunks[] = $chunk->count();
        });

        $this->assertEquals([2, 2, 2], $chunks);
    }

    public function testChunkStopsOnFalse(): void
    {
        $processed = 0;
        $this->q()->orderBy('id')->chunk(2, function ($chunk) use (&$processed) {
            $processed++;
            return false; // stop after first chunk
        });

        $this->assertEquals(1, $processed);
    }
}
