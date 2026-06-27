@extends('layouts.app')
@section('title', $t->seo_title ?? $t->title)

@push('head')
    {{-- Load the editor's own stylesheet so the published body renders exactly
         as authored; .ck-content wraps the body below. --}}
    <link rel="stylesheet" href="{{ asset('vendor/ckeditor/ckeditor5.css') }}">
    <link rel="stylesheet" href="{{ asset('css/article.css') }}">
@endpush

@section('content')
    <div class="container">
        <article>
            <h1>{{ $t->title }}</h1>
            <div class="article-paper">
                <div class="ck-content">
                    {!! $t->body !!}
                </div>
            </div>
        </article>
    </div>
@endsection
