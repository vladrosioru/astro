@extends('layouts.admin')

@section('title', 'Themes')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        <div class="adm-head">
            <h2 class="adm-head__title">Themes</h2>
            <span class="adm-head__count">{{ count($themes) }} installed</span>
            <span class="adm-head__grow"></span>
        </div>

        @if (session('status'))
            <p class="adm-note" style="margin-bottom:14px">{{ session('status') }}</p>
        @endif

        @error('theme')
            <p class="adm-err">{{ $message }}</p>
        @enderror

        {{-- Applying a theme repaints the public site only: this module loads
             no theme CSS, so comparing themes here is honest. --}}
        <p class="adm-note" style="margin-bottom:16px">Changing the theme repaints the public site. Nothing in this admin moves.</p>

        <div class="adm-theme-grid">
            @foreach ($themes as $t)
                <form method="POST" action="{{ route('admin.themes.update') }}" class="adm-panel adm-theme-card">
                    @csrf
                    @method('PATCH')
                    @if ($t['screenshot'])
                        <img class="adm-theme-shot" src="{{ $t['screenshot'] }}" alt="{{ $t['title'] }}">
                    @endif
                    <div class="adm-panel__body">
                        <h3>{{ $t['title'] }} @if($t['active'])<span class="adm-pill adm-pill--ok">active</span>@endif</h3>
                        <p class="adm-note">{{ $t['description'] }}</p>
                        <input type="hidden" name="theme" value="{{ $t['name'] }}">
                        <button class="adm-btn adm-btn--primary" @disabled($t['active'])>{{ $t['active'] ? 'Applied' : 'Apply' }}</button>
                    </div>
                </form>
            @endforeach
        </div>
    </main>
@endsection
