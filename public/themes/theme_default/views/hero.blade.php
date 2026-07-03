@php
    $hero = array_merge(\App\Models\SiteSetting::heroDefaults(), \App\Models\SiteSetting::current()->hero ?? []);
@endphp
<section class="stage">
    <div class="container">
        <h1 class="title">{{ $hero['headline'] }}</h1>
        <p class="lede">{{ $hero['subhead'] }}</p>
        <div class="actions">
            @if (!empty($hero['cta_label']))<a class="btn btn-primary" href="{{ $hero['cta_url'] ?? '#' }}">{{ $hero['cta_label'] }}</a>@endif
            @if (!empty($hero['cta2_label']))<a class="btn btn-ghost" href="{{ $hero['cta2_url'] ?? '#' }}">{{ $hero['cta2_label'] }}</a>@endif
        </div>
    </div>
</section>
