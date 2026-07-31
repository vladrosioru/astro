@extends('layouts.app')
@section('title', 'Authors')
@section('content')
    <div class="container">
        <h1>Authors</h1>
        <p><a href="{{ route('admin.authors.create') }}" class="btn btn-primary">New Author</a></p>
        <ul style="list-style:none;padding:0;">
            @foreach ($authors as $author)
                <li style="display:flex;align-items:center;gap:1em;line-height:1.4;padding-bottom:0.75em;margin-bottom:0.75em;border-bottom:1px solid var(--color-muted);">
                    @if($author->picture)
                        <img src="{{ $author->picture }}" alt="" style="width:2.8em;height:2.8em;object-fit:cover;border-radius:50%;flex-shrink:0;">
                    @else
                        <span style="width:2.8em;height:2.8em;flex-shrink:0;"></span>
                    @endif
                    <span style="flex:1;">
                        <a href="{{ route('admin.authors.edit', $author) }}" style="display:block;">{{ $author->name }}</a>
                    </span>
                    <span style="display:flex;flex-direction:column;align-items:center;justify-content:center;width:4em;flex-shrink:0;gap:0.25em;text-align:center;">
                        <span>{{ $author->posts_count }} {{ $author->posts_count === 1 ? 'post' : 'posts' }}</span>
                        <form method="POST" action="{{ route('admin.authors.destroy', $author) }}">
                            @csrf @method('DELETE')
                            <button type="submit" onclick="return confirm('Delete this author? Their posts will keep existing but lose this author.')">Delete</button>
                        </form>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
