<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_default_locale(): void
    {
        $this->get('/')->assertRedirect('/en');
    }

    public function test_supported_locale_sets_application_locale(): void
    {
        $this->get('/ro')->assertOk();
        $this->assertSame('ro', app()->getLocale());
    }

    public function test_unsupported_locale_returns_404(): void
    {
        $this->get('/de')->assertNotFound();
    }
}
