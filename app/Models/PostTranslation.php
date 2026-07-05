<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTranslation extends Model
{
    protected $guarded = [];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
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
