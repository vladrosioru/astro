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
            'sections' => ['about' => true, 'blog' => true, 'contact' => true],
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
            'eyebrow'    => 'Celestial Guidance',
            'cta2_label' => 'Read the Journal',
            'cta2_url'   => '/en/blog',
        ];
    }

    public function sectionVisible(string $key): bool
    {
        return (bool) ($this->sections[$key] ?? true);
    }
}
