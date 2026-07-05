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
            ->with('translations')
            ->latest('published_at')
            ->get()
            ->filter(fn (Post $p) => $p->translation($locale) !== null)
            ->values();

        return view('blog.index', ['posts' => $posts, 'locale' => $locale]);
    }

    public function show(string $locale, string $slug)
    {
        abort_unless(SiteSetting::current()->sectionVisible('blog'), 404);

        $translation = PostTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('post', fn ($q) => $q->published())
            ->first();

        abort_if($translation === null, 404);

        // Chronological neighbors (oldest -> newest), restricted to posts that
        // have a translation in this locale so a prev/next link never 404s.
        $ordered = Post::published()
            ->with('translations')
            ->orderBy('published_at')
            ->get()
            ->filter(fn (Post $p) => $p->translation($locale) !== null)
            ->values();

        $index = $ordered->search(fn (Post $p) => $p->id === $translation->post_id);
        $previous = $index !== false && $index > 0 ? $ordered[$index - 1]->translation($locale) : null;
        $next = $index !== false && $index < $ordered->count() - 1 ? $ordered[$index + 1]->translation($locale) : null;

        return view('blog.show', [
            't' => $translation,
            'locale' => $locale,
            'previous' => $previous,
            'next' => $next,
        ]);
    }
}
