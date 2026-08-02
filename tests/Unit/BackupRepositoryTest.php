<?php

namespace Tests\Unit;

use App\Services\Database\BackupRepository;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class BackupRepositoryTest extends TestCase
{
    private BackupRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        $this->repository = new BackupRepository;
    }

    private function putBackup(string $name): void
    {
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, 'x');
    }

    public function test_it_lists_backups_newest_first(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');
        $this->putBackup('backup-20260301-000000-example.com-manual.sql.gz');
        $this->putBackup('backup-20260201-000000-example.com-auto.sql.gz');

        $names = $this->repository->all()->pluck('name')->all();

        $this->assertSame([
            'backup-20260301-000000-example.com-manual.sql.gz',
            'backup-20260201-000000-example.com-auto.sql.gz',
            'backup-20260101-000000-example.com-manual.sql.gz',
        ], $names);
    }

    public function test_it_labels_the_origin(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-auto.sql.gz');

        $this->assertSame('auto (pre-restore)', $this->repository->all()->first()['origin']);
    }

    public function test_it_ignores_files_that_are_not_backups(): void
    {
        $this->putBackup('notes.txt');
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->assertCount(1, $this->repository->all());
    }

    public function test_prune_keeps_only_the_newest_n(): void
    {
        foreach (['20260101', '20260102', '20260103', '20260104'] as $day) {
            $this->putBackup("backup-{$day}-000000-example.com-manual.sql.gz");
        }

        $this->repository->prune(2);

        $this->assertSame([
            'backup-20260104-000000-example.com-manual.sql.gz',
            'backup-20260103-000000-example.com-manual.sql.gz',
        ], $this->repository->all()->pluck('name')->all());
    }

    public function test_delete_removes_a_backup(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->repository->delete('backup-20260101-000000-example.com-manual.sql.gz');

        $this->assertCount(0, $this->repository->all());
    }

    public function test_delete_rejects_a_traversal_attempt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository->delete('../../.env');
    }

    public function test_path_rejects_a_traversal_attempt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository->path('../../.env');
    }

    public function test_exists_distinguishes_a_present_backup_from_a_missing_one(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->assertTrue($this->repository->exists('backup-20260101-000000-example.com-manual.sql.gz'));
        $this->assertFalse($this->repository->exists('backup-20260102-000000-example.com-manual.sql.gz'));
    }

    public function test_exists_returns_false_for_a_traversal_attempt_without_throwing(): void
    {
        $this->assertFalse($this->repository->exists('../../.env'));
    }

    public function test_filename_encodes_origin_and_is_pattern_valid(): void
    {
        $name = $this->repository->filename('auto');

        $this->assertMatchesRegularExpression(BackupRepository::FILENAME_PATTERN, $name);
        $this->assertStringEndsWith('-auto.sql.gz', $name);
    }
}
