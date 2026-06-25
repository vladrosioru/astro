<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_when_enabled(): void
    {
        $this->get('/en/about')->assertOk();
    }

    public function test_about_page_404s_when_disabled(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['about' => false] + $setting->sections]);

        $this->get('/en/about')->assertNotFound();
    }

    public function test_nav_hides_contact_link_when_disabled(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['contact' => false] + $setting->sections]);

        // Check on /en/about (no hero) so this isolates nav link visibility.
        $this->get('/en/about')
            ->assertOk()
            ->assertDontSee('/en/contact');
    }

    public function test_nav_shows_contact_link_when_enabled(): void
    {
        $this->get('/en')->assertSee('/en/contact');
    }
}
