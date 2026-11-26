<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\SiteSetting;

class BlogController extends Controller
{
    public function index()
    {
        abort_unless(SiteSetting::current()->sectionVisible('blog'), 404);

        $locale = app()->getLocale();
        $posts = Post::published()
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale), 'author'])
            ->latest('published_at')
            ->paginate(12);

        return view('blog.index', ['posts' => $posts, 'locale' => $locale]);
    }

    public function show(string $locale, string $slug)
    {
        abort_unless(SiteSetting::current()->sectionVisible('blog'), 404);

        $translation = PostTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('post', fn ($q) => $q->published())
            ->with('post.author', 'post.translations')
            ->first();

        abort_if($translation === null, 404);

        // Chronological neighbors (oldest -> newest), restricted to posts that
        // have a translation in this locale so a prev/next link never 404s.
        $publishedAt = $translation->post->published_at;
        $postId = $translation->post_id;
        $hasLocale = fn ($q) => $q->where('locale', $locale);

        $previousPost = Post::published()
            ->whereHas('translations', $hasLocale)
            ->where(function ($q) use ($publishedAt, $postId) {
                $q->where('published_at', '<', $publishedAt)
                    ->orWhere(fn ($q2) => $q2->where('published_at', '=', $publishedAt)->where('id', '<', $postId));
            })
            ->with(['translations' => $hasLocale])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        $nextPost = Post::published()
            ->whereHas('translations', $hasLocale)
            ->where(function ($q) use ($publishedAt, $postId) {
                $q->where('published_at', '>', $publishedAt)
                    ->orWhere(fn ($q2) => $q2->where('published_at', '=', $publishedAt)->where('id', '>', $postId));
            })
            ->with(['translations' => $hasLocale])
            ->orderBy('published_at')
            ->orderBy('id')
            ->first();

        return view('blog.show', [
            't' => $translation,
            'locale' => $locale,
            'previous' => $previousPost?->translation($locale),
            'next' => $nextPost?->translation($locale),
        ]);
    }
}
