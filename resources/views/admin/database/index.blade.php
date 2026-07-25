@extends('layouts.app')

@section('title', 'Database')

@section('content')
    <div class="container">
        <h1>Database</h1>

        @if(session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <h2>Backups</h2>

        <form method="POST" action="{{ route('admin.database.backup') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Back up now</button>
        </form>

        @if($backups->isEmpty())
            <p class="muted">No backups yet.</p>
        @else
            <ul>
                @foreach($backups as $backup)
                    <li>
                        <a href="{{ route('admin.database.download', $backup['name']) }}">{{ $backup['name'] }}</a>
                        <span class="muted">{{ $backup['origin'] }} &middot; {{ number_format($backup['size'] / 1024, 1) }} KB</span>
                        <form method="POST" action="{{ route('admin.database.destroy', $backup['name']) }}"
                              onsubmit="return confirm('Delete this backup?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn" type="submit">Delete</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
