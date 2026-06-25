<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'ro'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        abort_unless(in_array($locale, self::SUPPORTED, true), 404);

        app()->setLocale($locale);

        return $next($request);
    }
}
