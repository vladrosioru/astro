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
            ->assertSee('Personal Horoscope &amp; Magic Services', false)
            ->assertSee('Begin Here');
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

        $this->assertSame('Celestial Guidance', $defaults['eyebrow']);
        $this->assertArrayHasKey('cta2_label', $defaults);
        $this->assertArrayHasKey('cta2_url', $defaults);
    }

    public function test_home_renders_stage_with_eyebrow_and_secondary_cta(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('class="stage"', false)
            ->assertSee('Celestial Guidance')      // eyebrow default
            ->assertSee('Read the Journal');       // secondary CTA default
    }

    public function test_home_sets_page_home_body_class(): void
    {
        $this->get('/en')->assertSee('class="page-home"', false);
    }
}
