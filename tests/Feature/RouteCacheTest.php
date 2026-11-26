<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RouteCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_can_be_cached_without_closure_errors(): void
    {
        try {
            $exitCode = Artisan::call('route:cache');
            $this->assertSame(0, $exitCode);
        } finally {
            Artisan::call('route:clear');
        }
    }

    public function test_legacy_blog_and_articles_routes_use_301_permanent_redirects(): void
    {
        $this->get('/en/blog')->assertStatus(301)->assertRedirect('/en/journal');
        $this->get('/en/blog/sample-post')->assertStatus(301)->assertRedirect('/en/journal/sample-post');
        $this->get('/en/articles')->assertStatus(301)->assertRedirect('/en/journal');
        $this->get('/en/articles/sample-post')->assertStatus(301)->assertRedirect('/en/journal/sample-post');
    }
}
