@php
    $setting = \App\Models\SiteSetting::current();
    $locale = app()->getLocale();
    // The eyebrow wordmark rides under the logo as a permanent brand sublabel
    // (both link Home). It reuses the hero.eyebrow setting so it stays editable.
    $eyebrow = data_get($setting->hero, 'eyebrow', \App\Models\SiteSetting::heroDefaults()['eyebrow']);
@endphp
<nav>
    <div class="container">
        {{-- Centered nav: 2 links · brand · 2 links. The brand stacks the logo
             (res/logo.jpeg → public/img/logo-nav.png) with the ASTROTHERAPIA
             eyebrow sublabel beneath it; both are links back to Home. --}}
        <ul class="nav-left">
            @if ($setting->sectionVisible('about'))
                <li><a href="/{{ $locale }}/about">About</a></li>
            @endif
            {{-- 'blog' is the internal feature key; the menu label + URL are "Articles". --}}
            @if ($setting->sectionVisible('blog'))
                <li><a href="/{{ $locale }}/articles">Articles</a></li>
            @endif
        </ul>
        <div class="nav-brand">
            <a class="nav-logo" href="/{{ $locale }}" aria-label="{{ config('app.name') }} — Home">
                <img src="{{ asset('img/logo-nav.png') }}" alt="{{ config('app.name') }}">
            </a>
            @if (!empty($eyebrow))
                <a class="nav-eyebrow" href="/{{ $locale }}"><span class="rule"></span>{{ $eyebrow }}<span class="rule"></span></a>
            @endif
        </div>
        <ul class="nav-right">
            @if ($setting->sectionVisible('services'))
                <li><a href="/{{ $locale }}/services">Services</a></li>
            @endif
            @if ($setting->sectionVisible('contact'))
                <li><a href="/{{ $locale }}/contact">Contact</a></li>
            @endif
        </ul>
    </div>
</nav>
