<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @include('partials.tokens')
    <link rel="stylesheet" href="{{ asset('css/structure.css') }}">
    <link rel="stylesheet" href="{{ asset('css/skin.css') }}">
</head>
<body>
    @include('partials.nav')
    @yield('content')
</body>
</html>
