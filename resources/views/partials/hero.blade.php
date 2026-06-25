@php
    $hero = array_merge(\App\Models\SiteSetting::heroDefaults(), \App\Models\SiteSetting::current()->hero ?? []);
@endphp
<header class="hero">
    <div class="hero-inner">
        <h1 class="hero-title">{{ $hero['headline'] }}</h1>
        <p class="hero-subhead">{{ $hero['subhead'] }}</p>
        @if (!empty($hero['cta_label']))
            <a class="hero-cta" href="{{ $hero['cta_url'] ?? '#' }}">{{ $hero['cta_label'] }}</a>
        @endif
    </div>
</header>
