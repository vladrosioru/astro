<?php

namespace Tests\Unit;

use Tests\TestCase;

class ThemeJsonContractTest extends TestCase
{
    public function test_every_shipped_theme_json_is_structurally_valid(): void
    {
        $configTokenNames = array_keys(config('tokens.defaults'));

        foreach (glob(public_path('themes/theme_*'), GLOB_ONLYDIR) as $dir) {
            $manifestPath = $dir.'/theme.json';
            $this->assertFileExists($manifestPath, "Missing theme.json in $dir");

            $m = json_decode(file_get_contents($manifestPath), true);
            $this->assertIsArray($m, "Invalid JSON in $manifestPath");

            foreach (['name', 'title', 'tokens', 'assets'] as $key) {
                $this->assertArrayHasKey($key, $m, "$key missing in $manifestPath");
            }

            // Every declared token has type/role/value and is a known token name.
            foreach ($m['tokens'] as $name => $def) {
                $this->assertContains($name, $configTokenNames, "Unknown token '$name' in $manifestPath");
                foreach (['type', 'role', 'value'] as $field) {
                    $this->assertArrayHasKey($field, $def, "Token '$name' missing '$field' in $manifestPath");
                }
                $this->assertContains(
                    $def['type'],
                    ['color', 'font-stack', 'length', 'shadow'],
                    "Token '$name' has invalid type '{$def['type']}' in $manifestPath"
                );
            }

            // Every referenced CSS asset exists on disk.
            foreach ($m['assets']['css'] as $rel) {
                $this->assertFileExists("$dir/$rel", "Missing CSS asset $rel in $dir");
            }
            foreach ($m['assets']['js'] ?? [] as $js) {
                $this->assertFileExists("$dir/{$js['src']}", "Missing JS asset {$js['src']} in $dir");
            }

            // Declared view partials exist.
            foreach (($m['views'] ?? []) as $slot => $view) {
                if (in_array($slot, ['hero', 'cosmos', 'nav'], true)) {
                    $this->assertFileExists("$dir/views/$view.blade.php", "Missing view $view in $dir");
                }
            }
        }
    }
}
