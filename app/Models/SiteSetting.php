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
            'locales'  => ['default' => 'en', 'supported' => ['en', 'ro']],
        ];
    }

    public function sectionVisible(string $key): bool
    {
        return (bool) ($this->sections[$key] ?? true);
    }
}
