<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_settings(): void
    {
        $this->get('/admin/settings')->assertRedirect('/admin/login');
        $this->patch('/admin/settings', [])->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_settings_page_with_current_defaults(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('Site Settings')
            ->assertSee('Contact Details')
            ->assertSee('About Page Content')
            ->assertSee('Understanding the Why Behind Your Choices');
    }

    public function test_admin_can_update_contact_hero_sections_and_about_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->patch('/admin/settings', [
            'contact' => [
                'email' => 'hello@astrotherapia.test',
                'phone' => '+40 722 000 111',
                'address' => 'Bucharest, RO',
            ],
            'sections' => [
                'about' => '1',
                'blog' => '1',
                'services' => '1',
                'contact' => '1',
            ],
            'hero' => [
                'eyebrow' => 'Updated Brand',
                'headline' => 'Custom Admin Headline',
                'subhead' => 'Custom Admin Subhead',
                'cta_label' => 'Book Now',
                'cta_url' => '/en/contact',
                'cta2_label' => 'Journal',
                'cta2_url' => '/en/journal',
            ],
            'about' => [
                'manifesto_eyebrow' => 'Philosophy',
                'manifesto_title' => 'New About Title',
                'manifesto_subhead' => 'New About Subhead',
                'manifesto_body' => "First custom paragraph.\n\nSecond custom paragraph.",
                'core_eyebrow' => 'Core Practice',
                'core_title' => 'New Core Title',
                'core_body' => "Core paragraph one.\n\nCore paragraph two.",
                'faqs' => [
                    ['q' => 'Custom FAQ Question?', 'a' => 'Custom FAQ Answer.'],
                    ['q' => '', 'a' => ''],
                ],
            ],
        ]);

        $response->assertRedirect('/admin/settings');

        $this->get('/en/contact')
            ->assertOk()
            ->assertSee('hello@astrotherapia.test')
            ->assertSee('+40 722 000 111');

        $this->get('/en')
            ->assertOk()
            ->assertSee('Updated Brand')
            ->assertSee('Custom Admin Headline');

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('Philosophy')
            ->assertSee('New About Title')
            ->assertSee('New About Subhead')
            ->assertSee('First custom paragraph.')
            ->assertSee('Second custom paragraph.')
            ->assertSee('New Core Title')
            ->assertSee('Core paragraph one.')
            ->assertSee('Custom FAQ Question?')
            ->assertSee('Custom FAQ Answer.');

        $this->assertCount(1, SiteSetting::current()->aboutContent()['faqs']);
    }
}
