@extends('layouts.app')

@section('title', config('app.name'))
@section('body_class', 'page-home')

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/about.css') }}">
    <script src="{{ asset('js/about.js') }}" defer></script>
@endpush

@php
    // Same static astrology content as pages/about.blade.php, reused below the
    // homepage hero. All would-be internal links point to about:blank (this is
    // a single static page — no child pages are created). Images were copied
    // from the reference into public/img/about.
    $faqs = [
        ['q' => 'Which Sun sign am I?',
         'a' => 'Your Sun sign is set by your date of birth. The zodiac runs Aries (Mar 21–Apr 19), Taurus (Apr 20–May 20), Gemini (May 21–Jun 20) and on through Pisces. Find the range your birthday falls in and that is your Sun sign.'],
        ['q' => 'Is there a 13th zodiac sign?',
         'a' => 'You may have heard about Ophiuchus, the “serpent bearer”. Western astrology has always worked with twelve signs tied to the seasons rather than the exact backdrop of constellations, so the familiar twelve-sign zodiac stays intact.'],
        ['q' => 'Why does my Sun sign sometimes change?',
         'a' => 'Your Sun sign is always determined by your date of birth — it never really changes. Confusion happens on the cusp dates, because the Sun crosses from one sign to the next at a slightly different time each year.'],
        ['q' => 'What is a “rising sign”?',
         'a' => 'Your rising sign, or ascendant, is the zodiac sign that was climbing over the eastern horizon at the exact moment you were born. It colours your outward manner and the first impression you make on the world.'],
        ['q' => 'What is a twin flame?',
         'a' => 'A twin flame is thought of as a mirror soul — an intense connection that reflects your own strengths and shadows back at you. Astrology can help you read the chart patterns such a bond tends to share.'],
    ];
@endphp

@section('content')
    @includeIf('theme::hero')

    <main class="about">

        {{-- What is astrology --------------------------------------------------- --}}
        <section class="about-section">
            <div class="about-shell about-split about-split--stacked">
                <div class="about-split__lead">
                    <p class="about-eyebrow">Discover</p>
                    <h2 class="about-h2">What Is Astrology?</h2>
                </div>
                <div class="about-split__body">
                    <p>Astrology is the study of the influence that distant cosmic objects, usually stars and
                        planets, have on human lives. The position of the Sun, Moon and planets at the moment
                        of your birth is said to shape your personality, your relationships and the tides of
                        your fortune.</p>
                    <p>Far from cold prediction, a chart is a map — a way of reading your own patterns back to
                        you so you can move through the world with a little more self-knowledge and a little
                        more grace.</p>
                    <a class="about-link" href="/{{ $locale }}/about">Learn More <span aria-hidden="true">→</span></a>
                </div>
            </div>
        </section>

        {{-- Sun sign meaning ---------------------------------------------------- --}}
        <section class="about-section about-section--alt">
            <div class="about-shell about-split about-split--stacked">
                <div class="about-split__lead">
                    <p class="about-eyebrow">Your Core</p>
                    <h2 class="about-h2">The Meaning of the Sun Sign</h2>
                </div>
                <div class="about-split__body">
                    <p>Your Sun sign describes your basic nature and the personality traits that remain
                        constant through the ups and downs of life. It is your core identity — the steady
                        flame at the centre of the chart, and the way you instinctively present yourself to
                        the world.</p>
                    <p>Understanding it is the first doorway into astrology, and the foundation everything else
                        in your chart is read against.</p>
                    <a class="about-btn about-btn--sm" href="/{{ $locale }}/contact">Schedule Your Session</a>
                </div>
            </div>
        </section>

        {{-- From the Journal: featured newest post + paginated carousel ---------- --}}
        @if ($featuredPost)
            @php($journalHref = "/{$locale}/journal")
            <section class="about-section">
                <div class="about-shell">
                    <p class="about-eyebrow about-eyebrow--center"><a href="{{ $journalHref }}">From the Journal</a></p>
                    <h2 class="about-h2 about-h2--center">Last Astrological Journal Entries</h2>

                    {{-- Newest post, rendered identically to the Journal listing's own
                         first card (same shared partial — see AUTHORING.md). --}}
                    <div class="blog-grid blog-grid--journal">
                        @include('partials.journal-card', [
                            'post' => $featuredPost['post'],
                            'translation' => $featuredPost['translation'],
                            'locale' => $locale,
                            'first' => true,
                        ])
                    </div>

                    @if ($journalPosts->isNotEmpty())
                        <div class="about-cards" data-journal-carousel data-per-page="2">
                            @foreach ($journalPosts as $entry)
                                @php($p = $entry['post'])
                                @php($t = $entry['translation'])
                                @php($href = "/{$locale}/journal/{$t->slug}")
                                <article class="about-card" data-journal-card>
                                    <a class="about-card__media" href="{{ $href }}">
                                        <img src="{{ $p->featured_image }}" alt="{{ $t->title }}" loading="lazy" width="370" height="370">
                                    </a>
                                    <div class="about-card__body">
                                        <h3 class="about-card__title"><a href="{{ $href }}">{{ $t->title }}</a></h3>
                                        @if (!empty($t->subtitle))
                                            <p class="about-card__subtitle">{{ $t->subtitle }}</p>
                                        @endif
                                        <p class="about-card__meta">{{ optional($p->published_at)->format('M j, Y') }}</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        {{-- Populated by js/about.js: one numbered link per page of two
                             posts, plus a "→" that advances a page at a time. --}}
                        <nav class="about-pager" data-journal-pager aria-label="Journal pagination"></nav>

                        <p class="about-eyebrow about-eyebrow--center about-journal-more">
                            <a href="{{ $journalHref }}">See all the articles in the Journal <span aria-hidden="true">→</span></a>
                        </p>
                    @endif
                </div>
            </section>
        @endif

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
            </div>
        </section>

    </main>
@endsection
