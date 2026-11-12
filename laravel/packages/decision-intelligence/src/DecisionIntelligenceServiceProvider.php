<?php

namespace DecisionIntelligence;

use Illuminate\Support\ServiceProvider;

final class DecisionIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/decision-intelligence.php', 'decision-intelligence');
        $this->app->singleton(ExecutiveSummary::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/decision-intelligence.php' => config_path('decision-intelligence.php'),
        ], 'decision-intelligence-config');
    }
}
