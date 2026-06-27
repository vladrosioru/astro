@extends('layouts.app')

@section('title', config('app.name'))
@section('body_class', 'page-home')

@section('content')
    @includeIf('theme::hero')
@endsection
