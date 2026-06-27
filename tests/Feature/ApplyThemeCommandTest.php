<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyThemeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_solarsystem_sets_pointer_and_emits_cosmos_tokens(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'solarsystem'])->assertExitCode(0);

        $this->assertSame('solarsystem', SiteSetting::current()->fresh()->theme);
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-bg: #05060c', $html);
    }

    public function test_applying_default_sets_pointer_and_emits_light(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'default'])->assertExitCode(0);

        $this->assertSame('default', SiteSetting::current()->fresh()->theme);
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-primary: #2563eb', $html);
    }

    public function test_unknown_theme_fails(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'nope'])->assertExitCode(1);
        $this->assertSame('solarsystem', SiteSetting::current()->fresh()->theme);
    }
}
