<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\SiteSetting;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;
use App\Services\Database\DatabaseRestoreService;
use App\Services\Database\InvalidBackupException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseRestoreServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
    }

    private function seedPost(string $title, string $body = '<p>Body</p>'): Post
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => $title,
            'slug' => Str::slug($title),
            'body' => $body,
        ]);

        return $post;
    }

    private function backupPath(string $name): string
    {
        return app(BackupRepository::class)->path($name);
    }

    /** Writes an arbitrary gzipped file into the backups directory. */
    private function fakeArchive(string $contents): string
    {
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, (string) gzencode($contents));

        return $this->backupPath($name);
    }

    public function test_a_dump_round_trips_through_a_restore(): void
    {
        $this->seedPost('Original', "<p>one; two</p>\n<p>three 'quoted'</p>");
        $name = app(DatabaseBackupService::class)->create('manual');

        PostTranslation::query()->delete();
        Post::query()->delete();
        $this->seedPost('Replaced Later');

        app(DatabaseRestoreService::class)->restore($this->backupPath($name));

        $this->assertSame(1, PostTranslation::count());
        $translation = PostTranslation::first();
        $this->assertSame('Original', $translation->title);
        $this->assertSame("<p>one; two</p>\n<p>three 'quoted'</p>", $translation->body);
    }

    public function test_it_snapshots_before_mutating(): void
    {
        $this->seedPost('Original');
        $name = app(DatabaseBackupService::class)->create('manual');

        $result = app(DatabaseRestoreService::class)->restore($this->backupPath($name));

        $this->assertStringEndsWith('-auto.sql.gz', $result['snapshot']);
        $this->assertTrue(Storage::disk('backups')->exists(BackupRepository::DIRECTORY.'/'.$result['snapshot']));
    }

    public function test_it_reports_the_number_of_restored_rows(): void
    {
        // site_settings is created lazily, so materialise it explicitly rather
        // than depending on whether something else in the test touched it.
        SiteSetting::current();
        $this->seedPost('One');
        $this->seedPost('Two');
        $name = app(DatabaseBackupService::class)->create('manual');

        $result = app(DatabaseRestoreService::class)->restore($this->backupPath($name));

        // 2 posts + 2 translations + 1 site_settings row + 0 media.
        $this->assertSame(5, $result['rows']);
    }

    public function test_it_rewrites_media_paths_when_a_fallback_url_is_configured(): void
    {
        config(['database_admin.media_fallback_url' => 'https://prod.example.com']);

        Media::create(['path' => 'media/a.jpg', 'url' => '/storage/media/a.jpg']);
        $this->seedPost('With Image', '<img src="/storage/media/a.jpg">');
        $name = app(DatabaseBackupService::class)->create('manual');

        app(DatabaseRestoreService::class)->restore($this->backupPath($name));

        $this->assertSame('https://prod.example.com/storage/media/a.jpg', Media::first()->url);
        $this->assertSame('<img src="https://prod.example.com/storage/media/a.jpg">', PostTranslation::first()->body);
    }

    public function test_it_leaves_media_paths_alone_without_a_fallback_url(): void
    {
        config(['database_admin.media_fallback_url' => null]);

        Media::create(['path' => 'media/a.jpg', 'url' => '/storage/media/a.jpg']);
        $name = app(DatabaseBackupService::class)->create('manual');

        app(DatabaseRestoreService::class)->restore($this->backupPath($name));

        $this->assertSame('/storage/media/a.jpg', Media::first()->url);
    }

    public function test_it_rejects_a_file_that_is_not_gzipped(): void
    {
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, 'not gzip at all');

        $this->expectException(InvalidBackupException::class);

        app(DatabaseRestoreService::class)->restore($this->backupPath($name));
    }

    public function test_it_rejects_a_statement_against_an_unlisted_table(): void
    {
        $path = $this->fakeArchive("DELETE FROM `users`;\n");

        $this->expectException(InvalidBackupException::class);

        app(DatabaseRestoreService::class)->restore($path);
    }

    public function test_it_rejects_a_statement_that_is_not_an_insert_or_delete(): void
    {
        $path = $this->fakeArchive("DROP TABLE `posts`;\n");

        $this->expectException(InvalidBackupException::class);

        app(DatabaseRestoreService::class)->restore($path);
    }

    public function test_a_rejected_file_leaves_existing_rows_untouched(): void
    {
        $this->seedPost('Keep Me');
        $path = $this->fakeArchive("DROP TABLE `posts`;\n");

        try {
            app(DatabaseRestoreService::class)->restore($path);
        } catch (InvalidBackupException) {
            // expected
        }

        $this->assertSame('Keep Me', PostTranslation::first()->title);
    }

    public function test_a_failure_mid_restore_rolls_back(): void
    {
        $this->seedPost('Keep Me');

        // Valid shape, but the column does not exist, so execution fails after
        // validation has already passed.
        $path = $this->fakeArchive("DELETE FROM `posts`;\nINSERT INTO `posts` (`nope`) VALUES (1);\n");

        try {
            app(DatabaseRestoreService::class)->restore($path);
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame('Keep Me', PostTranslation::first()->title);
    }
}
