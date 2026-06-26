@extends('layouts.app')
@section('title', 'Blog')
@section('content')
    <div class="container">
        <h1>Blog</h1>
        <div class="blog-grid">
            @foreach ($posts as $post)
                @php($t = $post->translation($locale))
                <a class="card{{ $post->featured_image ? ' card--media' : '' }}" href="/{{ $locale }}/blog/{{ $t->slug }}">
                    @if ($post->featured_image)
                        <img class="card__media" src="{{ $post->featured_image }}" alt="{{ $t->title }}">
                    @endif
                    <div class="card__body">
                        <h2>{{ $t->title }}</h2>
                        <p class="muted">{{ $t->excerpt }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endsection
