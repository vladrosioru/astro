@extends('layouts.app')

@section('title', config('app.name'))
@section('body_class', 'page-home')

@section('content')
    @include('partials.hero')
    <div class="container">
        <h2>{{ config('app.name') }}</h2>
    </div>
@endsection
