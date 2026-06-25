<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;

class ApplyTheme extends Command
{
    protected $signature = 'app:apply-theme {name}';

    protected $description = 'Apply a named theme token set to SiteSetting.branding';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === 'default') {
            SiteSetting::current()->update(['branding' => []]);
            $this->info('Theme reset to default.');

            return self::SUCCESS;
        }

        $tokens = config("themes.{$name}");

        if (! is_array($tokens)) {
            $this->error("Unknown theme: {$name}");

            return self::FAILURE;
        }

        SiteSetting::current()->update(['branding' => $tokens]);
        $this->info("Theme '{$name}' applied.");

        return self::SUCCESS;
    }
}
