<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyThemeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_mystik_writes_gold_dark_tokens(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'mystik'])->assertExitCode(0);

        $branding = SiteSetting::current()->fresh()->branding;
        $this->assertSame('#f0a23c', $branding['color-heading']);
        $this->assertSame('#0a0a0f', $branding['color-bg']);
    }

    public function test_applying_mystik_makes_token_partial_emit_gold(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'mystik']);
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-heading: #f0a23c', $html);
    }

    public function test_applying_default_clears_branding(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'mystik']);
        $this->artisan('app:apply-theme', ['name' => 'default'])->assertExitCode(0);

        $this->assertSame([], SiteSetting::current()->fresh()->branding);
    }

    public function test_unknown_theme_fails(): void
    {
        $this->artisan('app:apply-theme', ['name' => 'nope'])->assertExitCode(1);
    }
}
