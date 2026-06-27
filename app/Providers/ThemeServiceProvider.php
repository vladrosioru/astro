<?php

namespace App\Providers;

use App\Services\ThemeManager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('theme.manager', fn () => new ThemeManager);
    }

    public function boot(): void
    {
        // ThemeManager reads SiteSetting (the DB) to resolve the active theme.
        // During the test bootstrap the site_settings table may not exist yet
        // (providers boot before RefreshDatabase migrates), so guard the read.
        // callAfterResolving defers execution until the view factory is first used,
        // by which point migrations have already run.
        $this->callAfterResolving('view', function () {
            if (! Schema::hasTable('site_settings')) {
                return;
            }

            $manager = $this->app->make('theme.manager');

            if ($path = $manager->viewsPath()) {
                View::addNamespace('theme', $path);
            }

            View::share('themeManager', $manager);
            View::share('theme', $manager->manifest());
        });
    }
}
