@extends('layouts.admin')

@section('title', 'Authors')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        <div class="adm-head">
            <h2 class="adm-head__title">Authors</h2>
            <span class="adm-head__count">{{ $authors->count() }}</span>
            <span class="adm-head__grow"></span>
            <a class="adm-btn adm-btn--primary" href="{{ route('admin.authors.create') }}">New author</a>
        </div>

        <div class="adm-panel">
            @if ($authors->isEmpty())
                <div class="adm-panel__body"><p class="adm-note">No authors yet.</p></div>
            @else
                <div class="adm-rows">
                    @foreach ($authors as $author)
                        @include('admin.partials._row', [
                            'image' => $author->picture,
                            'round' => true,
                            'title' => $author->name,
                            'sub' => $author->description,
                            'url' => route('admin.authors.edit', $author),
                            'metas' => [$author->posts_count.' '.Str::plural('post', $author->posts_count)],
                            'actions' => '<a class="adm-btn adm-btn--sm" href="'.route('admin.authors.edit', $author).'">Edit</a>'
                                .'<form method="POST" action="'.route('admin.authors.destroy', $author).'">'
                                .csrf_field().method_field('DELETE')
                                .'<button class="adm-btn adm-btn--sm adm-btn--danger" type="submit" '
                                .'onclick="return confirm(\'Delete this author? Their posts will keep existing but lose this author.\')">Delete</button>'
                                .'</form>',
                        ])
                    @endforeach
                </div>
            @endif
        </div>
    </main>
@endsection
