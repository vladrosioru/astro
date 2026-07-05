@extends('layouts.app')
@section('title', $t->seo_title ?? $t->title)

@php
    $post = $t->post;
    $articleUrl = url()->current();
    $ogDescription = $t->seo_description ?: $t->subtitle;
    $ogImage = $post->featured_image ? url($post->featured_image) : null;
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/ckeditor/ckeditor5.css') }}">
    <link rel="stylesheet" href="{{ asset('css/article.css') }}">
    <script src="{{ asset('js/article-share.js') }}" defer></script>

    @if ($ogDescription)
        <meta name="description" content="{{ $ogDescription }}">
    @endif

    {{-- Open Graph / Twitter Card: Facebook's and X's share dialogs build
         their link-preview card by scraping these tags from the target URL
         — without them the composer opens with no link attached at all. --}}
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $t->seo_title ?? $t->title }}">
    <meta property="og:url" content="{{ $articleUrl }}">
    @if ($ogDescription)
        <meta property="og:description" content="{{ $ogDescription }}">
    @endif
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $t->seo_title ?? $t->title }}">
    @if ($ogDescription)
        <meta name="twitter:description" content="{{ $ogDescription }}">
    @endif
    @if ($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
@endpush

@section('content')
    <header class="journal-hero">
        <h1 class="journal-hero__title">{{ $t->title }}</h1>
    </header>

    <div class="container">
        @if ($post->featured_image)
            <div class="article-image">
                <img src="{{ $post->featured_image }}" alt="{{ $t->title }}">
            </div>
        @endif

        <article>
            <div class="article-paper">
                <div class="ck-content">
                    {!! $t->body !!}
                </div>
            </div>
        </article>

        <div class="article-footer">
            <p class="article-date">{{ $post->published_at->format('M j, Y') }}</p>
            <ul class="article-share">
                <li>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($articleUrl) }}"
                       target="_blank" rel="noopener" aria-label="Share on Facebook">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                            <path d="M15 3h3a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-2a1 1 0 0 0-1 1v2h3.5a.5.5 0 0 1 .5.6l-.6 3a.5.5 0 0 1-.5.4H15v8h-4v-8H9v-3.5h2V8a5 5 0 0 1 5-5Z"/>
                        </svg>
                    </a>
                </li>
                <li>
                    {{-- Instagram has no public "share this link" web intent — the reference
                         theme doesn't have an Instagram share button either (only FB/X/Tumblr). --}}
                    <a href="#" aria-label="Share on Instagram">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <rect x="3.5" y="3.5" width="17" height="17" rx="5"/>
                            <circle cx="12" cy="12" r="4.2"/>
                            <circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="https://x.com/intent/tweet?text={{ urlencode($t->title) }}&url={{ urlencode($articleUrl) }}"
                       target="_blank" rel="noopener" aria-label="Share on X">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                            <path d="M4 3h4.2l4 5.6L16.8 3H20l-6.4 8.2L20.4 21H16.2l-4.4-6.1L6.8 21H3.6l6.9-8.8L4 3Z"/>
                        </svg>
                    </a>
                </li>
            </ul>
        </div>

        @if ($previous || $next)
            <nav class="article-adjacent" aria-label="More articles">
                @if ($previous)
                    <a class="article-adjacent__link article-adjacent__link--prev" href="/{{ $locale }}/journal/{{ $previous->slug }}">
                        <span class="article-adjacent__eyebrow">&larr; Previous</span>
                        <span class="article-adjacent__title">{{ $previous->title }}</span>
                    </a>
                @endif
                @if ($next)
                    <a class="article-adjacent__link article-adjacent__link--next" href="/{{ $locale }}/journal/{{ $next->slug }}">
                        <span class="article-adjacent__eyebrow">Next &rarr;</span>
                        <span class="article-adjacent__title">{{ $next->title }}</span>
                    </a>
                @endif
            </nav>
        @endif
    </div>
@endsection
