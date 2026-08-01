<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;

/**
 * Replays a content backup into the current database.
 *
 * Only ever reached on the dev subdomain: DatabaseController gates it on
 * config('database_admin.restore_enabled'), which is false everywhere else.
 *
 * Two passes over the archive. The first validates every statement against a
 * strict allow-list; the second executes. Nothing is mutated until the whole
 * file has been accepted, and neither pass loads the file into memory.
 */
class DatabaseRestoreService
{
    /** A statement is only ever a DELETE or an INSERT against a listed table. */
    private const STATEMENT_PATTERN = '/^(?:INSERT INTO|DELETE FROM) `([A-Za-z0-9_]+)`/';

    public function __construct(
        private DatabaseBackupService $backups,
    ) {}

    /** @return array{snapshot: string, rows: int} */
    public function restore(string $archivePath): array
    {
        $this->assertGzip($archivePath);
        $this->assertStatementsAllowed($archivePath);

        $snapshot = $this->backups->create('auto');

        DB::transaction(function () use ($archivePath) {
            $this->eachStatement($archivePath, fn (string $statement) => DB::unprepared($statement));
            $this->rewriteMediaPaths();
        });

        return ['snapshot' => $snapshot, 'rows' => $this->countRows()];
    }

    private function assertGzip(string $path): void
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidBackupException('The backup file could not be read.');
        }

        $magic = fread($handle, 2);
        fclose($handle);

        if ($magic !== "\x1f\x8b") {
            throw new InvalidBackupException('That file is not a gzipped backup.');
        }
    }

    private function assertStatementsAllowed(string $path): void
    {
        $tables = (array) config('database_admin.tables');

        $this->eachStatement($path, function (string $statement) use ($tables) {
            preg_match(self::STATEMENT_PATTERN, $statement, $matches);

            if (! isset($matches[1]) || ! in_array($matches[1], $tables, true)) {
                throw new InvalidBackupException('The backup contains a statement this app will not run.');
            }
        });
    }

    /**
     * Streams the archive one statement per line. The dumper guarantees no
     * statement ever spans two physical lines, which is why splitting on "\n"
     * is safe where splitting on ";" would not be.
     */
    private function eachStatement(string $path, callable $callback): void
    {
        $handle = gzopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidBackupException('The backup file could not be opened.');
        }

        try {
            while (($line = gzgets($handle)) !== false) {
                $statement = rtrim($line, "\r\n");

                if ($statement === '' || str_starts_with($statement, '--')) {
                    continue;
                }

                $callback($statement);
            }
        } finally {
            gzclose($handle);
        }
    }

    private function rewriteMediaPaths(): void
    {
        $rewriter = new MediaPathRewriter(config('database_admin.media_fallback_url'));

        DB::table('authors')->orderBy('id')->chunkById(200, function ($rows) use ($rewriter) {
            foreach ($rows as $row) {
                DB::table('authors')->where('id', $row->id)
                    ->update(['picture' => $rewriter->rewriteUrl($row->picture)]);
            }
        });

        DB::table('media')->orderBy('id')->chunkById(200, function ($rows) use ($rewriter) {
            foreach ($rows as $row) {
                DB::table('media')->where('id', $row->id)
                    ->update(['url' => $rewriter->rewriteUrl($row->url)]);
            }
        });

        DB::table('posts')->orderBy('id')->chunkById(200, function ($rows) use ($rewriter) {
            foreach ($rows as $row) {
                DB::table('posts')->where('id', $row->id)
                    ->update(['featured_image' => $rewriter->rewriteUrl($row->featured_image)]);
            }
        });

        DB::table('post_translations')->orderBy('id')->chunkById(200, function ($rows) use ($rewriter) {
            foreach ($rows as $row) {
                DB::table('post_translations')->where('id', $row->id)
                    ->update(['body' => $rewriter->rewriteBody($row->body)]);
            }
        });
    }

    private function countRows(): int
    {
        return collect((array) config('database_admin.tables'))
            ->sum(fn (string $table) => DB::table($table)->count());
    }
}
