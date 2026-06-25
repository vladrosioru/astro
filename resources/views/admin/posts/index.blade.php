@extends('layouts.app')
@section('title', 'Posts')
@section('content')
    <div class="container">
        <h1>Posts</h1>
        <p><a href="{{ route('admin.posts.create') }}">New post</a></p>
        <ul>
            @foreach ($posts as $post)
                <li>
                    <a href="{{ route('admin.posts.edit', $post) }}">{{ $post->translation('en')?->title ?? '(untitled)' }}</a>
                    — {{ $post->status }}
                    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" style="display:inline">
                        @csrf @method('DELETE')
                        <button type="submit">Delete</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
