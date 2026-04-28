<?php

declare(strict_types=1);

namespace Plugins\VelorynPlugin\Services;

use App\Models\Quote;
use App\Services\Templates\QuoteTokenBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * VelorynQuoteTokenExtension — rozszerza QuoteTokenBuilder o kontekst cennika regresywnego.
 *
 * Strategia hookowania: WARIANT B (override przez dziedziczenie + rejestracja w TokenBuilderRegistry).
 * Plugin zastępuje wpis 'quote' w rejestrze własnym builderem dziedziczącym po QuoteTokenBuilder.
 * Nie wymaga żadnych zmian w core — jest to czyste rozszerzenie OOP.
 *
 * Dodawane tokeny szablonu (gdy quote.meta.user_count jest ustawiony):
 *  - pricing.regressive_breakdown — szczegółowy breakdown tier'ów
 *  - pricing.tier_table           — pełna tabela cennika (do pętli Twig)
 *  - pricing.total_monthly        — łączny miesięczny abonament
 *  - pricing.avg_per_user         — średnia cena per user
 */
class VelorynQuoteTokenExtension extends QuoteTokenBuilder
{
    public function __construct(
        private readonly RegressiveTierPricingCalculator $calculator,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @param  Quote  $entity
     */
    public function buildContext(Model $entity, array $extras = []): array
    {
        $context = parent::buildContext($entity, $extras);

        $context['pricing'] = $this->buildPricingContext($context);

        return $context;
    }

    /**
     * {@inheritDoc}
     */
    public function availableTokens(): array
    {
        return array_merge(parent::availableTokens(), [
            'pricing.regressive_breakdown' => [
                'label' => 'Breakdown cennika regresywnego',
                'type' => 'array',
                'description' => 'Szczegółowy rozkład per tier; null gdy brak user_count w meta lub >100 users',
            ],
            'pricing.tier_table' => [
                'label' => 'Pełna tabela cennika',
                'type' => 'array',
                'description' => 'Wszystkie 5 tier\'ów do renderowania tabeli w szablonie',
            ],
            'pricing.total_monthly' => [
                'label' => 'Łączny miesięczny abonament',
                'type' => 'decimal|null',
                'description' => 'null gdy >100 użytkowników (wycena indywidualna)',
            ],
            'pricing.avg_per_user' => [
                'label' => 'Średnia cena per użytkownik',
                'type' => 'decimal|null',
                'description' => 'null gdy >100 użytkowników',
            ],
        ]);
    }

    /**
     * Buduje sekcję pricing na podstawie quote.meta.user_count.
     *
     * Jeśli user_count nie jest ustawiony lub wynosi 0 — zwraca strukturę z samą tabelą.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildPricingContext(array $context): array
    {
        $userCount = (int) ($context['quote']['meta']['user_count'] ?? 0);

        $tierTable = $this->calculator->tiers();

        if ($userCount <= 0) {
            return [
                'regressive_breakdown' => null,
                'tier_table' => $tierTable,
                'total_monthly' => null,
                'avg_per_user' => null,
            ];
        }

        $breakdown = $this->calculator->forUserCount($userCount);

        return [
            'regressive_breakdown' => $breakdown,
            'tier_table' => $tierTable,
            'total_monthly' => $breakdown['total_monthly'],
            'avg_per_user' => $breakdown['avg_per_user'],
        ];
    }
}
