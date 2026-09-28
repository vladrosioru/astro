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

    <!-- Open Graph -->
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="@yield('meta_og_type', 'website')">
    <meta property="og:title" content="@hasSection('meta_og_title')@yield('meta_og_title')@else@yield('title', config('app.name'))@endif">
    <meta property="og:description" content="@hasSection('meta_og_description')@yield('meta_og_description')@else@yield('meta_description')@endif">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'ro' ? 'ro_RO' : 'en_US' }}">
    <meta property="og:locale:alternate" content="{{ app()->getLocale() === 'ro' ? 'en_US' : 'ro_RO' }}">
    <meta property="og:image" content="@hasSection('meta_og_image')@yield('meta_og_image')@else{{ asset('img/og-default.jpg') }}@endif">
    <meta property="og:image:width" content="@yield('meta_og_image_width', '1200')">
    <meta property="og:image:height" content="@yield('meta_og_image_height', '630')">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="@yield('meta_twitter_card', 'summary_large_image')">
    <meta name="twitter:title" content="@hasSection('meta_twitter_title')@yield('meta_twitter_title')@else@yield('title', config('app.name'))@endif">
    <meta name="twitter:description" content="@hasSection('meta_twitter_description')@yield('meta_twitter_description')@else@yield('meta_description')@endif">
    <meta name="twitter:image" content="@hasSection('meta_twitter_image')@yield('meta_twitter_image')@else{{ asset('img/og-default.jpg') }}@endif">

    @include('partials.tokens')
    @foreach (app('theme.manager')->cssUrls() as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
    <link rel="stylesheet" href="{{ versioned_asset('css/back-to-top.css') }}">
    @stack('head')
    @include('partials.seo-schema')
    @stack('schema')
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
