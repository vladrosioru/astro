<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_shows_footer(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('site-footer', false)
            ->assertSee('Every man and every woman is a Star')
            ->assertSee('AstroTherapia © 2024')
            ->assertSee('/en/contact', false)
            ->assertSee('https://www.facebook.com/astrotherapia.ro', false);
    }

    public function test_footer_contact_link_hidden_when_contact_section_disabled(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['contact' => false] + $setting->sections]);

        $this->get('/en/about')
            ->assertOk()
            ->assertDontSee('site-footer__contact', false);
    }

    public function test_admin_page_does_not_show_footer(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('site-footer', false);
    }
}
