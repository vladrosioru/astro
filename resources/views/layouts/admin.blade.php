{{-- Admin master layout. Deliberately theme-free: no partials.tokens, no
     ThemeManager asset loops, no theme:: views, no site nav/footer/back-to-top.
     The admin module owns its whole appearance via public/css/admin.css, so
     switching the active theme cannot change an admin screen. The one place
     the theme is still needed — the post editor's Preview — loads it inside an
     isolated iframe (see admin/posts/_form.blade.php). --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ versioned_asset('css/admin.css') }}">
    @stack('head')
</head>
<body class="adm-body">
    @yield('content')
    @stack('scripts')
</body>
</html>
