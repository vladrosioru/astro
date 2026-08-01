@extends('layouts.admin')

@section('title', 'New post')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        <div class="adm-head">
            <h2 class="adm-head__title">New post</h2>
            <span class="adm-head__grow"></span>
            <a class="adm-btn adm-btn--sm" href="{{ route('admin.posts.index') }}">&larr; All posts</a>
        </div>

        <form method="POST" action="{{ route('admin.posts.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.posts._form', ['submitLabel' => 'Save'])
        </form>
    </main>
@endsection
