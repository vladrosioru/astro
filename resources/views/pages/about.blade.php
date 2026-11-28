@extends('layouts.app')

@section('title', app()->getLocale() === 'ro'
    ? 'Despre AstroTherapia — Înțelege tiparele din spatele deciziilor · ' . config('app.name')
    : 'About AstroTherapia — Understanding the Why Behind Your Choices · ' . config('app.name'))
@section('meta_description', app()->getLocale() === 'ro'
    ? 'Ce este AstroTherapia? O abordare bazată pe harta natală ce dezvăluie tiparele din spatele deciziilor tale — nu predicții, ci claritate. Descoperă filosofia.'
    : 'What is AstroTherapia? A birth-chart-based practice that reveals the patterns behind your decisions — not predictions, but clarity. Discover the philosophy.')
@section('body_class', 'page-about')

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/about.css') }}">
    <script src="{{ versioned_asset('js/about.js') }}" defer></script>
@endpush

@php
    $locale = app()->getLocale();
    $setting = \App\Models\SiteSetting::current();
    $contactVisible = $setting->sectionVisible('contact');
    $about = $setting->aboutContent();
    $faqs = $about['faqs'];
    $splitParagraphs = static fn (string $text): array => array_values(array_filter(
        array_map('trim', preg_split('/\R{2,}/', trim($text)) ?: []),
        fn (string $p) => $p !== ''
    ));
@endphp

@push('schema')
@php
    $faqEntities = [];
    foreach ($faqs as $faq) {
        $faqEntities[] = [
            '@type' => 'Question',
            'name' => $faq['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['a'],
            ],
        ];
    }
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faqEntities,
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endpush

@section('content')
<main class="about">

    {{-- Manifesto: the "why, not what" philosophy ---------------------------- --}}
    <section class="about-section">
        <div class="about-shell about-shell--narrow">
            @include('partials.about-chart-motif')
            <p class="about-eyebrow about-eyebrow--center">{{ $about['manifesto_eyebrow'] }}</p>
            <h1 class="about-h2 about-h2--center">{{ $about['manifesto_title'] }}</h1>
            <p class="about-hero__sub">{{ $about['manifesto_subhead'] }}</p>
            <div class="about-manifesto__body">
                @foreach ($splitParagraphs($about['manifesto_body']) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Astrology intro: how a birth chart works + why it matters ----------- --}}
    <section class="about-section about-section--alt">
        <div class="about-shell about-split about-split--stacked">
            <div class="about-split__lead">
                @include('partials.about-chart-motif')
                <p class="about-eyebrow">{{ $about['core_eyebrow'] }}</p>
                <h2 class="about-h2">{{ $about['core_title'] }}</h2>
            </div>
            <div class="about-split__body">
                @foreach ($splitParagraphs($about['core_body']) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ ----------------------------------------------------------------- --}}
    <section class="about-section about-section--alt">
        <div class="about-shell about-shell--narrow">
            <p class="about-eyebrow about-eyebrow--center">Good to Know</p>
            <h2 class="about-h2 about-h2--center">Frequently Asked Questions</h2>
            <div class="about-faq">
                @foreach ($faqs as $i => $f)
                    <details class="about-faq__item" @if($i === 0) open @endif>
                        <summary class="about-faq__q">{{ $f['q'] }}</summary>
                        <div class="about-faq__a"><p>{{ $f['a'] }}</p></div>
                    </details>
                @endforeach
            </div>
            @if ($contactVisible)
                <p class="about-manifesto__cta">
                    <a class="about-btn about-btn--sm" href="/{{ $locale }}/contact">Schedule Your Session</a>
                </p>
            @endif
        </div>
    </section>

</main>
@endsection
