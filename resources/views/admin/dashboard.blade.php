@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="container">
        <h1>Dashboard</h1>
        <ul>
            @if (Route::has('admin.posts.index'))
                <li><a href="{{ route('admin.posts.index') }}">Blog posts</a></li>
            @endif
            @if (Route::has('admin.payments.edit'))
                <li><a href="{{ route('admin.payments.edit') }}">Payment settings</a></li>
            @endif
        </ul>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit">Log out</button></form>
    </div>
@endsection
