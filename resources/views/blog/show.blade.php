@extends('layouts.app')
@section('title', $t->seo_title ?? $t->title)
@section('content')
    <div class="container">
        <article>
            <h1>{{ $t->title }}</h1>
            {!! $t->body !!}
        </article>
    </div>
@endsection
