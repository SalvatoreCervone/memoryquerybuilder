<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Tests\Unit;

use MemoryQueryBuilder\MemoryQueryBuilder;
use PHPUnit\Framework\TestCase;

class AggregatesTest extends TestCase
{
    private array $orders;

    protected function setUp(): void
    {
        $this->orders = [
            ['id' => 1, 'product' => 'Apple',  'quantity' => 5,  'price' => 2.50, 'status' => 'paid'],
            ['id' => 2, 'product' => 'Banana', 'quantity' => 10, 'price' => 1.00, 'status' => 'paid'],
            ['id' => 3, 'product' => 'Cherry', 'quantity' => 2,  'price' => 5.00, 'status' => 'pending'],
            ['id' => 4, 'product' => 'Apple',  'quantity' => 8,  'price' => 2.50, 'status' => 'paid'],
            ['id' => 5, 'product' => 'Banana', 'quantity' => 3,  'price' => 1.00, 'status' => 'cancelled'],
            ['id' => 6, 'product' => 'Durian', 'quantity' => 1,  'price' => null, 'status' => 'paid'],
        ];
    }

    private function q(): MemoryQueryBuilder
    {
        return MemoryQueryBuilder::from($this->orders);
    }

    public function testCount(): void
    {
        $this->assertEquals(6, $this->q()->count());
    }

    public function testCountWithFilter(): void
    {
        $this->assertEquals(4, $this->q()->where('status', 'paid')->count());
    }

    public function testCountColumn(): void
    {
        // count non-null prices
        $this->assertEquals(5, $this->q()->count('price'));
    }

    public function testSum(): void
    {
        $this->assertEquals(29, $this->q()->sum('quantity'));
    }

    public function testSumWithFilter(): void
    {
        $total = $this->q()->where('status', 'paid')->sum('quantity');
        // 5 + 10 + 8 + 1 = 24
        $this->assertEquals(24, $total);
    }

    public function testAvg(): void
    {
        // (5+10+2+8+3+1) / 6 = 29/6 ≈ 4.833
        $avg = $this->q()->avg('quantity');
        $this->assertEqualsWithDelta(29 / 6, $avg, 0.001);
    }

    public function testAvgEmptyDataset(): void
    {
        $avg = $this->q()->where('id', 999)->avg('quantity');
        $this->assertEquals(0, $avg);
    }

    public function testMin(): void
    {
        $this->assertEquals(1, $this->q()->min('quantity'));
    }

    public function testMinWithFilter(): void
    {
        $min = $this->q()->where('product', 'Apple')->min('quantity');
        $this->assertEquals(5, $min);
    }

    public function testMax(): void
    {
        $this->assertEquals(10, $this->q()->max('quantity'));
    }

    public function testMaxPrice(): void
    {
        $this->assertEquals(5.00, $this->q()->max('price'));
    }

    public function testMinEmptyReturnsNull(): void
    {
        $this->assertNull($this->q()->where('id', 999)->min('quantity'));
    }

    public function testMaxEmptyReturnsNull(): void
    {
        $this->assertNull($this->q()->where('id', 999)->max('quantity'));
    }

    public function testValue(): void
    {
        $product = $this->q()->where('id', 1)->value('product');
        $this->assertEquals('Apple', $product);
    }

    public function testValueReturnsNullWhenNotFound(): void
    {
        $val = $this->q()->where('id', 999)->value('product');
        $this->assertNull($val);
    }

    public function testPluck(): void
    {
        $products = $this->q()->orderBy('id')->pluck('product');
        $this->assertEquals(['Apple', 'Banana', 'Cherry', 'Apple', 'Banana', 'Durian'], $products->toArray());
    }

    public function testPluckWithKey(): void
    {
        $keyed = $this->q()->pluck('product', 'id');
        $arr   = $keyed->toArray();
        // The Collection stores values; since pluck with key returns assoc, keys are strings/ints
        // But Collection::make wraps them with array_values, so we need to check the underlying assoc array
        // Let's verify via the raw MemoryQueryBuilder::pluck which preserves keys
        $this->assertEquals('Apple',  $arr[1]);
        $this->assertEquals('Banana', $arr[2]);
        $this->assertEquals('Durian', $arr[6]);
    }

    public function testPluckWithFilter(): void
    {
        $products = $this->q()->where('status', 'paid')->orderBy('id')->pluck('product');
        $this->assertEquals(['Apple', 'Banana', 'Apple', 'Durian'], $products->toArray());
    }

    public function testLast(): void
    {
        $item = $this->q()->orderBy('id')->last();
        $this->assertEquals('Durian', $item['product']);
    }

    public function testLastWithFilter(): void
    {
        $item = $this->q()->orderBy('id')->where('status', 'paid')->last();
        $this->assertEquals('Durian', $item['product']);
    }
}
