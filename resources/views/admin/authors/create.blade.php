@extends('layouts.admin')

@section('title', 'New author')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        <div class="adm-head">
            <h2 class="adm-head__title">New author</h2>
            <span class="adm-head__grow"></span>
            <a class="adm-btn adm-btn--sm" href="{{ route('admin.authors.index') }}">&larr; All authors</a>
        </div>

        <div class="adm-panel" style="max-width:640px">
            <div class="adm-panel__body">
                <form method="POST" action="{{ route('admin.authors.store') }}" enctype="multipart/form-data">
                    @csrf
                    @include('admin.authors._form')
                    <button class="adm-btn adm-btn--primary" type="submit">Save</button>
                </form>
            </div>
        </div>
    </main>
@endsection
