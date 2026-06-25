@extends('layouts.app')

@section('title', 'Contact — ' . config('app.name'))

@section('content')
    <div class="container">
        <h1>Contact</h1>
        <p class="muted">{{ $contact['email'] ?? '' }}</p>
    </div>
@endsection
