<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PostTranslation extends Model
{
    protected $guarded = [];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    // Slugs are unique per locale (matching the `unique(['locale', 'slug'])`
    // DB index and the /{locale}/journal/{slug} routing), not globally.
    public static function uniqueSlug(string $title, string $locale, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;

        while (
            static::where('locale', $locale)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.random_int(100, 999);
        }

        return $slug;
    }

    /**
     * Card-teaser text: the article's first sentence, plus the first 5 words
     * of the next sentence (both derived from the plain-text body). This is
     * distinct from `subtitle` (an admin-entered short subtitle shown right
     * under the title) — the teaser is a body excerpt shown below the card
     * image, ending in a linked "[...]".
     */
    public function excerptFragments(): array
    {
        $text = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $this->body), ENT_QUOTES)));

        if ($text === '') {
            return ['lead' => '', 'continued' => null];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $lead = $sentences[0] ?? $text;
        $continued = null;

        if (isset($sentences[1])) {
            $words = preg_split('/\s+/', trim($sentences[1]), -1, PREG_SPLIT_NO_EMPTY);
            $continued = implode(' ', array_slice($words, 0, 5));
        }

        return ['lead' => $lead, 'continued' => $continued];
    }
}
