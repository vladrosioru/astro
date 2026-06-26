<?php

namespace Tests\Unit;

use Tests\TestCase;

class SolarsystemJsTest extends TestCase
{
    public function test_js_exists_and_self_guards(): void
    {
        $js = file_get_contents(public_path('js/solarsystem.js'));

        $this->assertStringContainsString('.twinkle', $js);
        $this->assertStringContainsString("data-parallax", $js);
        // Must no-op when there is no stage on the page.
        $this->assertStringContainsString(".stage", $js);
    }

    public function test_layout_loads_js_deferred(): void
    {
        $blade = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('solarsystem.js', $blade);
        $this->assertStringContainsString('defer', $blade);
    }
}
