<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_shows_the_gold_wordmark(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('✦')
            ->assertSee(config('app.name'));
    }
}
