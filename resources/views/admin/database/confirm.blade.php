@extends('layouts.admin')

@section('title', 'Restore backup')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        <div class="adm-head">
            <h2 class="adm-head__title">Restore backup</h2>
        </div>

        <div class="adm-stack">
            <div class="adm-panel">
                <div class="adm-panel__head">
                    <h3>{{ $file }}</h3>
                    <span class="adm-panel__grow"></span>
                    <span class="adm-note">created {{ $header['created']?->format('j M Y, H:i') ?? 'unknown' }}</span>
                </div>

                {{-- Three aligned columns of numbers: a table is what this is. --}}
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>In backup</th>
                            <th>Live now</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tables as $table)
                            <tr>
                                <td>{{ $table }}</td>
                                <td class="is-data">{{ $header['rows'][$table] ?? '—' }}</td>
                                <td class="is-data">{{ $live[$table] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @unless ($header['rows'])
                    <div class="adm-panel__body">
                        <p class="adm-note">Row counts are unavailable — this backup was written before they were recorded. It can still be restored.</p>
                    </div>
                @endunless
            </div>

            <div class="adm-panel adm-panel--danger">
                <div class="adm-panel__head"><h3>Replace all content on this site</h3></div>
                <div class="adm-panel__body">
                    <p class="adm-note" style="margin-bottom:12px">
                        Replaces {{ implode(', ', $tables) }}. A snapshot of the current content is
                        taken automatically first. Accounts and sessions are not touched.
                    </p>

                    @error('restore')
                        <p class="adm-err">{{ $message }}</p>
                    @enderror

                    <form method="POST" action="{{ route('admin.database.restore', $file) }}">
                        @csrf
                        <div class="adm-field">
                            <label for="confirm-host">Type {{ $host }} to confirm</label>
                            <input id="confirm-host" type="text" autocomplete="off" data-confirm="{{ $host }}">
                        </div>
                        <div class="adm-actions">
                            <a class="adm-btn" href="{{ route('admin.database.index') }}">Cancel</a>
                            <button class="adm-btn adm-btn--danger" type="submit" disabled>Restore</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
<script>
    // Friction for a human, not a control: the server gates on the host in the
    // filename, not on this field.
    (function () {
        var input = document.getElementById('confirm-host');
        var button = input.form.querySelector('button[type=submit]');

        input.addEventListener('input', function () {
            button.disabled = input.value.trim() !== input.dataset.confirm;
        });
    })();
</script>
@endpush
