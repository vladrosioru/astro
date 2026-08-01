<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads_the_admin_stylesheet_and_no_theme_css(): void
    {
        $content = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('css/admin.css', $content);

        // The admin module is theme-independent: no theme package asset and no
        // :root token block may reach an admin page.
        $this->assertStringNotContainsString('/themes/theme_', $content);
        $this->assertStringNotContainsString('--color-primary', $content);
    }

    public function test_login_page_has_no_site_nav_footer_or_back_to_top(): void
    {
        $content = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('nav-toggle-input', $content);
        $this->assertStringNotContainsString('site-footer', $content);
        $this->assertStringNotContainsString('back-to-top', $content);
    }

    public function test_login_page_body_carries_the_admin_class(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('<body class="adm-body">', false);
    }
}
