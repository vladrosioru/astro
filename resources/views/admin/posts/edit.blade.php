@extends('layouts.app')
@section('title', 'Edit Post')
@section('content')
    <div class="container">
        <h1>Edit Post</h1>
        <form method="POST" action="{{ route('admin.posts.update', $post) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.posts._form')
            <p><button type="submit">Update</button></p>
        </form>
    </div>
@endsection
