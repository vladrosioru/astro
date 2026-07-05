@extends('layouts.app')
@section('title', 'Journal')
@section('content')
    <header class="journal-hero">
        <h1 class="journal-hero__title">Cosmic Journal</h1>
    </header>

    <div class="container">
        <div class="blog-grid blog-grid--journal">
            @foreach ($posts as $i => $post)
                @php($t = $post->translation($locale))
                @include('partials.journal-card', ['post' => $post, 'translation' => $t, 'locale' => $locale, 'first' => $i === 0])
            @endforeach
        </div>
    </div>
@endsection
