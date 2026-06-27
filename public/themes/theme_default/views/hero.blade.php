@php($hero = \App\Models\SiteSetting::current()->hero)
<section class="stage page-home-hero">
    <div class="container">
        @if(!empty($hero['eyebrow']))<p class="eyebrow">{{ $hero['eyebrow'] }}</p>@endif
        <h1 class="title">{{ $hero['headline'] }}</h1>
        <p class="lede">{{ $hero['subhead'] }}</p>
        <p class="hero-actions">
            <a class="btn btn-primary" href="{{ $hero['cta_url'] }}">{{ $hero['cta_label'] }}</a>
            @if(!empty($hero['cta2_label']))<a class="btn btn-ghost" href="{{ $hero['cta2_url'] }}">{{ $hero['cta2_label'] }}</a>@endif
        </p>
    </div>
</section>
