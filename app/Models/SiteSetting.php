<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    public const CACHE_KEY = 'site_settings.v1';

    protected $guarded = [];

    protected $casts = [
        'sections' => 'array',
        'nav' => 'array',
        'contact' => 'array',
        'branding' => 'array',
        'locales' => 'array',
        'hero' => 'array',
        'about' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCurrentCache());
        static::deleted(fn () => static::flushCurrentCache());
    }

    public static function current(): self
    {
        $container = app();
        if ($container->bound('site_setting.current')) {
            return $container->make('site_setting.current');
        }

        // Cache raw attributes (plain array) rather than an Eloquent object,
        // respecting config/cache.php `serializable_classes => false`.
        $attributes = Cache::rememberForever(self::CACHE_KEY, function (): array {
            return static::firstOrCreate(['id' => 1], static::defaults())->getAttributes();
        });

        $instance = (new static)->newFromBuilder($attributes);
        $container->instance('site_setting.current', $instance);

        return $instance;
    }

    public static function flushCurrentCache(): void
    {
        app()->forgetInstance('site_setting.current');
        Cache::forget(self::CACHE_KEY);
    }

    public static function defaults(): array
    {
        return [
            'sections' => ['about' => true, 'blog' => true, 'services' => true, 'contact' => true],
            'nav' => [],
            'contact' => ['email' => '', 'phone' => '', 'address' => ''],
            'branding' => [],
            'theme' => 'solarsystem',
            'locales' => ['default' => 'en', 'supported' => ['en', 'ro']],
            'hero' => self::heroDefaults(),
            'about' => self::aboutDefaults(),
        ];
    }

    public static function aboutDefaults(): array
    {
        return [
            'manifesto_eyebrow' => 'Discover',
            'manifesto_title' => 'Understanding the Why Behind Your Choices',
            'manifesto_subhead' => 'Your birth chart is the key to help you understand why you think, feel, and choose the way you do — so you can make your next decision with clarity, not guesswork.',
            'manifesto_body' => implode("\n\n", [
                'Most people meet astrology one of two ways. Either as something to roll their eyes at — a horoscope column, a party-trick sun sign, a "you\'ll meet someone tall, dark, and interesting" — or as something to lean on a little too hard, waiting for the sky to hand down an answer they\'re afraid to find on their own. I understand both reactions. I\'ve had them myself. But neither one is what a birth chart is actually for.',
                'A birth chart isn\'t a prediction. It\'s a mirror. It\'s the exact arrangement of the sky at the moment you took your first breath, and it doesn\'t say what will happen to you — it says something far more useful: why you tend to respond to your life the way you do. Why the same argument keeps circling back in your relationships. Why money feels like proof of something instead of just money. Why you push through when you\'re exhausted instead of resting. Why a decision that should be simple keeps not getting made.',
                'That "why" is the whole practice, and it\'s the reason I don\'t call what I do fortune-telling. Fortune-telling asks the chart to do your thinking for you. I ask it to show you what you\'re already doing, clearly enough that you can finally choose something different — or choose to keep going, on purpose instead of on autopilot.',
                'Here\'s what that looks like across the parts of life clients actually bring to me — and it usually starts from the same place.',
                '◎ At the center of it is identity — the Sun and the Ascendant, the part of the chart that\'s simply you. Why you feel most like yourself in some seasons, and like a stranger to yourself in others. Every pattern below spins around this one, the same way a chart\'s other houses spin around the degree that was rising the moment you were born. Get a little clearer on this, and the rest tends to follow.',
                '♡ Relationships spin off from there first, more often than not. The chart doesn\'t tell you who to love. It tends to show you the pattern underneath who you keep loving — why safety can feel safer than passion, or why closeness sometimes feels like losing yourself. Once you can see the pattern, you get to decide whether it\'s still serving you.',
                '⟟ Work and purpose orbit the same center — the Midheaven, the part of the chart that asks what you\'re building and who\'s watching you build it. Why some work feels meaningful and other work just quietly drains you, even when it looks fine on paper. The chart won\'t tell you which job to take. It can show you why the choice keeps feeling harder than it should.',
                '◐ Money and self-worth circle around here too. It\'s rarely about the money for long. It\'s usually about what security means to you, and where you learned to measure your worth by what you\'ve built, saved, or given away. The chart won\'t tell you to spend or save. It can show you why that decision feels so loaded in the first place.',
                '☼ And so does the body — health and energy, the rhythm underneath your stress. That\'s not about diagnosis, and I\'ll always say so plainly. It\'s about noticing when you tend to override your body instead of listening to it, and why rest can feel like something you have to earn rather than something you\'re allowed.',
                'Over years of reading charts, I keep meeting the same handful of patterns wearing different names. Someone who tests every commitment before they\'ll trust it. Someone who builds and builds and never quite says when enough is enough. Someone who takes care of everyone except themselves and calls it strength. I\'ve started thinking of these as archetypes — not fixed identities, not boxes, but patterns you\'re currently running, the way you might currently be running an old piece of software. You\'re not "a" Guardian or "a" Builder forever. You tend to run that pattern right now, in this season, for reasons that made sense once. And a pattern you can name is a pattern you can work with — that\'s the growth edge, every time.',
                'This is what I mean when I say astrology helps you understand why, not what. I\'m not going to tell you what happens next — that part is genuinely, entirely yours. What I can do is sit with your chart and hand you back the pattern underneath the question you came in with, clearly enough that your next decision comes from insight instead of guesswork.',
                'If any of this sounds like the conversation you\'ve been circling around on your own, that\'s usually a good sign it\'s time to have it out loud. Book a session, and let\'s find out what your chart has been trying to tell you.',
            ]),
            'core_eyebrow' => 'Your Core',
            'core_title' => 'Why Knowing Your Patterns Can Change What Happens Next',
            'core_body' => implode("\n\n", [
                'Have you ever noticed you keep making the same choice, even when it doesn\'t work? Maybe it\'s the same kind of relationship. Maybe it\'s how you deal with money, or stress, or big decisions. Most people call this "just who I am." But it\'s usually a pattern — and patterns can be understood.',
                'Your birth chart is a simple tool for this. It\'s based on the exact place and moment you were born. From that, we can see patterns that tend to show up again and again in your life — in love, in money, in health, in the choices you keep putting off.',
                'This isn\'t about guessing your future. Astrology here doesn\'t predict what will happen. It helps you see why things keep happening the way they do. And once you can see the pattern clearly, you get to choose what to do with it.',
                'That\'s the real change. Not a prediction. A clearer view of yourself — one you can actually use.',
            ]),
            'faqs' => [
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
                ['q' => 'How much does a session cost?',
                    'a' => 'It depends on what you need — prices vary by service, from a single tarot card draw to a full natal chart reading. Reach out by email or phone with what you\'re interested about, and I\'ll get either the basic price list or the quotation for your special request.'],
            ],
        ];
    }

    public function aboutContent(): array
    {
        $defaults = self::aboutDefaults();
        $stored = is_array($this->about) ? $this->about : [];

        $merged = array_merge(
            $defaults,
            array_filter($stored, fn ($v, $k) => $k !== 'faqs' && is_string($v) && trim($v) !== '', ARRAY_FILTER_USE_BOTH)
        );

        if (isset($stored['faqs']) && is_array($stored['faqs'])) {
            $cleanFaqs = array_values(array_filter(
                $stored['faqs'],
                fn ($f) => is_array($f) && trim((string) ($f['q'] ?? '')) !== '' && trim((string) ($f['a'] ?? '')) !== ''
            ));
            if ($cleanFaqs !== []) {
                $merged['faqs'] = $cleanFaqs;
            }
        }

        return $merged;
    }

    public function contactEmail(): string
    {
        $email = trim((string) ($this->contact['email'] ?? ''));

        return $email !== '' ? $email : (string) config('mail.contact_to', 'office@astrotherapia.com');
    }

    public static function heroDefaults(): array
    {
        return [
            'headline' => 'Understanding the Why Behind Your Choices',
            'subhead' => 'Your birth chart is the key to help you understand why you think, feel, and choose the way you do — so you can make your next decision with clarity, not guesswork.',
            'cta_label' => 'Begin Here',
            'cta_url' => '/en/services',
            'eyebrow' => 'AstroTherapia',
            'cta2_label' => 'Read the Journal',
            'cta2_url' => '/en/journal',
        ];
    }

    /**
     * Point the site at a theme. Branding is a per-theme override layer, so a
     * real switch clears it — otherwise the previous theme's palette overrides
     * the new theme's tokens. Re-applying the active theme preserves branding.
     */
    public function switchTheme(string $name): void
    {
        $this->update($this->theme === $name
            ? ['theme' => $name]
            : ['theme' => $name, 'branding' => []]);
    }

    public function sectionVisible(string $key): bool
    {
        return (bool) ($this->sections[$key] ?? true);
    }

    /**
     * Hero content ready to render: defaults merged with the stored overrides,
     * then every CTA resolved against the current locale and section visibility.
     *
     * Themes must use this rather than merging `heroDefaults()` themselves —
     * `cta_url` is free-form admin input, so a theme cannot guard it with an
     * `@if` the way the nav and footer guard their own hardcoded links.
     */
    public function heroFor(string $locale): array
    {
        $hero = array_merge(self::heroDefaults(), $this->hero ?? []);

        foreach ([['cta_label', 'cta_url'], ['cta2_label', 'cta2_url']] as [$labelKey, $urlKey]) {
            $url = $hero[$urlKey] ?? null;

            if (! is_string($url) || $url === '') {
                continue;
            }

            $resolved = $this->resolveCtaUrl($url, $locale);

            // A CTA into a disabled section is dropped whole — label included —
            // so the theme renders nothing instead of a button that 404s.
            if ($resolved === null) {
                unset($hero[$labelKey], $hero[$urlKey]);

                continue;
            }

            $hero[$urlKey] = $resolved;
        }

        return $hero;
    }

    /**
     * Section key for each first path segment a CTA can point at. The blog is
     * published as /journal (with /blog and /articles kept as redirects), so
     * the URL segment and the section key are deliberately not the same word.
     */
    private const SECTION_FOR_SEGMENT = [
        'about' => 'about',
        'services' => 'services',
        'contact' => 'contact',
        'journal' => 'blog',
        'blog' => 'blog',
        'articles' => 'blog',
    ];

    /**
     * Null when the URL targets a section that is currently hidden; otherwise
     * the URL with its locale segment swapped for the active locale.
     */
    private function resolveCtaUrl(string $url, string $locale): ?string
    {
        // Anything with a scheme or host is off-site: not ours to rewrite or hide.
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $suffix = substr($url, strlen($path));   // query string and/or fragment
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));

        if ($segments === []) {
            return $url;   // "/" — home is never toggleable
        }

        $supported = $this->locales['supported'] ?? [];

        if (in_array($segments[0], $supported, true)) {
            $segments[0] = $locale;
            $sectionSegment = $segments[1] ?? null;
        } else {
            $sectionSegment = $segments[0];
        }

        $key = self::SECTION_FOR_SEGMENT[$sectionSegment] ?? null;

        if ($key !== null && ! $this->sectionVisible($key)) {
            return null;
        }

        return '/'.implode('/', $segments).$suffix;
    }
}
