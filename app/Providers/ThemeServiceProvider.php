<?php

namespace App\Providers;

use App\Services\ThemeManager;
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
        try {
            $manager = $this->app->make('theme.manager');

            if ($path = $manager->viewsPath()) {
                View::addNamespace('theme', $path);
            }

            View::share('themeManager', $manager);
            View::share('theme', $manager->manifest());
        } catch (\Throwable $e) {
            // Database may not be available during initial app bootstrap in tests.
            // The singleton is still registered and will work once the database is available.
            // Attempt to register the namespace without database access by using the database default theme.
            // (This assumes the database will have a 'solarsystem' theme as default when it's created)
            $fallback = 'solarsystem';
            $path = public_path(config('theme.path', 'themes') . '/theme_' . $fallback . '/views');
            if (is_dir($path)) {
                View::addNamespace('theme', $path);
            }
        }
    }
}
