<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $settings = SiteSetting::current();
        $blogVisible = $settings->sectionVisible('blog');

        $posts = $blogVisible
            ? Post::published()->with('translations')->orderByDesc('published_at')->get()
            : collect();

        $latestPostDate = $posts->max(fn (Post $p) => $p->updated_at ?? $p->published_at)?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        $candidates = [
            ['section' => null, 'path' => '', 'priority' => '1.0', 'changefreq' => 'weekly', 'lastmod' => $latestPostDate],
            ['section' => 'about', 'path' => 'about', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => now()->format('Y-m-d')],
            ['section' => 'services', 'path' => 'services', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => now()->format('Y-m-d')],
            ['section' => 'contact', 'path' => 'contact', 'priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => now()->format('Y-m-d')],
            ['section' => 'blog', 'path' => 'journal', 'priority' => '0.9', 'changefreq' => 'daily', 'lastmod' => $latestPostDate],
        ];

        $staticPages = array_values(array_filter(
            $candidates,
            fn (array $page) => $page['section'] === null || $settings->sectionVisible($page['section'])
        ));

        return response()
            ->view('sitemap', compact('staticPages', 'posts'))
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
