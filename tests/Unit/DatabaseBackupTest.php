<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Services\Database\DatabaseBackupService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    public function test_dump_to_targets_a_named_connection_and_path(): void
    {
        // A separate sqlite file acts as the stand-in "prod" source.
        $sourceDb = tempnam(sys_get_temp_dir(), 'srcdb_');
        config(['database.connections.source' => [
            'driver' => 'sqlite',
            'database' => $sourceDb,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        Artisan::call('migrate', ['--database' => 'source', '--force' => true]);

        Post::on('source')->create(['status' => 'published']);
        PostTranslation::on('source')->create([
            'post_id' => 1, 'locale' => 'en',
            'title' => 'Source Row', 'slug' => 'source-row', 'body' => '<p>x</p>',
        ]);

        $out = tempnam(sys_get_temp_dir(), 'dump_');
        app(DatabaseBackupService::class)->dumpTo($out, 'source');
        $sql = (string) gzdecode(file_get_contents($out));

        @unlink($out);
        @unlink($sourceDb);

        $this->assertStringContainsString('INSERT INTO `posts`', $sql);
        $this->assertStringContainsString('Source Row', $sql);
        $this->assertStringContainsString('DELETE FROM `post_translations`;', $sql);
    }
}
