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

        return view('blog.show', ['t' => $translation]);
    }
}
