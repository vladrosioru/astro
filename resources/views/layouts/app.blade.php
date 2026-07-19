<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @include('partials.tokens')
    @foreach (app('theme.manager')->cssUrls() as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
    @stack('head')
</head>
<body class="@yield('body_class')">
    @includeIf('theme::cosmos')
    @include('partials.nav')
    @yield('content')
    @unless(request()->routeIs('admin.*'))
        @include('partials.footer')
    @endunless
    @foreach (app('theme.manager')->jsAssets() as $js)
        <script src="{{ $js['url'] }}" @if($js['defer'])defer @endif @if($js['async'])async @endif></script>
    @endforeach
</body>
</html>
