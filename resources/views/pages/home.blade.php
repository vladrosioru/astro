@extends('layouts.app')

@section('title', config('app.name'))
@section('body_class', 'page-home')

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/about.css') }}">
    <script src="{{ asset('js/about.js') }}" defer></script>
@endpush

@section('content')
    @includeIf('theme::hero')

    <main class="about">

        {{-- What is astrology --------------------------------------------------- --}}
        <section class="about-section">
            <div class="about-shell about-split about-split--stacked">
                <div class="about-split__lead">
                    <p class="about-eyebrow">Discover</p>
                    <h2 class="about-h2">What is AstroTHERAPIA?</h2>
                </div>
                <div class="about-split__body">
                    <p>Have you ever noticed you keep making the same choice, even when it doesn't work? Maybe
                        it's the same kind of relationship. Maybe it's how you deal with money, or stress, or big
                        decisions. Most people call this "just who I am." But it's usually a pattern — and
                        patterns can be understood.</p>
                    <p>Far from cold prediction, a chart is a map — a way of reading your own patterns back to
                        you so you can move through the world with a little more self-knowledge and a little
                        more grace.</p>
                    <a class="btn btn-primary" href="/{{ $locale }}/about">Learn More <span aria-hidden="true">→</span></a>
                </div>
            </div>
        </section>

        {{-- From the Journal: featured newest post + paginated carousel ---------- --}}
        @if ($featuredPost)
            @php($journalHref = "/{$locale}/journal")
            <section class="about-section about-section--alt">
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

    </main>
@endsection
