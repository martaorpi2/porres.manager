<?php

namespace Tests\Unit;

use App\Services\QuotaCollectionReconcile;
use PHPUnit\Framework\TestCase;

class QuotaCollectionReconcileTest extends TestCase
{
    public function test_same_orders_and_amounts_do_not_differ(): void
    {
        $amounts = [
            10 => [10000000, 0, 10000000],
            11 => [84400000, 0, 84400000],
        ];

        $this->assertFalse(QuotaCollectionReconcile::differs($amounts, $amounts));
    }

    public function test_a_lower_collected_amount_differs(): void
    {
        $stored = [10 => [94400000, 0, 94400000]];
        $desired = [10 => [84400000, 0, 84400000]];

        $this->assertTrue(QuotaCollectionReconcile::differs($stored, $desired));
    }

    public function test_a_removed_order_differs(): void
    {
        $stored = [
            10 => [10000000, 0, 10000000],
            11 => [5000000, 0, 5000000],
        ];
        $desired = [10 => [10000000, 0, 10000000]];

        $this->assertTrue(QuotaCollectionReconcile::differs($stored, $desired));
    }
}
