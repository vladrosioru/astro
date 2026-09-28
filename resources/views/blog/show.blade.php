@extends('layouts.app')
@section('title', ($t->seo_title ?? $t->title) . ' · ' . config('app.name'))

@php
    $post = $t->post;
    $articleUrl = url()->current();
    $ogDescription = $t->seo_description ?: $t->subtitle;
    $socialImage = $post->social_image ?: $post->featured_image;
    $ogImage = $socialImage ? url($socialImage) : null;
@endphp

@if ($ogDescription)
    @section('meta_description', $ogDescription)
@endif

@section('meta_og_type', 'article')
@section('meta_og_title', $t->seo_title ?? $t->title)
@if ($ogImage)
    @section('meta_og_image', $ogImage)
    @section('meta_twitter_card', 'summary_large_image')
    @section('meta_twitter_image', $ogImage)
@endif
@section('meta_twitter_title', $t->seo_title ?? $t->title)

@section('hreflangs')
    @php
        $enTrans = $post->translation('en');
        $roTrans = $post->translation('ro');
    @endphp
    @if ($enTrans)
        <link rel="alternate" hreflang="en" href="{{ url('en/journal/' . $enTrans->slug) }}">
    @endif
    @if ($roTrans)
        <link rel="alternate" hreflang="ro" href="{{ url('ro/journal/' . $roTrans->slug) }}">
    @endif
    @if ($enTrans)
        <link rel="alternate" hreflang="x-default" href="{{ url('en/journal/' . $enTrans->slug) }}">
    @endif
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/ckeditor/ckeditor5.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/article.css') }}">
    <script src="{{ asset('js/article-share.js') }}" defer></script>
@endpush

@push('schema')
@php
    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $t->seo_title ?? $t->title,
        'description' => $ogDescription ?: '',
        'image' => array_values(array_filter([$ogImage ?: asset('img/logo-nav.png')])),
        'datePublished' => $post->published_at ? $post->published_at->toIso8601String() : null,
        'dateModified' => $post->updated_at ? $post->updated_at->toIso8601String() : null,
        'author' => [
            '@type' => 'Person',
            'name' => $post->author ? $post->author->name : config('app.name'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('app.name'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('img/logo-nav.png'),
            ],
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $articleUrl,
        ],
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endpush

@section('content')
    <header class="journal-hero">
        <h1 class="journal-hero__title">{{ $t->title }}</h1>
    </header>

    <div class="container">
        @if ($post->featured_image)
            <div class="article-image">
                <img src="{{ $post->featured_image }}" alt="{{ $t->title }}" width="1200" height="630" loading="lazy">
            </div>
        @endif

        <article>
            <div class="article-paper">
                <div class="ck-content">
                    {!! $t->body !!}
                </div>
            </div>
        </article>

        @if ($post->author)
            @php($nameParts = explode('|', $post->author->name, 2))
            <div class="article-author">
                <div class="article-author__media">
                    <img src="{{ asset($post->author->picture) }}" alt="{{ $post->author->name }}" width="80" height="80" loading="lazy">
                </div>
                <div class="article-author__body">
                    <p class="article-author__label">Author</p>
                    <h2 class="article-author__name"><span class="article-author__name-first">{{ trim($nameParts[0]) }}</span>@if (isset($nameParts[1]))<span class="article-author__name-rest"> | {{ trim($nameParts[1]) }}</span>@endif</h2>
                    <p class="article-author__bio">{{ $post->author->description }}</p>
                </div>
            </div>
        @endif

        <div class="article-footer">
            <p class="article-date">{{ $post->published_at->format('M j, Y') }}</p>
            <div class="article-share-block">
                <p class="article-share-label">Like it? Tell the world!</p>
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
                        <a href="https://x.com/intent/tweet?text={{ urlencode($t->title) }}&url={{ urlencode($articleUrl) }}"
                           target="_blank" rel="noopener" aria-label="Share on X">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                                <path d="M4 3h4.2l4 5.6L16.8 3H20l-6.4 8.2L20.4 21H16.2l-4.4-6.1L6.8 21H3.6l6.9-8.8L4 3Z"/>
                            </svg>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($articleUrl) }}"
                           target="_blank" rel="noopener" aria-label="Share on LinkedIn">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                                <path d="M4.98 3.5a1.98 1.98 0 1 1 0 3.96 1.98 1.98 0 0 1 0-3.96ZM3.2 9h3.55v11.5H3.2V9Zm6.2 0h3.4v1.57h.05c.47-.9 1.63-1.85 3.36-1.85 3.6 0 4.26 2.37 4.26 5.46v6.32h-3.55v-5.6c0-1.34-.02-3.06-1.87-3.06-1.87 0-2.15 1.46-2.15 2.96v5.7H9.4V9Z"/>
                            </svg>
                        </a>
                    </li>
                    <li>
                        {{-- Copies the article link to the clipboard (see article-share.js);
                             no popup to open, so href just points at the article itself. --}}
                        <a href="{{ $articleUrl }}" target="_blank" rel="noopener"
                           data-share="copy" aria-label="Copy link">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <rect x="9" y="9" width="11" height="11" rx="2"/>
                                <path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </a>
                    </li>
                </ul>
            </div>
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
