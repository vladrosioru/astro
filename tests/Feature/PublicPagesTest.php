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

    public function test_about_page_has_single_schedule_session_cta_after_faq(): void
    {
        $response = $this->get('/en/about');

        $response->assertOk()
            ->assertDontSee('Book a Session')
            ->assertSeeInOrder(['Frequently Asked Questions', 'Schedule Your Session']);

        $this->assertSame(
            1,
            substr_count($response->getContent(), 'Schedule Your Session'),
            'Expected exactly one "Schedule Your Session" CTA on the About page.'
        );
    }

    public function test_about_page_has_no_solar_system_divider(): void
    {
        $this->get('/en/about')
            ->assertOk()
            ->assertDontSee('stage--motif', false);
    }

    public function test_about_schedule_session_cta_hidden_when_contact_disabled(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['contact' => false] + $setting->sections]);

        $this->get('/en/about')
            ->assertOk()
            ->assertDontSee('Schedule Your Session');
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

    public function test_services_page_renders_when_enabled(): void
    {
        $this->get('/en/services')
            ->assertOk()
            ->assertSee('Services');
    }

    public function test_services_page_404s_when_disabled(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['services' => false] + $setting->sections]);

        $this->get('/en/services')->assertNotFound();
    }

    public function test_nav_shows_services_link_when_enabled(): void
    {
        $this->get('/en')->assertSee('/en/services');
    }

    public function test_nav_services_submenu_has_all_seven_items_in_order(): void
    {
        $this->get('/en')->assertSeeInOrder([
            'services#natal-chart-analysis">Natal Chart Analysis',
            'services#relationship-analysis">Relationship Analysis',
            'services#progressions-solar-returns">Progressions<',
            'services#progressions-solar-returns">Solar Returns',
            'services#elective-horary-charts">Elective Astrology',
            'services#astro-travel">Astrocartography',
            'services#yearly-horoscope">Yearly Forecast',
        ], false);
    }

    public function test_services_page_has_no_breadcrumb(): void
    {
        $this->get('/en/services')
            ->assertOk()
            ->assertDontSee('about-crumb', false);
    }

    public function test_services_page_new_hero_copy_and_order(): void
    {
        $response = $this->get('/en/services');

        $response->assertOk()
            ->assertSee('Your birth chart is the key to help you understand why you')
            ->assertSeeInOrder([
                'Your birth chart is the key',
                'Every reading starts with a conversation',
                'data-svc-grid',
            ], false)
            ->assertDontSee('What We Offer')
            ->assertDontSee('Readings &amp; Sessions', false);
    }

    public function test_services_page_has_no_energy_healing(): void
    {
        $this->get('/en/services')
            ->assertOk()
            ->assertDontSee('Energy Healing')
            ->assertDontSee('Reiki Session')
            ->assertDontSee('Chakra Balancing')
            ->assertDontSee('Crystal Healing')
            ->assertDontSee('Cord-Cutting');
    }

    public function test_services_page_astrology_card_count_and_no_label(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk()
            ->assertDontSee('Daily Horoscope')
            ->assertDontSee('Child&#039;s Horoscope', false);

        $this->assertSame(6, substr_count($content, 'data-svc-cat="astrology"'));
        $this->assertStringNotContainsString('svc-card__cat', $content);
    }

    public function test_services_page_tarot_card_count(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk()->assertDontSee('Full Life Reading');

        $this->assertSame(3, substr_count($content, 'data-svc-cat="tarot"'));
    }

    public function test_services_page_has_faq_after_cards(): void
    {
        $this->get('/en/services')
            ->assertOk()
            ->assertSeeInOrder(['data-svc-grid', 'Frequently Asked Questions'], false);
    }

    public function test_services_page_book_a_session_button_after_cards(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk()->assertSeeInOrder(['data-svc-grid', 'Book a Session'], false);

        $this->assertSame(1, substr_count($content, 'Book a Session'));
    }

    public function test_services_page_testimonials_use_andrei_not_alice(): void
    {
        $this->get('/en/services')
            ->assertOk()
            ->assertSee('Andrei')
            ->assertDontSee('Alice');
    }
}
