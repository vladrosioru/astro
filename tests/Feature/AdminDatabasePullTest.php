<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDatabasePullTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sourceDb = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        config(['database_admin.restore_enabled' => true]);
        config(['database_admin.media_fallback_url' => 'https://astrotherapia.com']);
    }

    protected function tearDown(): void
    {
        if ($this->sourceDb) {
            @unlink($this->sourceDb);
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** Wire a separate sqlite DB as the `source` connection and build tables on it. */
    private function configureSource(): void
    {
        $this->sourceDb = tempnam(sys_get_temp_dir(), 'srcdb_');
        config(['database.connections.source' => [
            'driver' => 'sqlite',
            'database' => $this->sourceDb,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        Artisan::call('migrate', ['--database' => 'source', '--force' => true]);
    }

    public function test_pull_copies_source_content_into_this_database(): void
    {
        $this->configureSource();
        $post = Post::on('source')->create(['status' => 'published']);
        PostTranslation::on('source')->create([
            'post_id' => $post->id, 'locale' => 'en',
            'title' => 'Prod Title', 'slug' => 'prod-title', 'body' => '<p>Body</p>',
        ]);

        // Local dev starts with different content.
        $local = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $local->id, 'locale' => 'en',
            'title' => 'Dev Draft', 'slug' => 'dev-draft', 'body' => '<p>Draft</p>',
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/database/pull')
            ->assertRedirect('/admin/database');

        $this->assertSame('Prod Title', PostTranslation::first()->title);
        $this->assertSame(1, PostTranslation::count());
    }

    public function test_pull_rewrites_media_urls_to_the_fallback_origin(): void
    {
        $this->configureSource();
        Media::on('source')->create([
            'path' => 'media/photo.jpg',
            'url' => '/storage/media/photo.jpg',
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/database/pull')
            ->assertRedirect('/admin/database');

        $this->assertSame(
            'https://astrotherapia.com/storage/media/photo.jpg',
            Media::first()->url
        );
    }

    public function test_pull_takes_a_pre_restore_snapshot_of_dev(): void
    {
        $this->configureSource();

        $this->actingAs($this->admin())
            ->post('/admin/database/pull')
            ->assertSessionHas('status', fn (string $s) => str_contains($s, '-auto.sql.gz'));
    }

    public function test_pull_leaves_no_temp_dump_behind(): void
    {
        $this->configureSource();
        $before = glob(sys_get_temp_dir().'/proddump_*');

        $this->actingAs($this->admin())->post('/admin/database/pull');

        $after = glob(sys_get_temp_dir().'/proddump_*');
        $this->assertSame($before, $after);
    }

    public function test_pull_returns_404_when_restore_disabled(): void
    {
        config(['database_admin.restore_enabled' => false]);

        $this->actingAs($this->admin())
            ->post('/admin/database/pull')
            ->assertNotFound();
    }

    public function test_pull_errors_when_source_is_not_configured(): void
    {
        config(['database.connections.source.database' => null]);
        $this->seedLocal();

        $this->actingAs($this->admin())
            ->post('/admin/database/pull')
            ->assertRedirect()
            ->assertSessionHasErrors('pull');

        // Dev content untouched.
        $this->assertSame('Dev Draft', PostTranslation::first()->title);
    }

    public function test_guests_cannot_pull(): void
    {
        $this->post('/admin/database/pull')->assertRedirect('/admin/login');
    }

    public function test_the_pull_button_shows_when_enabled_and_source_configured(): void
    {
        $this->configureSource();

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('Copy live prod into this site');
    }

    public function test_the_pull_button_is_hidden_when_source_missing(): void
    {
        config(['database.connections.source.database' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertDontSee('Copy live prod into this site');
    }

    private function seedLocal(): void
    {
        $local = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $local->id, 'locale' => 'en',
            'title' => 'Dev Draft', 'slug' => 'dev-draft', 'body' => '<p>Draft</p>',
        ]);
    }
}
