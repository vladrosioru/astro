@extends('layouts.app')

@section('title', 'Themes')

@section('content')
    <div class="container">
        <h1>Themes</h1>

        @if(session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        @error('theme')
            <p class="muted">{{ $message }}</p>
        @enderror

        <div class="blog-grid">
            @foreach($themes as $t)
                <form method="POST" action="{{ route('admin.themes.update') }}" class="card">
                    @csrf
                    @method('PATCH')
                    @if($t['screenshot'])
                        <img class="card__media" src="{{ $t['screenshot'] }}" alt="{{ $t['title'] }}">
                    @endif
                    <div class="card__body">
                        <h3>{{ $t['title'] }} @if($t['active'])<span class="muted">(active)</span>@endif</h3>
                        <p class="muted">{{ $t['description'] }}</p>
                        <input type="hidden" name="theme" value="{{ $t['name'] }}">
                        <button class="btn btn-primary" @disabled($t['active'])>Apply</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>
@endsection
