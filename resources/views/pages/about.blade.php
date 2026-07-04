@extends('layouts.app')

@section('title', 'Astrology — ' . config('app.name'))

@push('head')
    <link rel="stylesheet" href="{{ asset('css/about.css') }}">
    <script src="{{ asset('js/about.js') }}" defer></script>
@endpush

@php
    // Static reproduction of the mystik astrology demo, re-skinned in the local
    // Solar System theme. All would-be internal links point to about:blank (this
    // is a single static page — no child pages are created). Images were copied
    // from the reference into public/img/about.
    $img = fn ($f) => asset('img/about/' . $f);

    $readings = [
        ['img' => 'image-74-copyright-min-370x240.jpg', 'cat' => 'Astrology',
         'title' => 'A Libra Season Meditation to Collaborate, Cooperate & Co-Create', 'date' => 'Mar 25, 2019'],
        ['img' => 'image-19-copyright-min-370x240.jpg', 'cat' => 'Tarot',
         'title' => 'Ace Cards in Tarot', 'date' => 'Mar 25, 2019'],
        ['img' => 'image-59-copyright-min-370x240.jpg', 'cat' => 'Astrology',
         'title' => 'Supermoon Equinox 2019', 'date' => 'Mar 25, 2019'],
    ];

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

    $signs = [
        ['name' => 'Aries',       'glyph' => '♈', 'dates' => 'Mar 21 – Apr 19'],
        ['name' => 'Taurus',      'glyph' => '♉', 'dates' => 'Apr 20 – May 20'],
        ['name' => 'Gemini',      'glyph' => '♊', 'dates' => 'May 21 – Jun 20'],
        ['name' => 'Cancer',      'glyph' => '♋', 'dates' => 'Jun 21 – Jul 22'],
        ['name' => 'Leo',         'glyph' => '♌', 'dates' => 'Jul 23 – Aug 22'],
        ['name' => 'Virgo',       'glyph' => '♍', 'dates' => 'Aug 23 – Sep 22'],
        ['name' => 'Libra',       'glyph' => '♎', 'dates' => 'Sep 23 – Oct 22'],
        ['name' => 'Scorpio',     'glyph' => '♏', 'dates' => 'Oct 23 – Nov 21'],
        ['name' => 'Sagittarius', 'glyph' => '♐', 'dates' => 'Nov 22 – Dec 21'],
        ['name' => 'Capricorn',   'glyph' => '♑', 'dates' => 'Dec 22 – Jan 19'],
        ['name' => 'Aquarius',    'glyph' => '♒', 'dates' => 'Jan 20 – Feb 18'],
        ['name' => 'Pisces',      'glyph' => '♓', 'dates' => 'Feb 19 – Mar 20'],
    ];

    $horoscopes = [
        'Aries' => "You're in the middle of a terrific yearly transition. You have a great deal of physical energy, Aries. You have an action-oriented mind ready to tackle anything. The key to making the most of this fortunate period is communication. Right now you have multitasking abilities that you can put to good use once you connect with others and understand exactly what needs to be done.",
        'Taurus' => "Try not to get too caught up in any potential conflicts brewing around you, Taurus. Your job lies in calming things down and bringing a more practical perspective to the situation. If you get tangled in the action phase of endeavors without first thinking about what it is that you're doing, you may confuse things more. Step back from the fire instead of throwing yourself into it headfirst.",
        'Gemini' => "This is a great time to move forward on a writing project, Gemini. Any large, long-term project involving communication, film, or long-distance travel is begging you to take action. Don't delay. You have a strong force urging you to move forward. Look ahead with a positive attitude instead of thinking of all the reasons why these projects won't pan out the way you want them to.",
        'Cancer' => "This is an expansive time for you. You can make great progress on your goals, Cancer. The key is to clear up any miscommunication or dishonesty before you move forward with a clear conscience. Don't even bother trying to make progress before you've cleared up past cobwebs. Keeping everything on a light, flexible track will help you work more efficiently.",
        'Leo' => "Your engine is revved and ready, Leo. You have a full tank of gas. Unfortunately, you may feel like there's a large obstacle in your way. Perhaps this obstacle is your mental attitude and inability to make confident decisions. You may become so scattered at times that you can't effectively move forward on anything. Don't beat yourself up over it. The answers will come when you need them.",
        'Virgo' => "No one likes rejection, but no one likes rejection less than you, Virgo. You may hesitate to take risks in the unknown. Keep in mind that by playing it safe, you deprive yourself of the very adventure that could turn your life around. There's an energetic, expansive feeling in the air encouraging you to take that leap of faith. This energy may feel foreign to you, but it's time to embrace it.",
        'Libra' => "Be flexible in your communication, Libra, and doors will open to you that you didn't even know were there. There's a tremendous amount of physical energy at your disposal. Don't waste it. By being rigid about your ways and insisting on doing things only according to your philosophy, you deprive yourself of the spontaneous adventures that give life the spice and variety you love.",
        'Scorpio' => "You may be in a difficult position, Scorpio. You want to explode into a new way of life yet feel stuck. Perhaps you feel chained to your current routine. You may feel like you're indeed making progress in the world, but you long for a giant release – like a trap door opening – that allows you to make a leap into the great beyond. This door is always open.",
        'Sagittarius' => "You're getting support for and confidence from one aspect of your life and physical energy from another. Even though the two areas may be in a point of conflict, Sagittarius, you have the ability to take the positive aspects from each and fuse them together to create something new or solve a problem. Pool your resources and shift into high gear. The sky's the limit.",
        'Capricorn' => "Success will come to you when you work with the energies at hand. Go with the flow of the situation instead of trying to undermine or manipulate it. There's a tremendous force at work. Perhaps all it needs is a bit of direction to align it with your goals. State your intentions openly instead of working behind the scenes. You will receive support from others when you do.",
        'Aquarius' => "You may end up in some arguments, Aquarius. Your nature is expansive and generous, but if others take advantage of this good nature, your mood quickly turns to anger and detachment. Conflict is often a natural part of a relationship. Use it as a learning experience instead of blowing it out of proportion and turning it into a larger issue than it needs to be.",
        'Pisces' => "You may be confused about asking for help, Pisces. Your usual resources could be occupied with issues and conflicts that have nothing to do with you. You may then offer to help others. By doing this, you've put someone else's needs above your own. Although this may feel good to you on some level, it's also a way to avoid the problems that you need to deal with.",
    ];

    $signMeta = collect($signs)->keyBy('name');

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
<main class="about">

    {{-- Title band --------------------------------------------------------- --}}
    <header class="about-hero">
        <div class="about-shell">
            <nav class="about-crumb" aria-label="Breadcrumb">
                <a href="about:blank">Home</a><span aria-hidden="true">›</span><span>Astrology</span>
            </nav>
            <h1 class="about-hero__title">Astrology</h1>
            <p class="about-hero__sub">The study of how the distant lights above us mirror the story unfolding within.</p>
            <a class="about-btn" href="about:blank">Begin Here</a>
        </div>
    </header>

    {{-- What is astrology --------------------------------------------------- --}}
    <section class="about-section">
        <div class="about-shell about-split">
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
                <a class="about-link" href="about:blank">Learn More <span aria-hidden="true">→</span></a>
            </div>
        </div>
    </section>

    {{-- Sun sign meaning ---------------------------------------------------- --}}
    <section class="about-section about-section--alt">
        <div class="about-shell about-split about-split--reverse">
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
                <a class="about-btn about-btn--sm" href="about:blank">Schedule Your Session</a>
            </div>
        </div>
    </section>

    {{-- Astrological readings + pagination ---------------------------------- --}}
    <section class="about-section">
        <div class="about-shell">
            <p class="about-eyebrow about-eyebrow--center">From the Journal</p>
            <h2 class="about-h2 about-h2--center">Astrological Readings</h2>
            <div class="about-cards">
                @foreach ($readings as $r)
                    <article class="about-card">
                        <a class="about-card__media" href="about:blank">
                            <img src="{{ $img($r['img']) }}" alt="{{ $r['title'] }}" loading="lazy" width="370" height="240">
                        </a>
                        <div class="about-card__body">
                            <span class="about-card__cat">{{ $r['cat'] }}</span>
                            <h3 class="about-card__title"><a href="about:blank">{{ $r['title'] }}</a></h3>
                            <p class="about-card__meta">{{ $r['date'] }} · 0 Comments</p>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Numbered pager. On the reference these page through more posts; here
                 every target is a static about:blank (no child pages are created). --}}
            <nav class="about-pager" aria-label="Readings pagination">
                <a class="about-pager__link is-active" href="about:blank" aria-current="page">1</a>
                <a class="about-pager__link" href="about:blank">2</a>
                <a class="about-pager__link" href="about:blank">3</a>
                <span class="about-pager__gap">…</span>
                <a class="about-pager__link about-pager__link--next" href="about:blank" aria-label="Next page">→</a>
            </nav>
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
        </div>
    </section>

    {{-- Zodiac signs grid --------------------------------------------------- --}}
    <section class="about-section">
        <div class="about-shell">
            <p class="about-eyebrow about-eyebrow--center">The Wheel</p>
            <h2 class="about-h2 about-h2--center">The Twelve Zodiac Signs</h2>
            <div class="about-zodiac">
                @foreach ($signs as $s)
                    <a class="about-sign" href="#horo-{{ Str::lower($s['name']) }}">
                        <span class="about-sign__glyph" aria-hidden="true">{{ $s['glyph'] }}&#xFE0E;</span>
                        <span class="about-sign__name">{{ $s['name'] }}</span>
                        <span class="about-sign__dates">{{ $s['dates'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Daily horoscope intro ----------------------------------------------- --}}
    <section class="about-section about-section--alt">
        <div class="about-shell about-shell--narrow about-center">
            <p class="about-eyebrow about-eyebrow--center">Today</p>
            <h2 class="about-h2 about-h2--center">What's Your Sign? Read Your Daily Horoscope</h2>
            <p class="about-lede">The planets are always moving, and so is your story. Read today's forecast for
                your sign below — then book a session to go deeper than the daily sky.</p>
            <a class="about-btn" href="about:blank">Schedule Your Session</a>
        </div>
    </section>

    {{-- 12 horoscope forecasts ---------------------------------------------- --}}
    <section class="about-section">
        <div class="about-shell">
            <div class="about-horoscopes">
                @foreach ($horoscopes as $name => $text)
                    @php $meta = $signMeta[$name]; @endphp
                    <article class="about-horo" id="horo-{{ Str::lower($name) }}">
                        <div class="about-horo__head">
                            <span class="about-horo__glyph" aria-hidden="true">{{ $meta['glyph'] }}&#xFE0E;</span>
                            <div>
                                <h3 class="about-horo__name">{{ $name }}</h3>
                                <p class="about-horo__dates">{{ $meta['dates'] }}</p>
                            </div>
                        </div>
                        <p class="about-horo__text">{{ $text }}</p>
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
