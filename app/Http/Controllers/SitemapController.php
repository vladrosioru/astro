<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $posts = Post::with('translations')
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->get();

        $staticPages = [
            ['path' => '', 'priority' => '1.0', 'changefreq' => 'weekly', 'lastmod' => now()->format('Y-m-d')],
            ['path' => 'about', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => now()->format('Y-m-d')],
            ['path' => 'services', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => now()->format('Y-m-d')],
            ['path' => 'contact', 'priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => now()->format('Y-m-d')],
            ['path' => 'journal', 'priority' => '0.9', 'changefreq' => 'daily', 'lastmod' => now()->format('Y-m-d')],
        ];

        return response()
            ->view('sitemap', compact('staticPages', 'posts'))
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
