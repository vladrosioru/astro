@extends('layouts.admin')

@section('title', 'Sign in')

@section('content')
    <div class="adm-login">
        <div class="adm-panel adm-login__card">
            <div class="adm-panel__body">
                <p class="adm-bar__brand adm-bar__brand--plain"><b>{{ config('app.name') }}</b><span>admin</span></p>
                <h2>Sign in</h2>
                <p class="adm-note">Administration for {{ config('app.name') }}</p>
                @if ($errors->any())
                    <p class="adm-err">{{ $errors->first() }}</p>
                @endif
                <form method="POST" action="{{ route('admin.login.attempt') }}">
                    @csrf
                    <div class="adm-field">
                        <label for="email">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="adm-field">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" required>
                    </div>
                    <button class="adm-btn adm-btn--primary adm-btn--wide" type="submit">Log in</button>
                </form>
            </div>
        </div>
    </div>
@endsection
