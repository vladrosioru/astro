<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sections' => 'array',
        'nav' => 'array',
        'contact' => 'array',
        'branding' => 'array',
        'locales' => 'array',
        'hero' => 'array',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], static::defaults());
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
        ];
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
