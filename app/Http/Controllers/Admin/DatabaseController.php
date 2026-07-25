<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Database\BackupRepository;
use App\Services\Database\DatabaseBackupService;

class DatabaseController extends Controller
{
    public function __construct(
        private BackupRepository $backups,
        private DatabaseBackupService $backupService,
    ) {}

    public function index()
    {
        return view('admin.database.index', [
            'backups' => $this->backups->all(),
            'restoreEnabled' => (bool) config('database_admin.restore_enabled'),
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
}
