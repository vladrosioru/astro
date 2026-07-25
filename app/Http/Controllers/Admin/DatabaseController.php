<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;
use App\Services\Database\DatabaseRestoreService;
use App\Services\Database\InvalidBackupException;
use Illuminate\Http\Request;

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
        return response()->download($this->backups->path($file));
    }

    public function destroy(string $file)
    {
        $this->backups->delete($file);

        return redirect()->route('admin.database.index')->with('status', 'Backup deleted.');
    }

    public function restore(Request $request)
    {
        // The gate. Prod's .env leaves DB_RESTORE_ENABLED unset, so this is a
        // 404 there and prod content can never be overwritten by this feature.
        abort_unless((bool) config('database_admin.restore_enabled'), 404);

        $request->validate([
            'backup' => ['required', 'file', 'max:'.(int) config('database_admin.max_upload_kilobytes')],
        ]);

        try {
            $result = $this->restoreService->restore($request->file('backup')->getRealPath());
        } catch (InvalidBackupException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return redirect()->route('admin.database.index')->with(
            'status',
            "Restored {$result['rows']} rows. Snapshot taken before the restore: {$result['snapshot']}",
        );
    }

    /**
     * One-click prod -> dev copy. Dumps the live prod database (the read-only
     * `source` connection) to a throwaway temp file, then runs it through the
     * same restore pipeline the upload form uses: dev is snapshotted first,
     * statements are allow-listed, media URLs are rewritten to prod's origin.
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
            $result = $this->restoreService->restore($temp);
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
