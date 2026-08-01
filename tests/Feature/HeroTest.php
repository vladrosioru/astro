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

    public function test_home_renders_hero_subhead(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('Your birth chart is the key to help you understand why you');
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

    public function test_hero_cta_hidden_when_its_target_section_is_disabled(): void
    {
        // The default theme is the one that renders hero CTAs. Its primary CTA
        // points at /services, so disabling that section must drop the button
        // rather than leave it pointing at a 404.
        $setting = SiteSetting::current();
        $setting->update([
            'theme' => 'default',
            'sections' => ['services' => false] + $setting->sections,
        ]);

        $this->get('/en')
            ->assertOk()
            ->assertDontSee('Begin Here')
            ->assertDontSee('/en/services');
    }

    public function test_hero_cta_url_follows_the_request_locale(): void
    {
        // The stored default is /en/services; a Romanian visitor must not be
        // bounced out of their locale.
        SiteSetting::current()->update(['theme' => 'default']);

        $this->get('/ro')
            ->assertOk()
            ->assertSee('/ro/services')
            ->assertDontSee('/en/services');
    }

    public function test_hero_cta_keeps_an_external_url_untouched(): void
    {
        $setting = SiteSetting::current();
        $setting->update([
            'theme' => 'default',
            'hero' => ['cta_url' => 'https://cal.example.com/book'] + $setting->hero,
        ]);

        $this->get('/en')
            ->assertOk()
            ->assertSee('https://cal.example.com/book', false);
    }

    public function test_home_sets_page_home_body_class(): void
    {
        $this->get('/en')->assertSee('class="page-home"', false);
    }
}
