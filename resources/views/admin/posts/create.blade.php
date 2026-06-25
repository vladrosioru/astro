@extends('layouts.app')
@section('title', 'New Post')
@section('content')
    <div class="container">
        <h1>New Post</h1>
        <form method="POST" action="{{ route('admin.posts.store') }}">
            @csrf
            @include('admin.posts._form')
            <p><button type="submit">Save</button></p>
        </form>
    </div>
@endsection
