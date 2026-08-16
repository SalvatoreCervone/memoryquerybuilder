<?php

declare(strict_types=1);

namespace MemoryQueryBuilder\Tests\Unit;

use MemoryQueryBuilder\Exceptions\ItemNotFoundException;
use MemoryQueryBuilder\MemoryQueryBuilder;
use PHPUnit\Framework\TestCase;

class WhereClausesTest extends TestCase
{
    private array $users;

    protected function setUp(): void
    {
        $this->users = [
            ['id' => 1, 'name' => 'Alice',   'age' => 30, 'role' => 'admin',  'status' => 'active',   'email' => 'alice@example.com', 'score' => 90],
            ['id' => 2, 'name' => 'Bob',     'age' => 17, 'role' => 'user',   'status' => 'inactive', 'email' => 'bob@example.com',   'score' => 45],
            ['id' => 3, 'name' => 'Charlie', 'age' => 25, 'role' => 'editor', 'status' => 'active',   'email' => 'charlie@test.org',  'score' => 70],
            ['id' => 4, 'name' => 'Diana',   'age' => 22, 'role' => 'user',   'status' => 'active',   'email' => 'diana@example.com', 'score' => 55],
            ['id' => 5, 'name' => 'Eve',     'age' => 35, 'role' => 'admin',  'status' => 'inactive', 'email' => 'eve@test.org',      'score' => null],
        ];
    }

    private function q(): MemoryQueryBuilder
    {
        return MemoryQueryBuilder::from($this->users);
    }

    // -------------------------------------------------------------------------
    // Basic where()
    // -------------------------------------------------------------------------

    public function testWhereEquals(): void
    {
        $result = $this->q()->where('status', 'active')->get();
        $this->assertCount(3, $result);
    }

    public function testWhereTwoArguments(): void
    {
        $result = $this->q()->where('role', 'admin')->get();
        $this->assertCount(2, $result);
    }

    public function testWhereGreaterThan(): void
    {
        $result = $this->q()->where('age', '>', 25)->get();
        $this->assertCount(2, $result); // Alice (30), Eve (35)
    }

    public function testWhereLessThanOrEqual(): void
    {
        $result = $this->q()->where('age', '<=', 22)->get();
        $this->assertCount(2, $result); // Bob (17), Diana (22)
    }

    public function testWhereNotEqual(): void
    {
        $result = $this->q()->where('role', '!=', 'admin')->get();
        $this->assertCount(3, $result);
    }

    // -------------------------------------------------------------------------
    // orWhere()
    // -------------------------------------------------------------------------

    public function testOrWhere(): void
    {
        $result = $this->q()
            ->where('role', 'admin')
            ->orWhere('role', 'editor')
            ->get();
        $this->assertCount(3, $result);
    }

    public function testOrWhereOnMultipleConditions(): void
    {
        $result = $this->q()
            ->where('age', '>', 30)
            ->orWhere('age', '<', 18)
            ->get();
        $this->assertCount(2, $result); // Eve (35), Bob (17)
    }

    // -------------------------------------------------------------------------
    // Nested where() with Closure
    // -------------------------------------------------------------------------

    public function testNestedWhere(): void
    {
        // active AND (admin OR score >= 70)
        $result = $this->q()
            ->where('status', 'active')
            ->where(function (MemoryQueryBuilder $q) {
                $q->where('role', 'admin')->orWhere('score', '>=', 70);
            })
            ->get();

        // Alice: active + admin ✓
        // Charlie: active + score=70 ✓
        // Diana: active but user and score=55 ✗
        $this->assertCount(2, $result);
        $names = array_column($result->toArray(), 'name');
        $this->assertContains('Alice', $names);
        $this->assertContains('Charlie', $names);
    }

    // -------------------------------------------------------------------------
    // whereIn / whereNotIn
    // -------------------------------------------------------------------------

    public function testWhereIn(): void
    {
        $result = $this->q()->whereIn('role', ['admin', 'editor'])->get();
        $this->assertCount(3, $result);
    }

    public function testOrWhereIn(): void
    {
        $result = $this->q()
            ->where('status', 'inactive')
            ->orWhereIn('role', ['editor'])
            ->get();
        // Bob (inactive), Eve (inactive), Charlie (editor)
        $this->assertCount(3, $result);
    }

    public function testWhereNotIn(): void
    {
        $result = $this->q()->whereNotIn('role', ['admin'])->get();
        $this->assertCount(3, $result);
    }

    // -------------------------------------------------------------------------
    // whereNull / whereNotNull
    // -------------------------------------------------------------------------

    public function testWhereNull(): void
    {
        $result = $this->q()->whereNull('score')->get();
        $this->assertCount(1, $result);
        $this->assertEquals('Eve', $result->first()['name']);
    }

    public function testWhereNotNull(): void
    {
        $result = $this->q()->whereNotNull('score')->get();
        $this->assertCount(4, $result);
    }

    // -------------------------------------------------------------------------
    // whereBetween / whereNotBetween
    // -------------------------------------------------------------------------

    public function testWhereBetween(): void
    {
        $result = $this->q()->whereBetween('age', [20, 30])->get();
        // Alice (30), Charlie (25), Diana (22)
        $this->assertCount(3, $result);
    }

    public function testWhereNotBetween(): void
    {
        $result = $this->q()->whereNotBetween('age', [20, 30])->get();
        // Bob (17), Eve (35)
        $this->assertCount(2, $result);
    }

    // -------------------------------------------------------------------------
    // whereLike / contains / startsWith / endsWith
    // -------------------------------------------------------------------------

    public function testWhereLike(): void
    {
        $result = $this->q()->whereLike('email', '%example.com')->get();
        $this->assertCount(3, $result);
    }

    public function testWhereLikeCaseSensitive(): void
    {
        // Uppercase won't match lowercase with caseSensitive=true
        $result = $this->q()->whereLike('name', 'alice', caseSensitive: true)->get();
        $this->assertCount(0, $result);

        $result = $this->q()->whereLike('name', 'Alice', caseSensitive: true)->get();
        $this->assertCount(1, $result);
    }

    public function testWhereLikeCaseInsensitive(): void
    {
        $result = $this->q()->whereLike('name', 'alice', caseSensitive: false)->get();
        $this->assertCount(1, $result);
    }

    public function testWhereContains(): void
    {
        $result = $this->q()->whereContains('email', 'test.org')->get();
        $this->assertCount(2, $result);
    }

    public function testWhereStartsWith(): void
    {
        $result = $this->q()->whereStartsWith('name', 'A')->get();
        $this->assertCount(1, $result);
        $this->assertEquals('Alice', $result->first()['name']);
    }

    public function testWhereEndsWith(): void
    {
        $result = $this->q()->whereEndsWith('email', '.org')->get();
        $this->assertCount(2, $result);
    }

    // -------------------------------------------------------------------------
    // when() / unless()
    // -------------------------------------------------------------------------

    public function testWhenTrue(): void
    {
        $search = 'admin';
        $result = $this->q()
            ->when($search, fn($q, $v) => $q->where('role', $v))
            ->get();
        $this->assertCount(2, $result);
    }

    public function testWhenFalse(): void
    {
        $search = null;
        $result = $this->q()
            ->when($search, fn($q, $v) => $q->where('role', $v))
            ->get();
        // No where applied → all 5
        $this->assertCount(5, $result);
    }

    public function testUnless(): void
    {
        $isAdmin = false;
        $result  = $this->q()
            ->unless($isAdmin, fn($q) => $q->where('role', 'user'))
            ->get();
        $this->assertCount(2, $result);
    }

    // -------------------------------------------------------------------------
    // firstOrFail
    // -------------------------------------------------------------------------

    public function testFirstOrFailThrows(): void
    {
        $this->expectException(ItemNotFoundException::class);
        $this->q()->where('id', 999)->firstOrFail();
    }

    public function testFirstOrFailReturns(): void
    {
        $result = $this->q()->where('id', 1)->firstOrFail();
        $this->assertEquals('Alice', $result['name']);
    }

    // -------------------------------------------------------------------------
    // find / findOrFail
    // -------------------------------------------------------------------------

    public function testFind(): void
    {
        $result = $this->q()->find(3);
        $this->assertNotNull($result);
        $this->assertEquals('Charlie', $result['name']);
    }

    public function testFindReturnsNullForMissing(): void
    {
        $result = $this->q()->find(999);
        $this->assertNull($result);
    }

    public function testFindOrFail(): void
    {
        $this->expectException(ItemNotFoundException::class);
        $this->q()->findOrFail(999);
    }

    // -------------------------------------------------------------------------
    // exists / doesntExist
    // -------------------------------------------------------------------------

    public function testExists(): void
    {
        $this->assertTrue($this->q()->where('role', 'admin')->exists());
        $this->assertFalse($this->q()->where('role', 'superadmin')->exists());
    }

    public function testDoesntExist(): void
    {
        $this->assertTrue($this->q()->where('role', 'superadmin')->doesntExist());
        $this->assertFalse($this->q()->where('role', 'admin')->doesntExist());
    }
}
