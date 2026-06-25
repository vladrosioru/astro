@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    <div class="container">
        <h1>{{ config('app.name') }}</h1>
    </div>
@endsection
