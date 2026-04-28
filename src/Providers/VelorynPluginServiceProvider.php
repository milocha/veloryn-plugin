<?php

declare(strict_types=1);

namespace Plugins\VelorynPlugin\Providers;

use App\Services\Templates\TokenBuilderRegistry;
use Illuminate\Support\ServiceProvider;
use Plugins\VelorynPlugin\Console\SeedOfferTemplateCommand;
use Plugins\VelorynPlugin\Services\RegressiveTierPricingCalculator;
use Plugins\VelorynPlugin\Services\VelorynQuoteTokenExtension;

/**
 * VelorynPluginServiceProvider
 *
 * Bootstraps the Veloryn Plugin. Rejestruje:
 *  - RegressiveTierPricingCalculator (singleton)
 *  - VelorynQuoteTokenExtension (singleton, zastępuje QuoteTokenBuilder w TokenBuilderRegistry)
 *  - SeedOfferTemplateCommand (Artisan)
 *
 * Hookowanie tokenów: WARIANT B — dziedziczenie + re-rejestracja w TokenBuilderRegistry.
 * Plugin rejestruje VelorynQuoteTokenExtension (extends QuoteTokenBuilder) pod kluczem 'quote',
 * zastępując domyślny QuoteTokenBuilder. Zero zmian w core.
 *
 * Follows the Veloryn plugin contract:
 * - Namespace must be under `Plugins\{PluginId}\` (enforced by PluginManager R3).
 * - ServiceProvider is registered only when the plugin is active in the database.
 */
class VelorynPluginServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RegressiveTierPricingCalculator::class);

        $this->app->singleton(
            VelorynQuoteTokenExtension::class,
            fn ($app) => new VelorynQuoteTokenExtension(
                $app->make(RegressiveTierPricingCalculator::class),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->commands([
            SeedOfferTemplateCommand::class,
        ]);

        // WARIANT B: nadpisz wpis 'quote' w TokenBuilderRegistry własnym builderem.
        // TemplatesServiceProvider::boot() rejestruje QuoteTokenBuilder pod 'quote' wcześniej.
        // PluginServiceProvider::boot() wywołuje registerProviders() — ten provider bootuje PO core.
        // Tedy re-rejestracja tutaj gwarantuje, że VelorynQuoteTokenExtension wygrywa.
        if ($this->app->bound(TokenBuilderRegistry::class)) {
            /** @var TokenBuilderRegistry $registry */
            $registry = $this->app->make(TokenBuilderRegistry::class);
            $registry->register('quote', VelorynQuoteTokenExtension::class);
        }
    }
}
