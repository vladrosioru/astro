@extends('layouts.app')
@section('title', 'Edit Author')
@section('content')
    <div class="container">
        <h1>Edit Author</h1>
        <form method="POST" action="{{ route('admin.authors.update', $author) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.authors._form')
            <p><button type="submit">Update</button></p>
        </form>
    </div>
@endsection
