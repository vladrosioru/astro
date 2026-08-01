<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAuthorCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_create_an_author(): void
    {
        $this->actingAs($this->admin())->post('/admin/authors', [
            'name' => 'Jane Doe',
            'description' => 'A great writer.',
        ])->assertRedirect('/admin/authors');

        $author = Author::first();
        $this->assertNotNull($author);
        $this->assertSame('Jane Doe', $author->name);
        $this->assertSame('A great writer.', $author->description);
    }

    public function test_name_is_required_to_create_an_author(): void
    {
        $this->actingAs($this->admin())->post('/admin/authors', [
            'description' => 'No name given.',
        ])->assertSessionHasErrors('name');

        $this->assertSame(0, Author::count());
    }

    public function test_admin_can_update_an_author(): void
    {
        $author = Author::create(['name' => 'Old Name', 'description' => 'Old bio']);

        $this->actingAs($this->admin())->put("/admin/authors/{$author->id}", [
            'name' => 'New Name',
            'description' => 'New bio',
        ])->assertRedirect('/admin/authors');

        $author->refresh();
        $this->assertSame('New Name', $author->name);
        $this->assertSame('New bio', $author->description);
    }

    public function test_admin_can_delete_an_author_and_posts_are_unassigned(): void
    {
        $author = Author::create(['name' => 'To Delete']);
        $post = Post::create(['status' => 'draft', 'author_id' => $author->id]);

        $this->actingAs($this->admin())->delete("/admin/authors/{$author->id}")
            ->assertRedirect('/admin/authors');

        $this->assertSame(0, Author::count());
        $this->assertNull($post->fresh()->author_id);
    }

    public function test_authors_index_shows_name_and_post_count(): void
    {
        $author = Author::create(['name' => 'Prolific Author']);
        Post::create(['status' => 'draft', 'author_id' => $author->id]);
        Post::create(['status' => 'draft', 'author_id' => $author->id]);

        $response = $this->actingAs($this->admin())->get('/admin/authors');

        $response->assertOk();
        $response->assertSee('Prolific Author');
        $response->assertSee('2');
    }

    public function test_uploading_a_picture_saves_a_square_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post('/admin/authors', [
            'name' => 'Jane Doe',
            'picture' => UploadedFile::fake()->image('avatar.jpg', 2000, 1000),
        ])->assertRedirect('/admin/authors');

        $author = Author::first();
        $this->assertNotNull($author->picture);
        $this->assertStringStartsWith('/', $author->picture);

        $media = Media::first();
        $this->assertNotNull($media);
        $this->assertSame(400, $media->width);
        $this->assertSame(400, $media->height);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_removing_a_picture_nulls_it(): void
    {
        $author = Author::create(['name' => 'Jane Doe', 'picture' => '/storage/media/old.jpg']);

        $this->actingAs($this->admin())->put("/admin/authors/{$author->id}", [
            'name' => 'Jane Doe',
            'remove_picture' => '1',
        ])->assertRedirect('/admin/authors');

        $this->assertNull($author->fresh()->picture);
    }

    public function test_non_admin_cannot_create_an_author(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/admin/authors', ['name' => 'Nope'])->assertForbidden();
    }

    public function test_dashboard_lists_authors_link_after_posts(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $html = $response->getContent();
        // Both live in the admin top bar (admin/partials/_topbar.blade.php),
        // which orders sections Posts → Authors.
        $postsPos = strpos($html, '>Posts</a>');
        $authorsPos = strpos($html, '>Authors</a>');

        $this->assertNotFalse($postsPos);
        $this->assertNotFalse($authorsPos);
        $this->assertLessThan($authorsPos, $postsPos);
    }
}
