@extends('layouts.app')
@section('title', app()->getLocale() === 'ro'
    ? 'Jurnal Cosmic — Articole și Perspective Astrologice · ' . config('app.name')
    : 'Cosmic Journal — Astrology Insights & Reflections · ' . config('app.name'))
@section('meta_description', app()->getLocale() === 'ro'
    ? 'Reflecții despre astrologie, autocunoaștere și tiparele care îți ghidează viața. Citește cele mai recente articole din Jurnalul Cosmic AstroTherapia.'
    : 'Reflections on astrology, self-knowledge, and the patterns that shape your life. Read the latest entries from the AstroTherapia Cosmic Journal.')
@section('content')
    <header class="journal-hero">
        <h1 class="journal-hero__title">Cosmic Journal</h1>
        <p class="journal-hero__sub">Reflections on the sky, the self, and the seen and unseen patterns</p>
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
