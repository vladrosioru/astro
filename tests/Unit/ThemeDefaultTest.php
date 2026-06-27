<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Services\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_theme_emits_light_tokens(): void
    {
        SiteSetting::current()->update(['theme' => 'default']);
        $tokens = (new ThemeManager)->tokens();

        $this->assertSame('#2563eb', $tokens['color-primary']);
        $this->assertSame('4rem', $tokens['nav-height']);
    }

    public function test_default_theme_renders_a_hero(): void
    {
        SiteSetting::current()->update(['theme' => 'default']);
        $this->get('/en')->assertOk()->assertSee('Understanding the Why Behind Your Choices');
    }
}
