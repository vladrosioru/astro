<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use Database\Seeders\AuthorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_exactly_one_author_with_expected_fields(): void
    {
        (new AuthorSeeder)->run();

        $this->assertSame(1, Author::count());

        $author = Author::first();
        $this->assertSame('Andrei | AstroTherapia', $author->name);
        $this->assertSame('Associate Member of Faculty of Astrological Studies - London, UK', $author->description);
        $this->assertSame('img/logo-nav.png', $author->picture);
    }

    public function test_it_is_idempotent_on_rerun(): void
    {
        (new AuthorSeeder)->run();
        (new AuthorSeeder)->run();

        $this->assertSame(1, Author::count());
    }

    public function test_it_backfills_posts_with_no_author(): void
    {
        $post = Post::create(['status' => 'draft']);

        (new AuthorSeeder)->run();

        $this->assertSame(Author::first()->id, $post->fresh()->author_id);
    }

    public function test_it_does_not_override_a_posts_existing_author(): void
    {
        $other = Author::create(['name' => 'Someone Else']);
        $post = Post::create(['status' => 'draft', 'author_id' => $other->id]);

        (new AuthorSeeder)->run();

        $this->assertSame($other->id, $post->fresh()->author_id);
    }
}
