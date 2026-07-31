@extends('layouts.app')
@section('title', 'New Author')
@section('content')
    <div class="container">
        <h1>New Author</h1>
        <form method="POST" action="{{ route('admin.authors.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.authors._form')
            <p><button type="submit">Save</button></p>
        </form>
    </div>
@endsection
