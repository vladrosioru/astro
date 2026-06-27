@php
    $setting = \App\Models\SiteSetting::current();
    $locale = app()->getLocale();
@endphp
<nav>
    <div class="container">
        {{-- Centered nav: 2 links · logo · 2 links. The logo (res/logo.jpeg →
             public/img/logo-nav.png) replaces the old text brand. --}}
        <ul class="nav-left">
            <li><a href="/{{ $locale }}">Home</a></li>
            @if ($setting->sectionVisible('about'))
                <li><a href="/{{ $locale }}/about">About</a></li>
            @endif
        </ul>
        <a class="nav-logo" href="/{{ $locale }}" aria-label="{{ config('app.name') }} — Home">
            <img src="{{ asset('img/logo-nav.png') }}" alt="{{ config('app.name') }}">
        </a>
        <ul class="nav-right">
            @if ($setting->sectionVisible('blog'))
                <li><a href="/{{ $locale }}/blog">Blog</a></li>
            @endif
            @if ($setting->sectionVisible('contact'))
                <li><a href="/{{ $locale }}/contact">Contact</a></li>
            @endif
        </ul>
    </div>
</nav>
