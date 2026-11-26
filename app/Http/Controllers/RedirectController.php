<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class RedirectController extends Controller
{
    public function home(): RedirectResponse
    {
        return redirect('/'.config('app.locale'));
    }

    public function journal(string $locale, ?string $slug = null): RedirectResponse
    {
        $target = $slug !== null ? "/{$locale}/journal/{$slug}" : "/{$locale}/journal";

        return redirect($target, 301);
    }
}
