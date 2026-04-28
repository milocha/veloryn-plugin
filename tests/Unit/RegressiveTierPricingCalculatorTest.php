<?php

declare(strict_types=1);

namespace Plugins\VelorynPlugin\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Plugins\VelorynPlugin\Services\RegressiveTierPricingCalculator;

/**
 * RegressiveTierPricingCalculatorTest — testy jednostkowe kalkulatora cennika regresywnego.
 *
 * Weryfikuje kumulatywną logikę tier'ów, walidację wejścia,
 * obsługę wyceny indywidualnej (>100 userów) oraz agregaty.
 */
class RegressiveTierPricingCalculatorTest extends TestCase
{
    private RegressiveTierPricingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RegressiveTierPricingCalculator;
    }

    public function test_zero_users_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->forUserCount(0);
    }

    public function test_negative_users_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->forUserCount(-1);
    }

    public function test_one_user_uses_first_tier(): void
    {
        $result = $this->calculator->forUserCount(1);

        $this->assertSame(1, $result['user_count']);
        $this->assertSame(120.00, $result['total_monthly']);
        $this->assertSame(120.00, $result['avg_per_user']);
        $this->assertFalse($result['is_individual']);
        $this->assertCount(1, $result['tiers']);
        $this->assertSame(1, $result['tiers'][0]['users_in_tier']);
    }

    public function test_twenty_users_uses_full_first_tier(): void
    {
        $result = $this->calculator->forUserCount(20);

        $this->assertSame(20, $result['user_count']);
        $this->assertSame(2400.00, $result['total_monthly']);
        $this->assertSame(120.00, $result['avg_per_user']);
        $this->assertFalse($result['is_individual']);
        $this->assertCount(1, $result['tiers']);
        $this->assertSame(20, $result['tiers'][0]['users_in_tier']);
    }

    public function test_twentyone_users_starts_second_tier(): void
    {
        // tier1: 20 × 120 = 2400, tier2: 1 × 80 = 80, total = 2480, avg = 118.10
        $result = $this->calculator->forUserCount(21);

        $this->assertSame(21, $result['user_count']);
        $this->assertSame(2480.00, $result['total_monthly']);
        $this->assertSame(118.10, $result['avg_per_user']);
        $this->assertFalse($result['is_individual']);
        $this->assertCount(2, $result['tiers']);
        $this->assertSame(20, $result['tiers'][0]['users_in_tier']);
        $this->assertSame(1, $result['tiers'][1]['users_in_tier']);
    }

    public function test_forty_users_completes_second_tier(): void
    {
        // tier1: 20 × 120 = 2400, tier2: 20 × 80 = 1600, total = 4000, avg = 100
        $result = $this->calculator->forUserCount(40);

        $this->assertSame(40, $result['user_count']);
        $this->assertSame(4000.00, $result['total_monthly']);
        $this->assertSame(100.00, $result['avg_per_user']);
        $this->assertFalse($result['is_individual']);
        $this->assertCount(2, $result['tiers']);
    }

    public function test_sixty_users_completes_third_tier(): void
    {
        // 2400 + 1600 + 1000 = 5000, avg = 83.33
        $result = $this->calculator->forUserCount(60);

        $this->assertSame(60, $result['user_count']);
        $this->assertSame(5000.00, $result['total_monthly']);
        $this->assertSame(83.33, $result['avg_per_user']);
        $this->assertFalse($result['is_individual']);
        $this->assertCount(3, $result['tiers']);
    }

    public function test_eighty_users_completes_fourth_tier(): void
    {
        // 2400 + 1600 + 1000 + 600 = 5600, avg = 70
        $result = $this->calculator->forUserCount(80);

        $this->assertSame(80, $result['user_count']);
        $this->assertSame(5600.00, $result['total_monthly']);
        $this->assertSame(70.00, $result['avg_per_user']);
        $this->assertFalse($result['is_individual']);
        $this->assertCount(4, $result['tiers']);
    }

    public function test_hundred_users_completes_fifth_tier(): void
    {
        // 2400 + 1600 + 1000 + 600 + 400 = 6000, avg = 60
        $result = $this->calculator->forUserCount(100);

        $this->assertSame(100, $result['user_count']);
        $this->assertSame(6000.00, $result['total_monthly']);
        $this->assertSame(60.00, $result['avg_per_user']);
        $this->assertFalse($result['is_individual']);
        $this->assertCount(5, $result['tiers']);
    }

    public function test_hundredone_users_is_individual(): void
    {
        $result = $this->calculator->forUserCount(101);

        $this->assertSame(101, $result['user_count']);
        $this->assertTrue($result['is_individual']);
        $this->assertNull($result['total_monthly']);
        $this->assertNull($result['avg_per_user']);
        $this->assertSame([], $result['tiers']);
    }

    public function test_thousand_users_is_individual(): void
    {
        $result = $this->calculator->forUserCount(1000);

        $this->assertTrue($result['is_individual']);
        $this->assertNull($result['total_monthly']);
    }

    public function test_tiers_returns_full_pricing_table(): void
    {
        $tiers = $this->calculator->tiers();

        $this->assertCount(5, $tiers);

        $this->assertSame(1, $tiers[0]['min']);
        $this->assertSame(20, $tiers[0]['max']);
        $this->assertSame(120.00, $tiers[0]['unit_price']);
        $this->assertSame(2400.00, $tiers[0]['package_total']);

        $this->assertSame(21, $tiers[1]['min']);
        $this->assertSame(40, $tiers[1]['max']);
        $this->assertSame(80.00, $tiers[1]['unit_price']);
        $this->assertSame(1600.00, $tiers[1]['package_total']);

        $this->assertSame(81, $tiers[4]['min']);
        $this->assertSame(100, $tiers[4]['max']);
        $this->assertSame(20.00, $tiers[4]['unit_price']);
        $this->assertSame(400.00, $tiers[4]['package_total']);
    }

    public function test_total_monthly_matches_breakdown(): void
    {
        foreach ([1, 20, 21, 40, 50, 60, 80, 100] as $users) {
            $breakdown = $this->calculator->forUserCount($users);
            $totalMonthly = $this->calculator->totalMonthly($users);

            $this->assertSame(
                $breakdown['total_monthly'],
                $totalMonthly,
                "totalMonthly() nie zgadza się z forUserCount() dla {$users} userów"
            );
        }
    }

    public function test_average_per_user_matches_breakdown(): void
    {
        foreach ([1, 20, 21, 40, 50, 60, 80, 100] as $users) {
            $breakdown = $this->calculator->forUserCount($users);
            $avgPerUser = $this->calculator->averagePerUser($users);

            $this->assertSame(
                $breakdown['avg_per_user'],
                $avgPerUser,
                "averagePerUser() nie zgadza się z forUserCount() dla {$users} userów"
            );
        }
    }

    public function test_partial_tier_calculation_is_correct(): void
    {
        // 50 userów: tier1(20*120=2400) + tier2(20*80=1600) + tier3(10*50=500) = 4500, avg=90
        $result = $this->calculator->forUserCount(50);

        $this->assertSame(50, $result['user_count']);
        $this->assertSame(4500.00, $result['total_monthly']);
        $this->assertSame(90.00, $result['avg_per_user']);
        $this->assertFalse($result['is_individual']);
        $this->assertCount(3, $result['tiers']);

        $this->assertSame(20, $result['tiers'][0]['users_in_tier']);
        $this->assertSame(20, $result['tiers'][1]['users_in_tier']);
        $this->assertSame(10, $result['tiers'][2]['users_in_tier']);
    }

    public function test_breakdown_includes_all_consumed_tiers(): void
    {
        // 30 userów: tier1 (20 userów) + tier2 (10 userów)
        $result = $this->calculator->forUserCount(30);

        $this->assertCount(2, $result['tiers']);

        $this->assertSame(1, $result['tiers'][0]['min']);
        $this->assertSame(20, $result['tiers'][0]['max']);
        $this->assertSame(20, $result['tiers'][0]['users_in_tier']);

        $this->assertSame(21, $result['tiers'][1]['min']);
        $this->assertSame(40, $result['tiers'][1]['max']);
        $this->assertSame(10, $result['tiers'][1]['users_in_tier']);

        // Suma: 20*120 + 10*80 = 2400 + 800 = 3200
        $this->assertSame(3200.00, $result['total_monthly']);
        // Avg: 3200 / 30 = 106.67
        $this->assertSame(106.67, $result['avg_per_user']);
    }
}
