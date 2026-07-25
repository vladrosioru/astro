<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDatabaseRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        config(['database_admin.restore_enabled' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function seedPost(string $title): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => $title,
            'slug' => Str::slug($title),
            'body' => '<p>Body</p>',
        ]);
    }

    /** Produces a real dump and hands it back as an upload. */
    private function dumpAsUpload(): UploadedFile
    {
        $name = app(DatabaseBackupService::class)->create('manual');
        $path = app(BackupRepository::class)->path($name);

        return new UploadedFile($path, $name, 'application/gzip', null, true);
    }

    public function test_the_restore_form_is_visible_when_enabled(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('Copy prod content into dev');
    }

    public function test_the_restore_form_is_hidden_when_disabled(): void
    {
        config(['database_admin.restore_enabled' => false]);

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertDontSee('Copy prod content into dev');
    }

    public function test_restore_returns_404_when_disabled(): void
    {
        config(['database_admin.restore_enabled' => false]);

        $this->actingAs($this->admin())
            ->post('/admin/database/restore', ['backup' => $this->dumpAsUpload()])
            ->assertNotFound();
    }

    public function test_an_admin_can_restore_an_uploaded_backup(): void
    {
        $this->seedPost('From Prod');
        $upload = $this->dumpAsUpload();

        PostTranslation::query()->delete();
        Post::query()->delete();
        $this->seedPost('Local Draft');

        $this->actingAs($this->admin())
            ->post('/admin/database/restore', ['backup' => $upload])
            ->assertRedirect('/admin/database');

        $this->assertSame('From Prod', PostTranslation::first()->title);
    }

    public function test_restoring_reports_the_snapshot_filename(): void
    {
        $this->seedPost('From Prod');

        $this->actingAs($this->admin())
            ->post('/admin/database/restore', ['backup' => $this->dumpAsUpload()])
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '-auto.sql.gz'));
    }

    public function test_a_non_gzip_upload_is_rejected_with_an_error(): void
    {
        $this->seedPost('Keep Me');

        $this->actingAs($this->admin())
            ->post('/admin/database/restore', [
                'backup' => UploadedFile::fake()->createWithContent('junk.sql.gz', 'not gzip'),
            ])
            ->assertSessionHasErrors('backup');

        $this->assertSame('Keep Me', PostTranslation::first()->title);
    }

    public function test_a_missing_upload_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/database/restore', [])
            ->assertSessionHasErrors('backup');
    }

    public function test_guests_cannot_restore(): void
    {
        $this->post('/admin/database/restore', [])->assertRedirect('/admin/login');
    }
}
