<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MemoryQueryBuilder\MemoryQueryBuilder;

// ─────────────────────────────────────────────────────────────────────────────
// Sample Dataset: E-commerce orders
// ─────────────────────────────────────────────────────────────────────────────

$orders = [
    ['id' => 1, 'customer' => 'Alice',   'product' => 'Laptop',   'category' => 'Electronics', 'amount' => 1200.00, 'qty' => 1, 'status' => 'paid',      'created_at' => '2024-03-15'],
    ['id' => 2, 'customer' => 'Bob',     'product' => 'Mouse',    'category' => 'Electronics', 'amount' => 29.99,   'qty' => 3, 'status' => 'paid',      'created_at' => '2024-03-18'],
    ['id' => 3, 'customer' => 'Carol',   'product' => 'Desk',     'category' => 'Furniture',   'amount' => 450.00,  'qty' => 1, 'status' => 'pending',   'created_at' => '2024-04-01'],
    ['id' => 4, 'customer' => 'Dave',    'product' => 'Chair',    'category' => 'Furniture',   'amount' => 320.00,  'qty' => 2, 'status' => 'paid',      'created_at' => '2024-04-10'],
    ['id' => 5, 'customer' => 'Eve',     'product' => 'Monitor',  'category' => 'Electronics', 'amount' => 350.00,  'qty' => 2, 'status' => 'shipped',   'created_at' => '2024-04-15'],
    ['id' => 6, 'customer' => 'Frank',   'product' => 'Keyboard', 'category' => 'Electronics', 'amount' => 89.99,   'qty' => 1, 'status' => 'cancelled', 'created_at' => '2024-05-01'],
    ['id' => 7, 'customer' => 'Grace',   'product' => 'Lamp',     'category' => 'Furniture',   'amount' => 75.00,   'qty' => 3, 'status' => 'paid',      'created_at' => '2024-05-10'],
    ['id' => 8, 'customer' => 'Alice',   'product' => 'Headset',  'category' => 'Electronics', 'amount' => 199.00,  'qty' => 1, 'status' => 'paid',      'created_at' => '2024-05-20'],
    ['id' => 9, 'customer' => 'Bob',     'product' => 'Bookshelf','category' => 'Furniture',   'amount' => 240.00,  'qty' => 1, 'status' => 'pending',   'created_at' => '2024-06-01'],
    ['id' => 10,'customer' => 'Carol',   'product' => 'Webcam',   'category' => 'Electronics', 'amount' => 119.00,  'qty' => 2, 'status' => 'paid',      'created_at' => '2024-06-15'],
];

function hr(string $title): void {
    echo "\n" . str_repeat('─', 60) . "\n";
    echo "  {$title}\n";
    echo str_repeat('─', 60) . "\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Basic filtering
// ─────────────────────────────────────────────────────────────────────────────
hr('1. Paid Electronics orders, sorted by amount DESC');

$result = MemoryQueryBuilder::from($orders)
    ->where('status', 'paid')
    ->where('category', 'Electronics')
    ->orderByDesc('amount')
    ->get();

foreach ($result as $order) {
    printf("  #%d %-12s %-10s $%.2f\n", $order['id'], $order['customer'], $order['product'], $order['amount']);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Nested WHERE (complex conditions)
// ─────────────────────────────────────────────────────────────────────────────
hr('2. Paid OR (Furniture with amount < 300)');

$result = MemoryQueryBuilder::from($orders)
    ->where('status', 'paid')
    ->orWhere(function (MemoryQueryBuilder $q) {
        $q->where('category', 'Furniture')
          ->where('amount', '<', 300);
    })
    ->orderBy('id')
    ->get();

foreach ($result as $order) {
    printf("  #%d %-12s %-10s %-10s $%.2f\n", $order['id'], $order['customer'], $order['product'], $order['status'], $order['amount']);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Aggregations
// ─────────────────────────────────────────────────────────────────────────────
hr('3. Aggregations on paid orders');

$paid = MemoryQueryBuilder::from($orders)->where('status', 'paid');

printf("  Total orders : %d\n", $paid->count());
printf("  Total revenue: $%.2f\n", $paid->sum('amount'));
printf("  Avg amount   : $%.2f\n", $paid->avg('amount'));
printf("  Max amount   : $%.2f\n", $paid->max('amount'));
printf("  Min amount   : $%.2f\n", $paid->min('amount'));

// ─────────────────────────────────────────────────────────────────────────────
// 4. GroupBy + Having
// ─────────────────────────────────────────────────────────────────────────────
hr('4. Group by category, show groups with count > 2');

$result = MemoryQueryBuilder::from($orders)
    ->groupBy('category')
    ->having('count', '>', 2)
    ->orderBy('category')
    ->get();

foreach ($result as $group) {
    printf("  %-15s  %d orders\n", $group['category'], $group['count']);
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Select with aliasing and dot-notation
// ─────────────────────────────────────────────────────────────────────────────
hr('5. Select specific columns with alias');

$result = MemoryQueryBuilder::from($orders)
    ->where('status', 'paid')
    ->select('id', 'customer as buyer', 'amount as total')
    ->orderByDesc('amount')
    ->limit(3)
    ->get();

foreach ($result as $row) {
    printf("  #%d  %-12s  $%.2f\n", $row['id'], $row['buyer'], $row['total']);
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. Pagination
// ─────────────────────────────────────────────────────────────────────────────
hr('6. Pagination — page 1 of 3 items per page');

$paginator = MemoryQueryBuilder::from($orders)
    ->orderBy('id')
    ->paginate(3, 1);

echo "  Total: {$paginator->total()} | Page {$paginator->currentPage()} of {$paginator->lastPage()} | Showing {$paginator->from()}-{$paginator->to()}\n";
foreach ($paginator->items() as $order) {
    printf("  #%d %s\n", $order['id'], $order['product']);
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. Pluck
// ─────────────────────────────────────────────────────────────────────────────
hr('7. Pluck customer names of paid orders');

$customers = MemoryQueryBuilder::from($orders)
    ->where('status', 'paid')
    ->orderBy('customer')
    ->pluck('customer')
    ->unique()
    ->toArray();

echo "  " . implode(', ', $customers) . "\n";

// ─────────────────────────────────────────────────────────────────────────────
// 8. whereBetween + whereIn
// ─────────────────────────────────────────────────────────────────────────────
hr('8. Amount between $100-$500 AND category in [Electronics, Furniture]');

$result = MemoryQueryBuilder::from($orders)
    ->whereBetween('amount', [100, 500])
    ->whereIn('category', ['Electronics', 'Furniture'])
    ->orderBy('amount')
    ->get();

foreach ($result as $order) {
    printf("  %-12s  %-14s  $%.2f\n", $order['product'], $order['category'], $order['amount']);
}

// ─────────────────────────────────────────────────────────────────────────────
// 9. when() conditional query
// ─────────────────────────────────────────────────────────────────────────────
hr('9. Conditional query with when()');

$filterStatus = 'paid';
$minAmount    = 200;

$result = MemoryQueryBuilder::from($orders)
    ->when($filterStatus, fn($q, $s) => $q->where('status', $s))
    ->when($minAmount, fn($q, $m) => $q->where('amount', '>=', $m))
    ->orderByDesc('amount')
    ->get();

echo "  Found {$result->count()} orders\n";
foreach ($result as $order) {
    printf("  #%d %-10s $%.2f\n", $order['id'], $order['customer'], $order['amount']);
}

// ─────────────────────────────────────────────────────────────────────────────
// 10. Chunk processing
// ─────────────────────────────────────────────────────────────────────────────
hr('10. Process results in chunks of 3');

$chunkNum = 0;
MemoryQueryBuilder::from($orders)
    ->orderBy('id')
    ->chunk(3, function ($chunk) use (&$chunkNum) {
        $chunkNum++;
        $ids = array_column($chunk->toArray(), 'id');
        printf("  Chunk %d: orders #%s\n", $chunkNum, implode(', #', $ids));
    });

echo "\n✅ Demo complete!\n\n";
