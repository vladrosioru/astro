<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Post;
use App\Services\Database\BackupRepository;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(private BackupRepository $backups) {}

    public function index()
    {
        $backups = $this->backups->all();

        return view('admin.dashboard', [
            'postCount' => Post::count(),
            'draftCount' => Post::where('status', 'draft')->count(),
            'authorCount' => Author::count(),
            'authorsWithoutPosts' => Author::doesntHave('posts')->count(),
            'backupCount' => $backups->count(),
            'latestBackupAt' => $this->takenAt($backups->first()['name'] ?? null),
            'activeTheme' => app('theme.manager')->manifest(),
            'recentPosts' => Post::with('translations')->latest()->take(5)->get(),
        ]);
    }

    /**
     * Backups carry no stored timestamp — the moment is encoded in the
     * filename by BackupRepository::filename() (backup-{Ymd}-{His}-…), which
     * is also what makes a lexical sort newest-first. Parse it back rather
     * than stat()-ing the file, so the figure survives a copy or a redeploy.
     */
    private function takenAt(?string $name): ?Carbon
    {
        if ($name === null || ! preg_match('/^backup-(\d{8})-(\d{6})-/', $name, $matches)) {
            return null;
        }

        return Carbon::createFromFormat('Ymd His', $matches[1].' '.$matches[2]) ?: null;
    }
}
