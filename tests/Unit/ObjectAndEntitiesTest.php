<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Tests\Unit;

use MemoryQueryBuilder\MemoryQueryBuilder;
use PHPUnit\Framework\TestCase;

class ObjectAndEntitiesTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Test data: stdClass objects
    // -------------------------------------------------------------------------

    private function makeStdClassData(): array
    {
        $a       = new \stdClass();
        $a->id   = 1;
        $a->name = 'Alice';
        $a->role = 'admin';
        $a->age  = 30;

        $b       = new \stdClass();
        $b->id   = 2;
        $b->name = 'Bob';
        $b->role = 'user';
        $b->age  = 17;

        $c       = new \stdClass();
        $c->id   = 3;
        $c->name = 'Carol';
        $c->role = 'editor';
        $c->age  = 25;

        return [$a, $b, $c];
    }

    // -------------------------------------------------------------------------
    // Test data: plain classes with getter methods
    // -------------------------------------------------------------------------

    private function makeEntityData(): array
    {
        return [
            new class(1, 'Alice', 'admin', 30) {
                public function __construct(
                    private int $id,
                    private string $name,
                    private string $role,
                    private int $age,
                ) {}
                public function getId(): int { return $this->id; }
                public function getName(): string { return $this->name; }
                public function getRole(): string { return $this->role; }
                public function getAge(): int { return $this->age; }
            },
            new class(2, 'Bob', 'user', 17) {
                public function __construct(
                    private int $id,
                    private string $name,
                    private string $role,
                    private int $age,
                ) {}
                public function getId(): int { return $this->id; }
                public function getName(): string { return $this->name; }
                public function getRole(): string { return $this->role; }
                public function getAge(): int { return $this->age; }
            },
            new class(3, 'Carol', 'editor', 25) {
                public function __construct(
                    private int $id,
                    private string $name,
                    private string $role,
                    private int $age,
                ) {}
                public function getId(): int { return $this->id; }
                public function getName(): string { return $this->name; }
                public function getRole(): string { return $this->role; }
                public function getAge(): int { return $this->age; }
            },
        ];
    }

    // -------------------------------------------------------------------------
    // Test data: nested arrays (dot-notation)
    // -------------------------------------------------------------------------

    private function makeNestedData(): array
    {
        return [
            [
                'id'   => 1,
                'user' => ['name' => 'Alice', 'address' => ['city' => 'Rome',   'zip' => '00100']],
                'tags' => ['admin', 'premium'],
            ],
            [
                'id'   => 2,
                'user' => ['name' => 'Bob',   'address' => ['city' => 'Milan',  'zip' => '20100']],
                'tags' => ['user'],
            ],
            [
                'id'   => 3,
                'user' => ['name' => 'Carol', 'address' => ['city' => 'Rome',   'zip' => '00200']],
                'tags' => ['user', 'premium'],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // stdClass tests
    // -------------------------------------------------------------------------

    public function testWhereOnStdClass(): void
    {
        $result = MemoryQueryBuilder::from($this->makeStdClassData())
            ->where('role', 'admin')
            ->get();
        $this->assertCount(1, $result);
        $this->assertEquals('Alice', $result->first()->name);
    }

    public function testOrderByOnStdClass(): void
    {
        $result = MemoryQueryBuilder::from($this->makeStdClassData())
            ->orderByDesc('age')
            ->get();
        $this->assertEquals('Alice', $result->toArray()[0]->name); // 30
        $this->assertEquals('Bob',   $result->toArray()[2]->name); // 17
    }

    public function testCountOnStdClass(): void
    {
        $count = MemoryQueryBuilder::from($this->makeStdClassData())
            ->where('age', '>=', 18)
            ->count();
        $this->assertEquals(2, $count);
    }

    // -------------------------------------------------------------------------
    // Entity/getter tests
    // -------------------------------------------------------------------------

    public function testWhereWithGetterObjects(): void
    {
        $result = MemoryQueryBuilder::from($this->makeEntityData())
            ->where('role', 'admin')
            ->get();
        $this->assertCount(1, $result);
        $this->assertEquals('Alice', $result->first()->getName());
    }

    public function testOrderByWithGetterObjects(): void
    {
        $result = MemoryQueryBuilder::from($this->makeEntityData())
            ->orderBy('age')
            ->get();
        $this->assertEquals('Bob', $result->first()->getName()); // youngest = 17
    }

    public function testSumWithGetterObjects(): void
    {
        $total = MemoryQueryBuilder::from($this->makeEntityData())
            ->sum('age');
        $this->assertEquals(72, $total); // 30 + 17 + 25
    }

    // -------------------------------------------------------------------------
    // Dot-notation / Nested array tests
    // -------------------------------------------------------------------------

    public function testDotNotationWhereOneLevel(): void
    {
        $result = MemoryQueryBuilder::from($this->makeNestedData())
            ->where('user.name', 'Alice')
            ->get();
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result->first()['id']);
    }

    public function testDotNotationWhereTwoLevels(): void
    {
        $result = MemoryQueryBuilder::from($this->makeNestedData())
            ->where('user.address.city', 'Rome')
            ->get();
        $this->assertCount(2, $result); // Alice and Carol
    }

    public function testDotNotationOrderBy(): void
    {
        $result = MemoryQueryBuilder::from($this->makeNestedData())
            ->orderBy('user.name')
            ->get();
        $first = $result->toArray()[0];
        $this->assertEquals('Alice', $first['user']['name']);
    }

    public function testDotNotationPluck(): void
    {
        $cities = MemoryQueryBuilder::from($this->makeNestedData())
            ->orderBy('id')
            ->pluck('user.address.city')
            ->toArray();
        $this->assertEquals(['Rome', 'Milan', 'Rome'], $cities);
    }

    public function testDotNotationSelectWithAlias(): void
    {
        $result = MemoryQueryBuilder::from($this->makeNestedData())
            ->select('id', 'user.name as username', 'user.address.city as city')
            ->orderBy('id')
            ->get();

        $first = $result->first();
        $this->assertEquals(1, $first['id']);
        $this->assertEquals('Alice', $first['username']);
        $this->assertEquals('Rome', $first['city']);
    }

    // -------------------------------------------------------------------------
    // Mixed data (array + stdClass together) – PropertyAccessor must handle both
    // -------------------------------------------------------------------------

    public function testMixedArrayAndStdClass(): void
    {
        $stdObj       = new \stdClass();
        $stdObj->id   = 10;
        $stdObj->name = 'Zara';
        $stdObj->age  = 28;

        $data = [
            ['id' => 1, 'name' => 'Alice', 'age' => 30],
            $stdObj,
            ['id' => 2, 'name' => 'Bob', 'age' => 17],
        ];

        $result = MemoryQueryBuilder::from($data)
            ->where('age', '>=', 18)
            ->orderBy('age')
            ->get();

        $this->assertCount(2, $result);

        // First = Zara (28), Second = Alice (30)
        $first  = $result->toArray()[0];
        $second = $result->toArray()[1];

        $this->assertEquals('Zara',  is_array($first) ? $first['name'] : $first->name);
        $this->assertEquals('Alice', is_array($second) ? $second['name'] : $second->name);
    }

    // -------------------------------------------------------------------------
    // Traversable input
    // -------------------------------------------------------------------------

    public function testFromGenerator(): void
    {
        $generator = (function () {
            yield ['id' => 1, 'val' => 10];
            yield ['id' => 2, 'val' => 20];
            yield ['id' => 3, 'val' => 30];
        })();

        $result = MemoryQueryBuilder::from($generator)
            ->where('val', '>', 10)
            ->get();

        $this->assertCount(2, $result);
    }
}
