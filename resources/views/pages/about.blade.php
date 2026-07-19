@extends('layouts.app')

@section('title', 'Astrology — ' . config('app.name'))
@section('body_class', 'page-about')

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/about.css') }}">
    <script src="{{ asset('js/about.js') }}" defer></script>
@endpush

@php
    $locale = app()->getLocale();
    $contactVisible = \App\Models\SiteSetting::current()->sectionVisible('contact');

    $faqs = [
        ['q' => 'Is this the same as reading my horoscope?',
         'a' => 'No. A horoscope guesses what might happen today for an entire sign. A reading works from your exact chart to explain the patterns already running in your life — why, not what\'s next.'],
        ['q' => 'Will you tell me what\'s going to happen?',
         'a' => 'No — and be skeptical of any reading that claims to. I can show you the pattern behind the question you\'re carrying. What you do with it is yours to decide.'],
        ['q' => 'What if I don\'t believe in astrology?',
         'a' => 'You don\'t need to. Think of the chart as a structured way to talk about patterns you already sense in yourself — belief isn\'t a prerequisite for the conversation to be useful.'],
        ['q' => 'How is this different from therapy?',
         'a' => 'It isn\'t a replacement for it. I\'m not a therapist, and this isn\'t clinical treatment — it\'s a different lens, one you might bring into therapy, a journal, or your own reflection.'],
        ['q' => 'Are the archetypes (like "Guardian" or "Builder") fixed personality types?',
         'a' => 'No — they\'re patterns you\'re currently running, not permanent labels. The same chart can show a different archetype rising depending on the season of life you\'re in.'],
        ['q' => 'What do you need from me to book a session?',
         'a' => 'Your exact birth date, time, and place. Time matters more than people expect — it sets your rising sign and house placements, and without it a chart can only say so much.'],
        ['q' => 'Do you keep what I share private?',
         'a' => 'Yes. If a session ever informs something I write, it\'s anonymized and reshaped enough that it isn\'t recognizable — the pattern matters, not your personal details.'],
    ];
@endphp

@section('content')
<main class="about">

    {{-- Top graphic — Option 1: mini natal-chart wheel (static SVG, token-driven). --}}
    <section class="about-chart-motif" aria-hidden="true">
        <svg class="about-chart-motif__svg" viewBox="0 0 240 240" xmlns="http://www.w3.org/2000/svg">
            {{-- Fire trine: Aries–Leo–Sagittarius --}}
            <polygon class="about-chart-motif__aspect" points="120,52 179,154 61,154" />
            {{-- Earth trine: Taurus–Virgo–Capricorn --}}
            <polygon class="about-chart-motif__aspect" points="154,61 154,179 52,120" />
            {{-- Air trine: Gemini–Libra–Aquarius --}}
            <polygon class="about-chart-motif__aspect" points="179,86 120,188 61,86" />
            {{-- Water trine: Cancer–Scorpio–Pisces --}}
            <polygon class="about-chart-motif__aspect" points="188,120 86,179 86,61" />

            <circle class="about-chart-motif__ring about-chart-motif__ring--inner" cx="120" cy="120" r="68" />
            <circle class="about-chart-motif__ring about-chart-motif__ring--outer" cx="120" cy="120" r="100" />

            @foreach ([0,30,60,90,120,150,180,210,240,270,300,330] as $deg)
                @php
                    $rad = deg2rad($deg - 90);
                    $x1 = 120 + 68 * cos($rad); $y1 = 120 + 68 * sin($rad);
                    $x2 = 120 + 100 * cos($rad); $y2 = 120 + 100 * sin($rad);
                @endphp
                <line class="about-chart-motif__spoke" x1="{{ round($x1) }}" y1="{{ round($y1) }}" x2="{{ round($x2) }}" y2="{{ round($y2) }}" />
            @endforeach

            @foreach (['♈','♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓'] as $i => $glyph)
                @php
                    $rad = deg2rad($i * 30 - 90);
                    $x = 120 + 84 * cos($rad); $y = 120 + 84 * sin($rad);
                @endphp
                <text class="about-chart-motif__glyph" x="{{ round($x) }}" y="{{ round($y) }}">{{ $glyph }}&#xFE0E;</text>
            @endforeach

            <circle class="about-chart-motif__sun" cx="120" cy="120" r="6" />
        </svg>
    </section>

    {{-- Manifesto: the "why, not what" philosophy ---------------------------- --}}
    <section class="about-section">
        <div class="about-shell about-shell--narrow">
            <p class="about-eyebrow about-eyebrow--center">Discover</p>
            <h2 class="about-h2 about-h2--center">Understanding the Why Behind Your Choices</h2>
            <p class="about-hero__sub">Your birth chart is the key to help you understand why you
                think, feel, and choose the way you do — so you can make your next decision with
                clarity, not guesswork.</p>
            <div class="about-manifesto__body">
                <p>Most people meet astrology one of two ways. Either as something to roll their eyes
                    at — a horoscope column, a party-trick sun sign, a "you'll meet someone tall, dark,
                    and interesting" — or as something to lean on a little too hard, waiting for the sky
                    to hand down an answer they're afraid to find on their own. I understand both
                    reactions. I've had them myself. But neither one is what a birth chart is actually
                    for.</p>
                <p>A birth chart isn't a prediction. It's a mirror. It's the exact arrangement of the
                    sky at the moment you took your first breath, and it doesn't say what will happen to
                    you — it says something far more useful: why you tend to respond to your life the
                    way you do. Why the same argument keeps circling back in your relationships. Why
                    money feels like proof of something instead of just money. Why you push through when
                    you're exhausted instead of resting. Why a decision that should be simple keeps not
                    getting made.</p>
                <p>That "why" is the whole practice, and it's the reason I don't call what I do
                    fortune-telling. Fortune-telling asks the chart to do your thinking for you. I ask it
                    to show you what you're already doing, clearly enough that you can finally choose
                    something different — or choose to keep going, on purpose instead of on autopilot.</p>
                <p>Here's what that looks like across the parts of life clients actually bring to me.</p>
                <p>In relationships, the chart doesn't tell you who to love. It tends to show you the
                    pattern underneath who you keep loving — why safety can feel safer than passion, or
                    why closeness sometimes feels like losing yourself. Once you can see the pattern, you
                    get to decide whether it's still serving you.</p>
                <p>In money and self-worth, it's rarely about the money for long. It's usually about what
                    security means to you, and where you learned to measure your worth by what you've
                    built, saved, or given away. The chart won't tell you to spend or save. It can show
                    you why that decision feels so loaded in the first place.</p>
                <p>In health and energy, it's not about diagnosis — that's not what this is, and I'll
                    always say so plainly. It's about noticing the rhythm underneath your stress: when
                    you tend to override your body instead of listening to it, and why rest can feel like
                    something you have to earn rather than something you're allowed.</p>
                <p>And in the decisions that actually keep you up at night — the ones about timing, about
                    whether now is the moment — the chart doesn't predict the outcome. It can explain the
                    resistance. Sometimes "is this the right time" is really "am I ready," and those are
                    different questions with different answers.</p>
                <p>Over years of reading charts, I keep meeting the same handful of patterns wearing
                    different names. Someone who tests every commitment before they'll trust it. Someone
                    who builds and builds and never quite says when enough is enough. Someone who takes
                    care of everyone except themselves and calls it strength. I've started thinking of
                    these as archetypes — not fixed identities, not boxes, but patterns you're currently
                    running, the way you might currently be running an old piece of software. You're not
                    "a" Guardian or "a" Builder forever. You tend to run that pattern right now, in this
                    season, for reasons that made sense once. And a pattern you can name is a pattern you
                    can work with — that's the growth edge, every time.</p>
                <p>This is what I mean when I say astrology helps you understand why, not what. I'm not
                    going to tell you what happens next — that part is genuinely, entirely yours. What I
                    can do is sit with your chart and hand you back the pattern underneath the question
                    you came in with, clearly enough that your next decision comes from insight instead
                    of guesswork.</p>
                <p>If any of this sounds like the conversation you've been circling around on your own,
                    that's usually a good sign it's time to have it out loud. Book a session, and let's
                    find out what your chart has been trying to tell you.</p>
            </div>
        </div>
    </section>

    {{-- Astrology intro: how a birth chart works + why it matters ----------- --}}
    <section class="about-section about-section--alt">
        <div class="about-shell about-split about-split--stacked">
            <div class="about-split__lead">
                <p class="about-eyebrow">Your Core</p>
                <h2 class="about-h2">Why Knowing Your Patterns Can Change What Happens Next</h2>
            </div>
            <div class="about-split__body">
                <p>Have you ever noticed you keep making the same choice, even when it doesn't work?
                    Maybe it's the same kind of relationship. Maybe it's how you deal with money, or
                    stress, or big decisions. Most people call this "just who I am." But it's usually a
                    pattern — and patterns can be understood.</p>
                <p>Your birth chart is a simple tool for this. It's based on the exact place and moment
                    you were born. From that, we can see patterns that tend to show up again and again
                    in your life — in love, in money, in health, in the choices you keep putting off.</p>
                <p>This isn't about guessing your future. Astrology here doesn't predict what will
                    happen. It helps you see why things keep happening the way they do. And once you can
                    see the pattern clearly, you get to choose what to do with it.</p>
                <p>That's the real change. Not a prediction. A clearer view of yourself — one you can
                    actually use.</p>
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
