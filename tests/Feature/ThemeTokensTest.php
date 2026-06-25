<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tokens_have_light_defaults(): void
    {
        $html = view('partials.tokens')->render();

        $this->assertStringContainsString('--color-bg-alt:', $html);
        $this->assertStringContainsString('--color-heading:', $html);
        $this->assertStringContainsString('--font-display:', $html);
        $this->assertStringContainsString('--nav-height: 4rem', $html);
        $this->assertStringContainsString('--hero-overlay:', $html);
    }
}
