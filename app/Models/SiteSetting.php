<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sections' => 'array',
        'nav'      => 'array',
        'contact'  => 'array',
        'branding' => 'array',
        'locales'  => 'array',
        'hero'     => 'array',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], static::defaults());
    }

    public static function defaults(): array
    {
        return [
            'sections' => ['about' => true, 'blog' => true, 'services' => true, 'contact' => true],
            'nav'      => [],
            'contact'  => ['email' => '', 'phone' => '', 'address' => ''],
            'branding' => [],
            'theme'    => 'solarsystem',
            'locales'  => ['default' => 'en', 'supported' => ['en', 'ro']],
            'hero'     => self::heroDefaults(),
        ];
    }

    public static function heroDefaults(): array
    {
        return [
            'headline'   => 'Understanding the Why Behind Your Choices',
            'subhead'    => 'When the planets align, so do the patterns within you. Read the map you were born under and move with intention.',
            'cta_label'  => 'Begin Here',
            'cta_url'    => '/en/contact',
            'eyebrow'    => 'AstroTherapia',
            'cta2_label' => 'Read the Journal',
            'cta2_url'   => '/en/articles',
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
}
