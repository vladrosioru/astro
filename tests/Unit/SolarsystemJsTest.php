<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolarsystemJsTest extends TestCase
{
    use RefreshDatabase;

    public function test_js_exists_and_self_guards(): void
    {
        $js = file_get_contents(public_path('themes/theme_solarsystem/js/solarsystem.js'));
        $this->assertStringContainsString('.twinkle', $js);
        $this->assertStringContainsString('data-parallax', $js);
        $this->assertStringContainsString('.stage', $js);
    }

    public function test_active_theme_loads_js_deferred(): void
    {
        $js = app('theme.manager')->jsAssets();
        $solar = array_values(array_filter($js, fn ($a) => str_ends_with($a['url'], 'solarsystem.js')));
        $this->assertNotEmpty($solar);
        $this->assertTrue($solar[0]['defer']);
    }
}
