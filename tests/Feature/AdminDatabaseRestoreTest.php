<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDatabaseRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        config(['app.url' => 'https://example.com']);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @param  array<string, int>|null  $rows */
    private function putBackup(string $name, ?array $rows = ['authors' => 0, 'posts' => 41, 'post_translations' => 82, 'media' => 210, 'site_settings' => 1]): string
    {
        $header = "-- Content backup\n-- created: 2026-01-01T09:30:00+00:00\n";

        if ($rows !== null) {
            $header .= '-- rows: '.collect($rows)->map(fn (int $n, string $table) => "{$table}={$n}")->implode(', ')."\n";
        }

        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, (string) gzencode($header));

        return $name;
    }

    public function test_the_confirmation_page_shows_backup_counts_beside_live_ones(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertOk()
            ->assertSee($name)
            ->assertSeeInOrder(['Table', 'In backup', 'Live now'])
            ->assertSeeInOrder(['posts', '41']);
    }

    public function test_the_confirmation_page_asks_for_the_host(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertOk()
            ->assertSee('to confirm');
    }

    public function test_a_backup_from_another_host_cannot_be_confirmed(): void
    {
        $name = $this->putBackup('backup-20260101-000000-dev.example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertNotFound();
    }

    public function test_a_missing_backup_cannot_be_confirmed(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database/restore/backup-20260101-000000-example.com-manual.sql.gz')
            ->assertNotFound();
    }

    public function test_the_confirmation_page_rejects_a_traversal_attempt(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database/restore/..%2F..%2F.env')
            ->assertNotFound();
    }

    public function test_guests_cannot_reach_the_confirmation_page(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->get('/admin/database/restore/'.$name)->assertRedirect('/admin/login');
    }

    public function test_a_backup_without_row_counts_says_so_and_stays_restorable(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz', null);

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertOk()
            ->assertSee('Row counts are unavailable');
    }

    public function test_the_listing_offers_restore_for_an_own_host_backup(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('Restore');
    }

    public function test_the_listing_does_not_offer_restore_for_a_foreign_backup(): void
    {
        $this->putBackup('backup-20260101-000000-dev.example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertDontSee('Restore');
    }

    /** A real dump of current content, written through the production path. */
    private function backupCurrentContent(): string
    {
        return app(DatabaseBackupService::class)->create('manual');
    }

    public function test_restoring_brings_back_the_content_in_the_backup(): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id, 'locale' => 'en',
            'title' => 'Original', 'slug' => 'original', 'body' => '<p>Body</p>',
        ]);

        $name = $this->backupCurrentContent();

        PostTranslation::query()->update(['title' => 'Wrecked']);

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertRedirect('/admin/database');

        $this->assertSame('Original', PostTranslation::first()->title);
    }

    public function test_restoring_snapshots_the_current_content_first(): void
    {
        $name = $this->backupCurrentContent();

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '-auto.sql.gz'));
    }

    public function test_restoring_a_backup_from_another_host_is_not_found(): void
    {
        $name = $this->putBackup('backup-20260101-000000-dev.example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertNotFound();
    }

    public function test_restoring_a_missing_backup_is_not_found(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/database/restore/backup-20260101-000000-example.com-manual.sql.gz')
            ->assertNotFound();
    }

    public function test_a_corrupt_backup_is_refused_and_changes_nothing(): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id, 'locale' => 'en',
            'title' => 'Untouched', 'slug' => 'untouched', 'body' => '<p>Body</p>',
        ]);

        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, 'not gzip at all');

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertRedirect()
            ->assertSessionHasErrors('restore');

        $this->assertSame('Untouched', PostTranslation::first()->title);
    }

    public function test_a_backup_naming_a_table_outside_the_allow_list_is_refused(): void
    {
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, (string) gzencode(
            "-- Content backup\nDELETE FROM `users`;\n"
        ));

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertRedirect()
            ->assertSessionHasErrors('restore');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_restoring_leaves_media_paths_alone(): void
    {
        config(['database_admin.media_fallback_url' => 'https://astrotherapia.com']);
        Media::create(['path' => 'media/photo.jpg', 'url' => '/storage/media/photo.jpg']);

        $name = $this->backupCurrentContent();

        $this->actingAs($this->admin())
            ->post('/admin/database/restore/'.$name)
            ->assertRedirect('/admin/database');

        $this->assertSame('/storage/media/photo.jpg', Media::first()->url);
    }

    public function test_guests_cannot_restore(): void
    {
        $this->post('/admin/database/restore/backup-20260101-000000-example.com-manual.sql.gz')
            ->assertRedirect('/admin/login');
    }
}
