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
            <table>
                <thead>
                    <tr>
                        <th>Backup</th>
                        <th>Origin</th>
                        <th>Size</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($backups as $backup)
                        <tr>
                            <td>
                                <a href="{{ route('admin.database.download', $backup['name']) }}">{{ $backup['name'] }}</a>
                            </td>
                            <td class="muted">{{ $backup['origin'] }}</td>
                            <td class="muted">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                            <td>
                                <form method="POST" action="{{ route('admin.database.destroy', $backup['name']) }}"
                                      onsubmit="return confirm('Delete this backup?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($restoreEnabled)
            <h2>Copy prod content into dev</h2>

            <p class="muted">
                Upload a backup taken on production. This replaces posts, translations,
                media records and site settings on this site. A snapshot of the current
                content is taken automatically first.
            </p>

            @error('backup')
                <p class="muted">{{ $message }}</p>
            @enderror

            <form method="POST" action="{{ route('admin.database.restore') }}" enctype="multipart/form-data"
                  onsubmit="return confirm('Replace this site\'s content with the uploaded backup?')">
                @csrf
                <input type="file" name="backup" accept=".gz" required>
                <button class="btn btn-primary" type="submit">Restore</button>
            </form>
        @endif
    </div>
@endsection
