<?php

declare(strict_types=1);

namespace Plugins\VelorynPlugin\Services;

use InvalidArgumentException;

/**
 * RegressiveTierPricingCalculator — kalkulator cennika regresywno-paczkowego Veloryn.
 *
 * Model cennika: kolejne 20-pak użytkowników jest tańszy, ale nie retroaktywny.
 * Paczka 21–40 nie obniża ceny pierwszych 20.
 *
 * Tier'y (prywatne stałe):
 *  1–20   → 120 zł/user → paczka  2 400 zł
 *  21–40  →  80 zł/user → paczka  1 600 zł
 *  41–60  →  50 zł/user → paczka  1 000 zł
 *  61–80  →  30 zł/user → paczka    600 zł
 *  81–100 →  20 zł/user → paczka    400 zł
 *  >100   → wycena indywidualna
 */
class RegressiveTierPricingCalculator
{
    /**
     * @var array<int, array{min: int, max: int, unit_price: float, package_total: float}>
     */
    private const TIERS = [
        ['min' => 1,   'max' => 20,  'unit_price' => 120.00, 'package_total' => 2400.00],
        ['min' => 21,  'max' => 40,  'unit_price' => 80.00,  'package_total' => 1600.00],
        ['min' => 41,  'max' => 60,  'unit_price' => 50.00,  'package_total' => 1000.00],
        ['min' => 61,  'max' => 80,  'unit_price' => 30.00,  'package_total' => 600.00],
        ['min' => 81,  'max' => 100, 'unit_price' => 20.00,  'package_total' => 400.00],
    ];

    /**
     * Oblicza szczegółowy breakdown kosztów dla podanej liczby użytkowników.
     *
     * @param  int  $users  Liczba użytkowników (>= 1)
     * @return array{
     *   user_count: int,
     *   tiers: array<int, array{min: int, max: int, unit_price: float, package_total: float, users_in_tier: int, tier_total: float}>,
     *   total_monthly: float|null,
     *   avg_per_user: float|null,
     *   is_individual: bool,
     * }
     *
     * @throws InvalidArgumentException gdy users < 1
     */
    public function forUserCount(int $users): array
    {
        $this->guardPositive($users);

        if ($users > 100) {
            return [
                'user_count' => $users,
                'tiers' => [],
                'total_monthly' => null,
                'avg_per_user' => null,
                'is_individual' => true,
            ];
        }

        $consumedTiers = [];
        $totalMonthly = 0.0;
        $remaining = $users;

        foreach (self::TIERS as $tier) {
            if ($remaining <= 0) {
                break;
            }

            $usersInTier = min($remaining, $tier['max'] - $tier['min'] + 1);
            $tierTotal = $usersInTier * $tier['unit_price'];

            $consumedTiers[] = [
                'min' => $tier['min'],
                'max' => $tier['max'],
                'unit_price' => $tier['unit_price'],
                'package_total' => $tier['package_total'],
                'users_in_tier' => $usersInTier,
                'tier_total' => $tierTotal,
            ];

            $totalMonthly += $tierTotal;
            $remaining -= $usersInTier;
        }

        return [
            'user_count' => $users,
            'tiers' => $consumedTiers,
            'total_monthly' => round($totalMonthly, 2),
            'avg_per_user' => round($totalMonthly / $users, 2),
            'is_individual' => false,
        ];
    }

    /**
     * Zwraca pełną tabelę cennika (wszystkie 5 tier'ów) do renderowania w szablonie Twig.
     *
     * @return array<int, array{min: int, max: int, unit_price: float, package_total: float}>
     */
    public function tiers(): array
    {
        return self::TIERS;
    }

    /**
     * Oblicza całkowity miesięczny abonament dla podanej liczby użytkowników.
     *
     * @throws InvalidArgumentException gdy users < 1
     */
    public function totalMonthly(int $users): float
    {
        $this->guardPositive($users);

        if ($users > 100) {
            return 0.0;
        }

        $total = 0.0;
        $remaining = $users;

        foreach (self::TIERS as $tier) {
            if ($remaining <= 0) {
                break;
            }
            $usersInTier = min($remaining, $tier['max'] - $tier['min'] + 1);
            $total += $usersInTier * $tier['unit_price'];
            $remaining -= $usersInTier;
        }

        return round($total, 2);
    }

    /**
     * Oblicza średnią cenę per użytkownik dla podanej liczby użytkowników.
     *
     * @throws InvalidArgumentException gdy users < 1
     */
    public function averagePerUser(int $users): float
    {
        $this->guardPositive($users);

        if ($users > 100) {
            return 0.0;
        }

        return round($this->totalMonthly($users) / $users, 2);
    }

    /**
     * Walidacja: liczba użytkowników musi być >= 1.
     *
     * @throws InvalidArgumentException
     */
    private function guardPositive(int $users): void
    {
        if ($users < 1) {
            throw new InvalidArgumentException(
                "Liczba użytkowników musi być >= 1, podano: {$users}"
            );
        }
    }
}
