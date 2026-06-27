@php
    $hero = array_merge(\App\Models\SiteSetting::heroDefaults(), \App\Models\SiteSetting::current()->hero ?? []);
@endphp
<section class="stage">
    {{-- Cosmos background is shared site-wide (partials/cosmos.blade.php) and
         shows through the transparent stage. Only the solar system + parallax
         live here. --}}

    {{-- solar system --}}
    <div class="solar-wrap" data-parallax="solar">
        <div class="plane">
            <div class="orbit orbit-1"><div class="anchor"><div class="planet planet-mercury"></div></div></div>
            <div class="orbit orbit-2"><div class="anchor"><div class="planet planet-venus"></div></div></div>
            <div class="orbit orbit-3"><div class="anchor"><div class="planet planet-earth"></div></div></div>
            <div class="orbit orbit-4"><div class="anchor"><div class="planet planet-mars"></div></div></div>
            <div class="orbit orbit-5"><div class="anchor"><div class="planet planet-saturn">
                <span class="saturn-ring"></span><span class="saturn-body"></span>
            </div></div></div>
        </div>
        <div class="sun"></div>
    </div>

    <div class="vignette"></div>

    {{-- hero copy --}}
    <main class="hero">
        @if (!empty($hero['eyebrow']))
            <p class="eyebrow"><span class="rule"></span>{{ $hero['eyebrow'] }}<span class="rule"></span></p>
        @endif
        <h1 class="title">{{ $hero['headline'] }}</h1>
        <p class="lede">{{ $hero['subhead'] }}</p>
        <div class="actions">
            @if (!empty($hero['cta_label']))
                <a href="{{ $hero['cta_url'] ?? '#' }}" class="btn btn-primary">{{ $hero['cta_label'] }}</a>
            @endif
            @if (!empty($hero['cta2_label']))
                <a href="{{ $hero['cta2_url'] ?? '#' }}" class="btn btn-ghost">{{ $hero['cta2_label'] }} &rarr;</a>
            @endif
        </div>
    </main>

    <div class="scroll-cue">Scroll<span class="arrow">&darr;</span></div>
</section>
