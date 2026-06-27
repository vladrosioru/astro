<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_shows_the_logo_centered_between_the_links(): void
    {
        $this->get('/en')
            ->assertOk()
            // Logo image replaces the old text wordmark; alt carries the site name.
            ->assertSee('img/logo-nav.png', false)
            ->assertSee('class="nav-logo"', false)
            ->assertSee(config('app.name'));
    }
}
