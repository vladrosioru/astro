<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_has_translations_and_lookup_by_locale(): void
    {
        $post = Post::create(['status' => 'published', 'published_at' => now()]);
        $post->translations()->create([
            'locale' => 'en', 'title' => 'Hello', 'slug' => 'hello', 'body' => '<p>Hi</p>',
        ]);

        $this->assertSame('Hello', $post->translation('en')->title);
        $this->assertNull($post->translation('ro'));
    }

    public function test_published_scope_excludes_drafts(): void
    {
        Post::create(['status' => 'draft']);
        Post::create(['status' => 'published', 'published_at' => now()]);

        $this->assertSame(1, Post::published()->count());
    }
}
