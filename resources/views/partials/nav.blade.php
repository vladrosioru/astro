@php
    $setting = \App\Models\SiteSetting::current();
    $locale = app()->getLocale();
    // The eyebrow wordmark rides under the logo as a permanent brand sublabel
    // (both link Home). It reuses the hero.eyebrow setting so it stays editable.
    $eyebrow = data_get($setting->hero, 'eyebrow', \App\Models\SiteSetting::heroDefaults()['eyebrow']);
@endphp
{{-- Desktop: 2 links · brand · 2 links, centered (nav-toggle-input/-btn/-scrim
     are inert — display:none / out of flow — so they don't affect this
     layout). Phone (<=720px): nav-left/nav-right collapse behind a hamburger;
     checking nav-toggle-input (via its label, the "≡" button) expands them
     into a dropdown panel below the brand, dimmed by the scrim. Pure CSS
     (checkbox hack) — no JS. The checkbox is a sibling *before* <nav> (not
     nested inside it) so CSS can react to :checked with a plain `~` sibling
     combinator — including restyling <nav> itself — without needing the
     :has() relational selector, which isn't supported by every browser. --}}
<input type="checkbox" id="nav-toggle" class="nav-toggle-input">
<nav>
    <div class="container">
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
        <label for="nav-toggle" class="nav-toggle-btn" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </label>
        <label for="nav-toggle" class="nav-scrim" aria-hidden="true"></label>
    </div>
</nav>
