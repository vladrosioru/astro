@extends('layouts.admin')

@section('title', 'Edit author')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        <div class="adm-head">
            <h2 class="adm-head__title">{{ $author->name }}</h2>
            <span class="adm-head__grow"></span>
            <a class="adm-btn adm-btn--sm" href="{{ route('admin.authors.index') }}">&larr; All authors</a>
        </div>

        <div class="adm-panel" style="max-width:640px">
            <div class="adm-panel__body">
                <form method="POST" action="{{ route('admin.authors.update', $author) }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    @include('admin.authors._form')
                    <button class="adm-btn adm-btn--primary" type="submit">Update</button>
                </form>
            </div>
        </div>
    </main>
@endsection
