<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;
use App\Services\Database\DatabaseRestoreService;
use App\Services\Database\InvalidBackupException;
use Illuminate\Support\Facades\DB;

class DatabaseController extends Controller
{
    public function __construct(
        private BackupRepository $backups,
        private DatabaseBackupService $backupService,
        private DatabaseRestoreService $restoreService,
    ) {}

    public function index()
    {
        return view('admin.database.index', [
            'backups' => $this->backups->all(),
            'restoreEnabled' => (bool) config('database_admin.restore_enabled'),
            'sourceConfigured' => (bool) config('database.connections.source.database'),
        ]);
    }

    public function backup()
    {
        $name = $this->backupService->create('manual');
        $this->backups->prune((int) config('database_admin.retention'));

        return redirect()->route('admin.database.index')->with('status', "Backup created: {$name}");
    }

    public function download(string $file)
    {
        abort_unless($this->backups->exists($file), 404);

        return response()->download($this->backups->path($file));
    }

    public function destroy(string $file)
    {
        abort_unless($this->backups->exists($file), 404);

        $this->backups->delete($file);

        return redirect()->route('admin.database.index')->with('status', 'Backup deleted.');
    }

    /**
     * The screen that stands between a click and overwriting live content.
     * Row counts are the part that catches a wrong file — a filename does not.
     */
    public function confirm(string $file)
    {
        abort_unless($this->backups->isRestorable($file), 404);

        return view('admin.database.confirm', [
            'file' => $file,
            'header' => $this->backups->header($file),
            'live' => $this->liveCounts(),
            'host' => $this->backups->hostOf($file),
            'tables' => (array) config('database_admin.tables'),
        ]);
    }

    public function restore(string $file)
    {
        abort(404);
    }

    /** @return array<string, int> */
    private function liveCounts(): array
    {
        $counts = [];

        foreach ((array) config('database_admin.tables') as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    /**
     * One-click prod -> dev copy. Dumps the live prod database (the read-only
     * `source` connection) to a throwaway temp file, then runs it through the
     * validated restore pipeline: dev is snapshotted first, statements are
     * allow-listed, and media URLs are rewritten to prod's origin.
     */
    public function pull()
    {
        abort_unless((bool) config('database_admin.restore_enabled'), 404);

        if (! config('database.connections.source.database')) {
            return back()->withErrors(['pull' => 'The prod source database is not configured.']);
        }

        // tempnam() creates the file; use its path as-is (restore checks gzip
        // magic bytes, not the extension) so nothing is left behind.
        $temp = tempnam(sys_get_temp_dir(), 'proddump_');

        try {
            $this->backupService->dumpTo($temp, 'source');
            $result = $this->restoreService->restore($temp, config('database_admin.media_fallback_url'));
        } catch (InvalidBackupException $e) {
            return back()->withErrors(['pull' => $e->getMessage()]);
        } finally {
            @unlink($temp);
        }

        return redirect()->route('admin.database.index')->with(
            'status',
            "Copied prod → dev: {$result['rows']} rows. Dev snapshot before overwrite: {$result['snapshot']}",
        );
    }
}
