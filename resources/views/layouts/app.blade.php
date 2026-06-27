<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    @include('partials.tokens')
    <link rel="stylesheet" href="{{ asset('css/structure.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cosmos.css') }}">
    <link rel="stylesheet" href="{{ asset('css/skin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/hero-solarsystem.css') }}">
    @stack('head')
</head>
<body class="@yield('body_class')">
    @include('partials.cosmos')
    @include('partials.nav')
    @yield('content')
    <script src="{{ asset('js/solarsystem.js') }}" defer></script>
</body>
</html>
