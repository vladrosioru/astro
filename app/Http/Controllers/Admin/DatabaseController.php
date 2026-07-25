<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DatabaseController extends Controller
{
    public function index()
    {
        return view('admin.database.index', [
            'backups' => collect(),
            'restoreEnabled' => (bool) config('database_admin.restore_enabled'),
        ]);
    }
}
