@extends('layouts.app')
@section('title', 'Posts')
@section('content')
    <div class="container">
        <h1>Posts</h1>
        <p><a href="{{ route('admin.posts.create') }}" class="btn btn-primary">New Post</a></p>
        <ul style="list-style:none;padding:0;">
            @foreach ($posts as $post)
                @php $t = $post->translation('en'); @endphp
                <li style="display:flex;align-items:center;gap:1em;line-height:1.4;padding-bottom:0.75em;margin-bottom:0.75em;border-bottom:1px solid var(--color-muted);">
                    @if($post->featured_image)
                        <img src="{{ $post->featured_image }}" alt="" style="width:2.8em;height:2.8em;object-fit:cover;border-radius:var(--radius);flex-shrink:0;">
                    @else
                        <span style="width:2.8em;height:2.8em;flex-shrink:0;"></span>
                    @endif
                    <span style="flex:1;">
                        <a href="{{ route('admin.posts.edit', $post) }}" style="display:block;">{{ $t?->title ?? '(untitled)' }}</a>
                        @if($t?->subtitle)
                            <span style="display:block;font-style:italic;">{{ $t->subtitle }}</span>
                        @endif
                    </span>
                    <span style="display:flex;flex-direction:column;align-items:center;justify-content:center;width:2.8em;flex-shrink:0;gap:0.25em;text-align:center;">
                        <span>{{ $post->status }}</span>
                        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}">
                            @csrf @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
