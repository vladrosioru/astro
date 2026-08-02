<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
    }

    private function contents(string $name): string
    {
        return (string) gzdecode(Storage::disk('backups')->get(BackupRepository::DIRECTORY.'/'.$name));
    }

    public function test_it_writes_a_gzipped_backup_containing_seeded_rows(): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'body' => '<p>Body</p>',
        ]);

        $name = app(DatabaseBackupService::class)->create('manual');
        $sql = $this->contents($name);

        $this->assertStringContainsString('INSERT INTO `posts`', $sql);
        $this->assertStringContainsString('Hello World', $sql);
        $this->assertStringContainsString('DELETE FROM `post_translations`;', $sql);
    }

    public function test_deletes_run_children_before_parents(): void
    {
        $name = app(DatabaseBackupService::class)->create('manual');
        $sql = $this->contents($name);

        $this->assertLessThan(
            strpos($sql, 'DELETE FROM `posts`;'),
            strpos($sql, 'DELETE FROM `post_translations`;'),
        );
    }

    public function test_every_statement_stays_on_one_line(): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => "Multi\nLine; Title 'quoted'",
            'slug' => 'multi-line',
            'body' => "<p>one</p>\n<p>two; three</p>\r\n<p>four</p>",
        ]);

        $sql = $this->contents(app(DatabaseBackupService::class)->create('manual'));

        foreach (explode("\n", trim($sql)) as $line) {
            if ($line === '' || str_starts_with($line, '--')) {
                continue;
            }
            $this->assertStringEndsWith(';', $line, "Statement was split across lines: {$line}");
        }
    }

    public function test_it_never_dumps_excluded_tables(): void
    {
        $sql = $this->contents(app(DatabaseBackupService::class)->create('manual'));

        foreach (['users', 'sessions', 'cache', 'jobs'] as $table) {
            $this->assertStringNotContainsString('`'.$table.'`', $sql);
        }
    }

    public function test_the_header_records_a_row_count_per_table(): void
    {
        $post = Post::create(['status' => 'published']);
        PostTranslation::create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Counted',
            'slug' => 'counted',
            'body' => '<p>Body</p>',
        ]);

        $sql = $this->contents(app(DatabaseBackupService::class)->create('manual'));

        $rows = collect(explode("\n", $sql))->first(fn (string $line) => str_starts_with($line, '-- rows: '));

        $this->assertNotNull($rows, 'The dump has no "-- rows:" header line.');
        $this->assertStringContainsString('authors=0', $rows);
        $this->assertStringContainsString('posts=1', $rows);
        $this->assertStringContainsString('post_translations=1', $rows);
    }

    public function test_the_origin_is_recorded_in_the_filename(): void
    {
        $this->assertStringEndsWith('-auto.sql.gz', app(DatabaseBackupService::class)->create('auto'));
    }
}
