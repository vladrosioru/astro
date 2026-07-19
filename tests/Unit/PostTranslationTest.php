<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_slug_derives_from_title_when_free(): void
    {
        $this->assertSame('a-fresh-title', PostTranslation::uniqueSlug('A Fresh Title', 'en'));
    }

    public function test_unique_slug_appends_a_numeric_suffix_on_collision(): void
    {
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Same Title', 'slug' => 'same-title']);

        $slug = PostTranslation::uniqueSlug('Same Title', 'en');

        $this->assertNotSame('same-title', $slug);
        $this->assertMatchesRegularExpression('/^same-title-\d{3}$/', $slug);
    }

    public function test_unique_slug_collision_in_a_different_locale_does_not_force_a_suffix(): void
    {
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create(['locale' => 'ro', 'title' => 'Same Title', 'slug' => 'same-title']);

        $this->assertSame('same-title', PostTranslation::uniqueSlug('Same Title', 'en'));
    }

    public function test_unique_slug_ignores_its_own_row_when_regenerating(): void
    {
        $post = Post::create(['status' => 'draft']);
        $translation = $post->translations()->create(['locale' => 'en', 'title' => 'Same Title', 'slug' => 'same-title']);

        $this->assertSame('same-title', PostTranslation::uniqueSlug('Same Title', 'en', $translation->id));
    }
}
