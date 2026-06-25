@php
    $setting = \App\Models\SiteSetting::current();
    $locale = app()->getLocale();
@endphp
<nav>
    <div class="container">
        <a class="brand" href="/{{ $locale }}">✦ {{ config('app.name') }} ✦</a>
        <ul>
            <li><a href="/{{ $locale }}">Home</a></li>
            @if ($setting->sectionVisible('about'))
                <li><a href="/{{ $locale }}/about">About</a></li>
            @endif
            @if ($setting->sectionVisible('blog'))
                <li><a href="/{{ $locale }}/blog">Blog</a></li>
            @endif
            @if ($setting->sectionVisible('contact'))
                <li><a href="/{{ $locale }}/contact">Contact</a></li>
            @endif
        </ul>
    </div>
</nav>
