<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function index()
    {
        $themes = app('theme.manager')->available();

        return view('admin.themes.index', compact('themes'));
    }

    public function update(Request $request)
    {
        $names = array_column(app('theme.manager')->available(), 'name');

        $data = $request->validate([
            'theme' => ['required', 'string', Rule::in($names)],
        ]);

        SiteSetting::current()->update(['theme' => $data['theme']]);
        Artisan::call('view:clear');

        return redirect('/admin/themes')->with('status', "Theme switched to {$data['theme']}.");
    }
}
