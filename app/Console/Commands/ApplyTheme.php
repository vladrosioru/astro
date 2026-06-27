<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ApplyTheme extends Command
{
    protected $signature = 'app:apply-theme {name}';

    protected $description = 'Set the active theme (folder under public/themes/theme_<name>)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $names = array_column(app('theme.manager')->available(), 'name');

        if (! in_array($name, $names, true)) {
            $this->error("Unknown theme: {$name}. Available: " . implode(', ', $names));

            return self::FAILURE;
        }

        SiteSetting::current()->update(['theme' => $name]);
        Artisan::call('view:clear');
        app()->forgetInstance('theme.manager');
        $this->info("Active theme set to '{$name}'.");

        return self::SUCCESS;
    }
}
