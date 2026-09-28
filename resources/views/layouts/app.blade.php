{{-- Public master layout. The admin module has its own (layouts/admin.blade.php)
     and loads none of the theme assets wired up here. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link rel="canonical" href="{{ url()->current() }}">
@hasSection('meta_description')
    <meta name="description" content="@yield('meta_description')">
@endif
@hasSection('hreflangs')
    @yield('hreflangs')
@else
@php
    $currentLocales = ['en', 'ro'];
    $currentPath = request()->path();
    $pathWithoutLocale = preg_replace('#^(' . implode('|', $currentLocales) . ')(\b|/|$)#', '', $currentPath);
    $pathWithoutLocale = ltrim($pathWithoutLocale, '/');
@endphp
@foreach ($currentLocales as $loc)
    <link rel="alternate" hreflang="{{ $loc }}" href="{{ url($loc . ($pathWithoutLocale !== '' ? '/' . $pathWithoutLocale : '')) }}">
@endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url('en' . ($pathWithoutLocale !== '' ? '/' . $pathWithoutLocale : '')) }}">
@endif
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    @include('partials.tokens')
    @foreach (app('theme.manager')->cssUrls() as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
    <link rel="stylesheet" href="{{ versioned_asset('css/back-to-top.css') }}">
    @stack('head')
</head>
<body class="@yield('body_class')">
    @includeIf('theme::cosmos')
    @include('partials.nav')
    @yield('content')
    @include('partials.footer')
    @include('partials.back-to-top')
    @foreach (app('theme.manager')->jsAssets() as $js)
        <script src="{{ $js['url'] }}" @if($js['defer'])defer @endif @if($js['async'])async @endif></script>
    @endforeach
</body>
</html>
