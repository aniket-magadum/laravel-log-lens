<?php

namespace AniketMagadum\LogLens;

use Illuminate\Support\ServiceProvider;

class LogLensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/log-lens.php', 'log-lens');

        $this->app->singleton(LogLens::class, function ($app) {
            return new LogLens($app['config']->get('log-lens'));
        });

        $this->app->alias(LogLens::class, 'log-lens');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/log-lens.php' => config_path('log-lens.php'),
        ], 'log-lens-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/log-lens'),
        ], 'log-lens-views');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'log-lens');

        if ($this->app['config']->get('log-lens.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}
