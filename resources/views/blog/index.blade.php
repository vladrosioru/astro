@extends('layouts.app')
@section('title', 'Blog')
@section('content')
    <div class="container">
        <h1>Blog</h1>
        @foreach ($posts as $post)
            @php($t = $post->translation($locale))
            <article class="card">
                <h2><a href="/{{ $locale }}/blog/{{ $t->slug }}">{{ $t->title }}</a></h2>
                <p class="muted">{{ $t->excerpt }}</p>
            </article>
        @endforeach
    </div>
@endsection
