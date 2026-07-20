<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_hero_headline(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('Understanding the Why Behind Your Choices');
    }

    public function test_hero_uses_custom_content_when_set(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['hero' => ['headline' => 'Custom Headline'] + $setting->hero]);

        $this->get('/en')->assertSee('Custom Headline');
    }

    public function test_hero_defaults_include_eyebrow_and_secondary_cta(): void
    {
        $defaults = SiteSetting::heroDefaults();

        $this->assertSame('AstroTherapia', $defaults['eyebrow']);
        $this->assertArrayHasKey('cta2_label', $defaults);
        $this->assertArrayHasKey('cta2_url', $defaults);
    }

    public function test_home_renders_stage_with_logo_mark(): void
    {
        // CTA buttons were replaced by the logo mark in the hero actions area.
        $this->get('/en')
            ->assertOk()
            ->assertSee('class="stage"', false)
            ->assertSee('class="hero-logo"', false);
    }

    public function test_nav_shows_eyebrow_wordmark_on_every_page(): void
    {
        // The eyebrow moved out of the home hero into the nav brand, so it now
        // rides under the logo on inner pages too (here /en/contact, no hero).
        $this->get('/en/contact')
            ->assertOk()
            ->assertSee('class="nav-eyebrow"', false)
            ->assertSee('AstroTherapia');      // eyebrow default
    }

    public function test_home_sets_page_home_body_class(): void
    {
        $this->get('/en')->assertSee('class="page-home"', false);
    }
}
