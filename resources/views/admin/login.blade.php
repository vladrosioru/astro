@extends('layouts.app')

@section('title', 'Admin Login')

@section('content')
    <div class="container">
        <h1>Admin Login</h1>
        @if ($errors->any())
            <p class="muted">{{ $errors->first() }}</p>
        @endif
        <form method="POST" action="{{ route('admin.login.attempt') }}">
            @csrf
            <p><label>Email <input type="email" name="email" value="{{ old('email') }}" required></label></p>
            <p><label>Password <input type="password" name="password" required></label></p>
            <p><button type="submit">Log in</button></p>
        </form>
    </div>
@endsection
