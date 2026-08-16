<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Tests\Unit;

use MemoryQueryBuilder\MemoryQueryBuilder;
use PHPUnit\Framework\TestCase;

class GroupByHavingTest extends TestCase
{
    private array $sales;

    protected function setUp(): void
    {
        $this->sales = [
            ['id' => 1, 'department' => 'IT',      'employee' => 'Alice', 'amount' => 1500],
            ['id' => 2, 'department' => 'IT',      'employee' => 'Bob',   'amount' => 800],
            ['id' => 3, 'department' => 'HR',      'employee' => 'Carol', 'amount' => 1200],
            ['id' => 4, 'department' => 'IT',      'employee' => 'Dave',  'amount' => 2000],
            ['id' => 5, 'department' => 'HR',      'employee' => 'Eve',   'amount' => 900],
            ['id' => 6, 'department' => 'Finance', 'employee' => 'Frank', 'amount' => 3000],
        ];
    }

    private function q(): MemoryQueryBuilder
    {
        return MemoryQueryBuilder::from($this->sales);
    }

    public function testGroupByCreatesGroups(): void
    {
        $result = $this->q()->groupBy('department')->get();
        // 3 distinct departments
        $this->assertCount(3, $result);
    }

    public function testGroupByPreservesGroupKey(): void
    {
        $result = $this->q()->groupBy('department')->get()->toArray();
        $departments = array_column($result, 'department');
        sort($departments);
        $this->assertEquals(['Finance', 'HR', 'IT'], $departments);
    }

    public function testGroupByCountColumn(): void
    {
        $result = $this->q()->groupBy('department')->get()->toArray();
        $counts = [];
        foreach ($result as $group) {
            $counts[$group['department']] = $group['count'];
        }

        $this->assertEquals(3, $counts['IT']);
        $this->assertEquals(2, $counts['HR']);
        $this->assertEquals(1, $counts['Finance']);
    }

    public function testGroupByItemsContainOriginalData(): void
    {
        $result = $this->q()->groupBy('department')->get()->toArray();
        foreach ($result as $group) {
            if ($group['department'] === 'IT') {
                $this->assertCount(3, $group['_items']);
                return;
            }
        }
        $this->fail('IT department group not found.');
    }

    public function testHavingFiltersGroups(): void
    {
        // Only departments with count > 1
        $result = $this->q()
            ->groupBy('department')
            ->having('count', '>', 1)
            ->get();

        $this->assertCount(2, $result); // IT (3) and HR (2), not Finance (1)
    }

    public function testHavingEquality(): void
    {
        $result = $this->q()
            ->groupBy('department')
            ->having('count', 1)
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Finance', $result->first()['department']);
    }

    public function testOrHaving(): void
    {
        $result = $this->q()
            ->groupBy('department')
            ->having('count', '>', 2)
            ->orHaving('department', 'Finance')
            ->get();

        $this->assertCount(2, $result); // IT (count=3) and Finance (department match)
    }

    public function testGroupByWithWhereFilter(): void
    {
        // Group only the IT department employees (should have 1 group)
        $result = $this->q()
            ->where('department', 'IT')
            ->groupBy('department')
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('IT', $result->first()['department']);
        $this->assertEquals(3, $result->first()['count']);
    }
}
