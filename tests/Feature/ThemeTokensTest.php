<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_theme_emits_all_token_names(): void
    {
        $html = view('partials.tokens')->render();

        $this->assertStringContainsString('--color-bg-alt:', $html);
        $this->assertStringContainsString('--color-heading:', $html);
        $this->assertStringContainsString('--font-display:', $html);
        $this->assertStringContainsString('--nav-height: 4.5rem', $html); // solarsystem
        $this->assertStringContainsString('--container-width: 64rem', $html); // inherited default
    }
}
