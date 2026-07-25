<?php

namespace App\Services\Database;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Backup files live on the private `backups` disk, rooted at the project's
 * database/ directory, under database/backups/. Never the public disk: these
 * dumps are content exports and must not be web-reachable. Downloads go through
 * DatabaseController::download().
 *
 * The location survives deploys: public/extract.php overlays the CI archive
 * (it never wipes the tree) and database/backups/ is not an archive entry, so
 * existing backups are left untouched — the same guarantee storage/ gets.
 */
class BackupRepository
{
    public const DIRECTORY = 'backups';

    /**
     * backup-{YmdHis}-{host}-{origin}.sql.gz
     *
     * The timestamp leads so a descending lexical sort is newest-first; a
     * host-first name would sort by hostname and interleave prod dumps with
     * dev's pre-restore snapshots.
     */
    public const FILENAME_PATTERN = '/^backup-\d{8}-\d{6}-[A-Za-z0-9.-]+-(manual|auto)\.sql\.gz$/';

    /** @param  'manual'|'auto'  $origin */
    public function filename(string $origin): string
    {
        $host = preg_replace('/[^A-Za-z0-9.-]/', '-', (string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return sprintf('backup-%s-%s-%s.sql.gz', now()->format('Ymd-His'), $host ?: 'local', $origin);
    }

    /** @return Collection<int, array{name: string, size: int, origin: string}> */
    public function all(): Collection
    {
        $disk = Storage::disk('backups');

        return collect($disk->files(self::DIRECTORY))
            ->map(fn (string $path) => basename($path))
            ->filter(fn (string $name) => (bool) preg_match(self::FILENAME_PATTERN, $name))
            ->sortDesc()
            ->values()
            ->map(fn (string $name) => [
                'name' => $name,
                'size' => $disk->size(self::DIRECTORY.'/'.$name),
                'origin' => str_ends_with($name, '-auto.sql.gz') ? 'auto (pre-restore)' : 'manual',
            ]);
    }

    public function prune(int $keep): void
    {
        $this->all()->slice($keep)->each(fn (array $backup) => $this->delete($backup['name']));
    }

    public function delete(string $name): void
    {
        Storage::disk('backups')->delete(self::DIRECTORY.'/'.$this->assertValid($name));
    }

    /** Absolute filesystem path to a backup. */
    public function path(string $name): string
    {
        return Storage::disk('backups')->path(self::DIRECTORY.'/'.$this->assertValid($name));
    }

    /** The only place a caller-supplied filename is trusted. */
    private function assertValid(string $name): string
    {
        if (! preg_match(self::FILENAME_PATTERN, $name)) {
            throw new InvalidArgumentException('Not a backup filename.');
        }

        return $name;
    }
}
