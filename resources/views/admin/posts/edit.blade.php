@extends('layouts.admin')

@section('title', 'Edit post')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        @php($heading = $post->translation('en')?->title ?: '(untitled)')
        <div class="adm-head">
            <h2 class="adm-head__title">{{ $heading }}</h2>
            <span class="adm-head__count">post #{{ $post->id }}</span>
            <span class="adm-head__grow"></span>
            <a class="adm-btn adm-btn--sm" href="{{ route('admin.posts.index') }}">&larr; All posts</a>
        </div>

        <form method="POST" action="{{ route('admin.posts.update', $post) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.posts._form', ['submitLabel' => 'Update'])
        </form>

        <div class="adm-panel adm-panel--danger" style="margin-top:18px;max-width:420px">
            <div class="adm-panel__head"><h3>Delete post</h3></div>
            <div class="adm-panel__body">
                <p class="adm-note" style="margin-bottom:12px">This removes the post and both its translations. It cannot be undone.</p>
                <form method="POST" action="{{ route('admin.posts.destroy', $post) }}"
                      onsubmit="return confirm('Delete this post permanently?')">
                    @csrf @method('DELETE')
                    <button class="adm-btn adm-btn--danger" type="submit">Delete permanently</button>
                </form>
            </div>
        </div>
    </main>
@endsection
