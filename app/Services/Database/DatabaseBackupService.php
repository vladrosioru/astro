<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Pure-PHP dumper. The host disables exec() (.github/scripts/make-env.sh:45),
 * so mysqldump is unavailable and every byte here is written over PDO.
 *
 * Rows are read in chunks and written straight into the gzip stream, so memory
 * stays flat regardless of table size.
 */
class DatabaseBackupService
{
    private const CHUNK = 500;

    public function __construct(private BackupRepository $backups) {}

    /**
     * @param  'manual'|'auto'  $origin
     * @return string the backup filename
     */
    public function create(string $origin = 'manual'): string
    {
        Storage::disk('backups')->makeDirectory(BackupRepository::DIRECTORY);

        $name = $this->backups->filename($origin);
        $this->dumpTo($this->backups->path($name));

        return $name;
    }

    /** Gzip a content dump of $connection (default when null) to $path. */
    public function dumpTo(string $path, ?string $connection = null): void
    {
        $handle = gzopen($path, 'wb6');

        if ($handle === false) {
            throw new RuntimeException('Could not open the backup file for writing.');
        }

        try {
            $this->writeDump($handle, $connection);
        } finally {
            gzclose($handle);
        }
    }

    /** @param  resource  $handle */
    private function writeDump($handle, ?string $connection): void
    {
        $db = DB::connection($connection);
        $tables = (array) config('database_admin.tables');
        $quoter = QuoterFactory::for($db->getDriverName());

        $this->line($handle, '-- Content backup');
        $this->line($handle, '-- source: '.config('app.url'));
        $this->line($handle, '-- created: '.now()->toIso8601String());
        $this->line($handle, '-- tables: '.implode(', ', $tables));

        // Children first so the cascading foreign key on post_translations is
        // never the thing doing the deleting.
        foreach (array_reverse($tables) as $table) {
            $this->line($handle, 'DELETE FROM `'.$table.'`;');
        }

        // Parents first, so every child row has its parent present.
        foreach ($tables as $table) {
            $this->writeTable($handle, $db, $table, $quoter);
        }
    }

    /** @param  resource  $handle */
    private function writeTable($handle, $db, string $table, SqlQuoter $quoter): void
    {
        $columns = Schema::connection($db->getName())->getColumnListing($table);

        if ($columns === []) {
            return;
        }

        $columnList = implode(', ', array_map(fn (string $c) => '`'.$c.'`', $columns));

        $db->table($table)->orderBy('id')->chunk(self::CHUNK, function ($rows) use ($handle, $table, $columns, $columnList, $quoter) {
            $tuples = [];

            foreach ($rows as $row) {
                $values = array_map(
                    fn (string $column) => $quoter->quote(((array) $row)[$column] ?? null),
                    $columns
                );
                $tuples[] = '('.implode(',', $values).')';
            }

            $this->line($handle, 'INSERT INTO `'.$table.'` ('.$columnList.') VALUES '.implode(',', $tuples).';');
        });
    }

    /** @param  resource  $handle */
    private function line($handle, string $text): void
    {
        gzwrite($handle, $text."\n");
    }
}
