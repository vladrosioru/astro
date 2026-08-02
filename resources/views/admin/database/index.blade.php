@extends('layouts.admin')

@section('title', 'Database')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        <div class="adm-head">
            <h2 class="adm-head__title">Database</h2>
            <span class="adm-head__grow"></span>
            <form method="POST" action="{{ route('admin.database.backup') }}">
                @csrf
                <button class="adm-btn adm-btn--primary" type="submit">Back up now</button>
            </form>
        </div>

        @if (session('status'))
            <p class="adm-note" style="margin-bottom:14px">{{ session('status') }}</p>
        @endif

        <div class="adm-stack">
            <div class="adm-panel">
                <div class="adm-panel__head">
                    <h3>Backups</h3>
                    <span class="adm-panel__grow"></span>
                    <span class="adm-note">{{ $backups->count() }} {{ Str::plural('file', $backups->count()) }} &middot; {{ number_format($backups->sum('size') / 1024, 1) }} KB</span>
                </div>

                @if ($backups->isEmpty())
                    <div class="adm-panel__body"><p class="adm-note">No backups yet.</p></div>
                @else
                    {{-- A real table: these are four aligned columns of file
                         data, which is what a table is for. --}}
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Backup</th>
                                <th>Origin</th>
                                <th>Size</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($backups as $backup)
                                <tr>
                                    <td><a href="{{ route('admin.database.download', $backup['name']) }}">{{ $backup['name'] }}</a></td>
                                    <td class="is-data">{{ $backup['origin'] }}</td>
                                    <td class="is-data">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                                    <td>
                                        <div class="adm-actions">
                                            @if ($backup['restorable'])
                                                <a class="adm-btn adm-btn--sm" href="{{ route('admin.database.restore.confirm', $backup['name']) }}">Restore</a>
                                            @endif
                                            <form method="POST" action="{{ route('admin.database.destroy', $backup['name']) }}"
                                                  onsubmit="return confirm('Delete this backup?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="adm-btn adm-btn--sm adm-btn--danger" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if ($restoreEnabled && $sourceConfigured)
                <div class="adm-panel adm-panel--danger">
                    <div class="adm-panel__head"><h3>Copy live prod into this site</h3></div>
                    <div class="adm-panel__body">
                        <p class="adm-note" style="margin-bottom:12px">
                            Pulls the current production content and replaces posts, translations,
                            media records and site settings on this site. A snapshot of the current
                            content is taken automatically first.
                        </p>

                        @error('pull')
                            <p class="adm-err">{{ $message }}</p>
                        @enderror

                        <form method="POST" action="{{ route('admin.database.pull') }}"
                              onsubmit="return confirm('Replace this site\'s content with live production content?')">
                            @csrf
                            <button class="adm-btn adm-btn--danger" type="submit">Copy prod &rarr; dev now</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </main>
@endsection
