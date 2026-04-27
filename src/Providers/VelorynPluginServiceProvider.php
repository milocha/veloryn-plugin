<?php

declare(strict_types=1);

namespace Plugins\VelorynPlugin\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * VelorynPluginServiceProvider
 *
 * Bootstraps the Veloryn Plugin. This is the entry point for the plugin
 * into the Veloryn application's service container.
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
