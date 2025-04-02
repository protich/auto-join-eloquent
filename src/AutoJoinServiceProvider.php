<?php

namespace protich\AutoJoinEloquent;

use Illuminate\Support\ServiceProvider;

class AutoJoinServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * This method publishes the package configuration file to the application's config directory
     * and merges the package configuration with the application's configuration.
     *
     * @return void
     */
    public function boot()
    {
        // Publish the configuration file.
        $this->publishes([
            __DIR__ . '/../config/auto-join-eloquent.php' => config_path('auto-join-eloquent.php'),
        ], 'config');

        // Merge package configuration to ensure defaults are available.
        $this->mergeConfigFrom(
            __DIR__ . '/../config/auto-join-eloquent.php',
            'auto-join-eloquent'
        );
    }

    /**
     * Register any package services.
     *
     * This method ensures that the package configuration is merged into the application's configuration.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/auto-join-eloquent.php',
            'auto-join-eloquent'
        );
    }
}
