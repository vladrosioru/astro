@extends('layouts.app')

@section('title', 'Services — ' . config('app.name'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/about.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/services.css') }}">
    <script src="{{ asset('js/about.js') }}" defer></script>
    <script src="{{ asset('js/services.js') }}" defer></script>
@endpush

@php
    // Adapted from the mystik "Our Services" reference, re-skinned in the
    // local Solar System theme. Hero, section rhythm, buttons and the
    // testimonials carousel reuse the About page's about.css/about.js
    // (about-hero, about-section, about-testi, etc.); services.css/js add
    // only the category tabs and the service card grid.
    $img = fn ($f) => asset('img/about/' . $f);
    $locale = app()->getLocale();

    $categories = [
        'astrology' => 'Astrology',
        'tarot'     => 'Tarotscopes',
        'energy'    => 'Energy Healing',
    ];

    $services = [
        ['id' => 'natal-chart-analysis', 'cat' => 'astrology', 'glyph' => '☉', 'title' => 'Natal Chart Analysis',
         'desc' => "A full read of your birth chart — Sun, Moon, rising sign and the placements between them — decoded into a clear picture of your personality, strengths and blind spots."],
        ['id' => 'progressions-solar-returns', 'cat' => 'astrology', 'glyph' => '⟳', 'title' => 'Progressions & Solar Returns',
         'desc' => "Your birth chart advanced to today, plus a fresh chart cast for your solar return, showing how the current sky is moving through your life right now."],
        ['id' => 'relationship-analysis', 'cat' => 'astrology', 'glyph' => '♡', 'title' => 'Relationship Analysis',
         'desc' => "A comparison of two charts — synastry and composite — to see where you naturally align with a partner, friend or family member, and where the friction comes from."],
        ['id' => 'elective-horary-charts', 'cat' => 'astrology', 'glyph' => '◔', 'title' => 'Elective & Horary Charts',
         'desc' => "Elective astrology times a decision — a launch, a move, a signature — for a favourable sky. Horary answers a specific question by reading the chart cast for the moment you asked it."],
        ['id' => 'daily-horoscope', 'cat' => 'astrology', 'glyph' => '☀', 'title' => 'Daily Horoscope',
         'desc' => "A short daily forecast for your Sun sign, keyed to the Moon and the day's fastest-moving transits, so you know where today's sky is pulling you."],
        ['id' => 'astro-travel', 'cat' => 'astrology', 'glyph' => '✦', 'title' => 'Astro Travel',
         'desc' => "An astrocartography reading that maps your chart onto the globe, showing which cities and regions light up specific parts of your life — useful before a move, a relocation, or a big trip."],
        ['id' => 'yearly-horoscope', 'cat' => 'astrology', 'glyph' => '✧', 'title' => 'Yearly Horoscope',
         'desc' => "A year-ahead forecast across love, work and growth, built from the major transits and returns crossing your chart over the next twelve months."],
        ['id' => 'childs-horoscope', 'cat' => 'astrology', 'glyph' => '☾', 'title' => "Child's Horoscope",
         'desc' => "A gentle first reading of your child's birth chart, translated into plain, age-appropriate guidance for temperament, learning style and what they need from you."],

        ['id' => 'single-card-draw', 'cat' => 'tarot', 'glyph' => '✦', 'title' => 'Single Card Draw',
         'desc' => "One card, one clear answer — quick guidance for a question that's been sitting with you."],
        ['id' => 'three-card-spread', 'cat' => 'tarot', 'glyph' => '❖', 'title' => 'Three-Card Spread',
         'desc' => "Past, present and future laid out side by side, so you can see where a situation has been and where it's actually heading."],
        ['id' => 'love-relationship-tarot', 'cat' => 'tarot', 'glyph' => '♡', 'title' => 'Love & Relationship Tarot',
         'desc' => "A spread built around one relationship — what's really going on beneath the surface, and what the cards suggest you do next."],
        ['id' => 'full-life-reading', 'cat' => 'tarot', 'glyph' => '◈', 'title' => 'Full Life Reading',
         'desc' => "A longer, ten-card spread across love, work and personal growth, for when there's more than one question on your mind."],

        ['id' => 'reiki-session', 'cat' => 'energy', 'glyph' => '✧', 'title' => 'Reiki Session',
         'desc' => "Gentle, hands-on (or remote) energy work to release tension, calm the nervous system and restore balance."],
        ['id' => 'chakra-balancing', 'cat' => 'energy', 'glyph' => '☉', 'title' => 'Chakra Balancing',
         'desc' => "A guided session to locate blocked or overactive chakras and bring your seven energy centres back into balance."],
        ['id' => 'crystal-healing', 'cat' => 'energy', 'glyph' => '◇', 'title' => 'Crystal Healing',
         'desc' => "Specific stones placed on or around the body to support a particular area of healing or a stated intention."],
        ['id' => 'cord-cutting-clearing', 'cat' => 'energy', 'glyph' => '⟡', 'title' => 'Cord-Cutting & Clearing',
         'desc' => "A closing ritual to release old attachments and energetic ties that no longer serve you, clearing space for what's next."],
    ];

    $testimonials = [
        ['img' => 'image-22-copyright-min-90x90.jpg', 'name' => 'Jenna Mackenzie', 'city' => 'Milan',
         'quote' => 'Alice created my personal horoscope and interpreted my natal chart in a way that finally made sense of the life problems I kept circling back to. I left our session lighter and clearer.'],
        ['img' => 'image-57-copyright-min-90x90.jpg', 'name' => 'Maya Gabriella', 'city' => 'Boston',
         'quote' => 'What impressed me most was how Alice synthesised information from a variety of different sources into one coherent, personal reading. Nothing felt generic — it was all mine.'],
        ['img' => 'image-40-copyright-min-90x90.jpg', 'name' => 'Avery Mia', 'city' => 'Los Angeles',
         'quote' => 'Alice gave me a brand-new perspective on the problem areas I had been stuck in for years. The reading was warm, precise, and genuinely useful.'],
        ['img' => 'image-50-copyright-min-90x90.jpg', 'name' => 'Clara Jenkins', 'city' => 'Paris',
         'quote' => 'Beyond the reading itself, Alice offered clear ways to develop and a real plan of how to move ahead. I still return to her notes months later.'],
    ];
@endphp

@section('content')
<main class="about services">

    {{-- Title band --------------------------------------------------------- --}}
    <header class="about-hero">
        <div class="about-shell">
            <nav class="about-crumb" aria-label="Breadcrumb">
                <a href="/{{ $locale }}">Home</a><span aria-hidden="true">›</span><span>Services</span>
            </nav>
            <h1 class="about-hero__title">Our Services</h1>
            <p class="about-hero__sub">Astrology, tarot and energy work — ways to read the sky and the self, chosen to fit the question you're carrying.</p>
            <a class="about-btn" href="/{{ $locale }}/contact">Book a Session</a>
        </div>
    </header>

    {{-- Service categories + card grid ---------------------------------------- --}}
    <section class="about-section">
        <div class="about-shell">
            <p class="about-eyebrow about-eyebrow--center">What We Offer</p>
            <h2 class="about-h2 about-h2--center">Readings & Sessions</h2>
            <p class="about-lede about-center">Every reading starts with a conversation about what you actually want to know. Browse by category below, or book a session and we'll work out the right fit together.</p>

            <div class="svc-tabs" data-svc-tabs role="tablist">
                @foreach ($categories as $key => $label)
                    <button type="button" class="svc-tab @if($loop->first) is-active @endif" data-svc-tab="{{ $key }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="svc-grid" data-svc-grid>
                @foreach ($services as $s)
                    <article class="svc-card" id="{{ $s['id'] }}" data-svc-cat="{{ $s['cat'] }}" @if($s['cat'] !== 'astrology') hidden @endif>
                        <span class="svc-card__icon" aria-hidden="true">{{ $s['glyph'] }}&#xFE0E;</span>
                        <p class="svc-card__cat">{{ $categories[$s['cat']] }}</p>
                        <h3 class="svc-card__title">{{ $s['title'] }}</h3>
                        <p class="svc-card__desc">{{ $s['desc'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Testimonials carousel (with pagination dots) ------------------------ --}}
    <section class="about-section about-section--alt">
        <div class="about-shell">
            <p class="about-eyebrow about-eyebrow--center">Kind Words</p>
            <h2 class="about-h2 about-h2--center">What Clients Say</h2>

            <div class="about-testi" data-testi>
                <button class="about-testi__arrow about-testi__arrow--prev" type="button" data-testi-prev aria-label="Previous testimonial">‹</button>

                <div class="about-testi__viewport">
                    <div class="about-testi__track" data-testi-track>
                        @foreach ($testimonials as $t)
                            <figure class="about-testi__item">
                                <blockquote class="about-testi__quote">“{{ $t['quote'] }}”</blockquote>
                                <figcaption class="about-testi__who">
                                    <img class="about-testi__avatar" src="{{ $img($t['img']) }}"
                                         alt="{{ $t['name'] }}" loading="lazy" width="90" height="90">
                                    <span class="about-testi__name">{{ $t['name'] }}</span>
                                    <span class="about-testi__city">{{ $t['city'] }}</span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>

                <button class="about-testi__arrow about-testi__arrow--next" type="button" data-testi-next aria-label="Next testimonial">›</button>

                <div class="about-testi__dots" data-testi-dots aria-hidden="true">
                    @foreach ($testimonials as $i => $t)
                        <button class="about-testi__dot @if($i === 0) is-active @endif" type="button" data-testi-dot="{{ $i }}"></button>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

</main>
@endsection
