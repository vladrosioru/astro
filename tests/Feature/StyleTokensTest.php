<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StyleTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_partial_renders_active_theme_variables(): void
    {
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-primary: #9dc1e6', $html); // solarsystem
        $this->assertStringContainsString('--font-base:', $html);
    }

    public function test_branding_overrides_a_theme_token(): void
    {
        \App\Models\SiteSetting::current()->update(['branding' => ['color-primary' => '#ff0000']]);
        $html = view('partials.tokens')->render();
        $this->assertStringContainsString('--color-primary: #ff0000', $html);
        $this->assertStringNotContainsString('--color-primary: #9dc1e6', $html);
    }
}
