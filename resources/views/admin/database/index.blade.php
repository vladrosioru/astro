@extends('layouts.app')

@section('title', 'Database')

@section('content')
    <div class="container">
        <h1>Database</h1>

        @if(session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <h2>Backups</h2>

        @if($backups->isEmpty())
            <p class="muted">No backups yet.</p>
        @endif
    </div>
@endsection
