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

    public function test_home_learn_more_cta_hidden_when_about_disabled(): void
    {
        // The home page's "Learn More" button links straight at /about; with the
        // About section off that route 404s, so the button must not render.
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['about' => false] + $setting->sections]);

        $this->get('/en')
            ->assertOk()
            ->assertDontSee('Learn More')
            ->assertDontSee('/en/about');
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

    public function test_about_page_manifesto_covers_all_five_pillars(): void
    {
        $response = $this->get('/en/about');

        $response->assertOk()
            ->assertSee('At the center of it is identity', false)
            ->assertSee('Work and purpose orbit the same center', false)
            ->assertDontSee('keep you up at night', false);
    }

    public function test_about_page_faq_includes_pricing_question(): void
    {
        $this->get('/en/about')
            ->assertOk()
            ->assertSee('How much does a session cost?', false);
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

    public function test_nav_services_submenu_is_hidden(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertDontSee('services#natal-chart-analysis">Natal Chart Analysis', false)
            ->assertDontSee('nav-dropdown', false);
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

    public function test_services_page_testimonials_use_andrei_not_alice(): void
    {
        $this->get('/en/services')
            ->assertOk()
            ->assertSee('Andrei')
            ->assertDontSee('Alice');
    }

    public function test_services_page_testimonials_have_no_avatar_placeholder(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk();
        $this->assertStringNotContainsString('about-testi__avatar', $content);
    }

    public function test_services_page_testimonials_use_first_name_initial_reviewers(): void
    {
        $response = $this->get('/en/services');

        $response->assertOk()
            ->assertSeeInOrder(['John M', 'London'])
            ->assertSeeInOrder(['Anca R', 'Boston'])
            ->assertSeeInOrder(['Andreea P', 'Brasov'])
            ->assertSeeInOrder(['Catalin A', 'Bucharest'])
            ->assertDontSee('Jenna Mackenzie')
            ->assertDontSee('Milan')
            ->assertDontSee('Los Angeles')
            ->assertDontSee('Paris');
    }

    public function test_services_page_archetypes_tab_is_first_and_labeled(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk()->assertSeeInOrder([
            'data-svc-tab="archetypes"',
            'Archetypes',
            'data-svc-tab="astrology"',
            'Methods',
        ], false);

        $this->assertStringNotContainsString('>Astrology<', $content);
    }

    public function test_services_page_methods_tab_renamed_astrology_cards_unchanged(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk()->assertSee('Methods');
        $this->assertStringNotContainsString('>Astrology<', $content);
        $this->assertSame(6, substr_count($content, 'data-svc-cat="astrology"'));
        $this->assertStringNotContainsString('svc-card__cat', $content);
    }

    public function test_services_page_tarot_tab_removed_from_tab_bar(): void
    {
        $this->get('/en/services')
            ->assertOk()
            ->assertDontSee('data-svc-tab="tarot"', false);
    }

    public function test_services_page_archetypes_tab_has_five_pillar_flip_cards(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk()
            ->assertSeeInOrder(['data-svc-tab="archetypes"', 'data-svc-panel="archetypes"'], false)
            ->assertSee('Identity &amp; growth', false)
            ->assertSee('Career &amp; purpose', false)
            ->assertSee('Values &amp; money', false)
            ->assertSee('Health &amp; energy', false)
            ->assertSee('Mirror seeker, Guardian, Free spirit')
            ->assertSee('Chameleon, Outsider, Phoenix');

        $this->assertSame(5, substr_count($content, 'data-flip'));
    }

    public function test_services_page_tarot_section_exists_after_tabs_with_heading_description_and_cards(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk()
            ->assertSeeInOrder(['data-svc-grid', 'id="tarot"', '>Tarot<', 'data-svc-tarot-grid'], false)
            ->assertSeeInOrder(['data-svc-tarot-grid', 'Frequently Asked Questions'], false);

        $this->assertSame(3, substr_count($content, 'data-svc-cat="tarot"'));
        $this->assertSame(1, substr_count($content, 'data-svc-tarot-grid'));
    }

    public function test_services_page_two_book_a_session_ctas_one_per_section(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk()->assertSeeInOrder([
            'data-svc-grid',
            'Book a Session',
            'data-svc-tarot-grid',
            'Book a Session',
            'Frequently Asked Questions',
        ], false);

        $this->assertSame(2, substr_count($content, 'Book a Session'));
    }

    public function test_services_page_faq_has_eight_items_matching_about(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();

        $response->assertOk()->assertSee('How much does a session cost?', false);
        $this->assertSame(8, substr_count($content, 'about-faq__q'));
    }

    public function test_services_page_archetypes_carousel_has_arrows_and_five_dots(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();
        $response->assertOk();

        $start = strpos($content, 'data-svc-panel="archetypes"');
        $end = strpos($content, 'data-svc-panel="astrology"');
        $panel = substr($content, $start, $end - $start);

        $this->assertSame(1, substr_count($panel, 'data-svc-prev'));
        $this->assertSame(1, substr_count($panel, 'data-svc-next'));
        $this->assertSame(5, substr_count($panel, 'data-svc-dot='));
    }

    public function test_services_page_methods_carousel_has_arrows_and_six_dots(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();
        $response->assertOk();

        $start = strpos($content, 'data-svc-panel="astrology"');
        $end = strpos($content, 'data-svc-tarot-grid');
        $panel = substr($content, $start, $end - $start);

        $this->assertSame(1, substr_count($panel, 'data-svc-prev'));
        $this->assertSame(1, substr_count($panel, 'data-svc-next'));
        $this->assertSame(6, substr_count($panel, 'data-svc-dot='));
    }

    public function test_services_page_tarot_carousel_has_arrows_and_three_dots(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();
        $response->assertOk();

        $start = strpos($content, 'data-svc-tarot-grid');
        $end = strpos($content, 'Frequently Asked Questions');
        $tarot = substr($content, $start, $end - $start);

        $this->assertSame(1, substr_count($tarot, 'data-svc-prev'));
        $this->assertSame(1, substr_count($tarot, 'data-svc-next'));
        $this->assertSame(3, substr_count($tarot, 'data-svc-dot='));
    }

    public function test_services_page_methods_cards_never_get_data_flip(): void
    {
        $response = $this->get('/en/services');
        $content = $response->getContent();
        $response->assertOk();

        $start = strpos($content, 'data-svc-panel="astrology"');
        $end = strpos($content, 'data-svc-tarot-grid');
        $panel = substr($content, $start, $end - $start);

        $this->assertStringNotContainsString('data-flip', $panel);
    }

    public function test_services_page_identity_growth_is_still_center_card(): void
    {
        $response = $this->get('/en/services');

        $response->assertOk()->assertSeeInOrder(['flip-card--center', 'Identity &amp; growth'], false);
    }
}
