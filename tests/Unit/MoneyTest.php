<?php

namespace Tests\Unit;

use App\Services\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_ratio_avoids_intermediate_overflow_and_rounds_half_up(): void
    {
        $this->assertSame(11000000000000, Money::ratio(100000000000000, 110000, 1000000));
        $this->assertSame(2, Money::ratio(3, 1, 2));
    }
}
