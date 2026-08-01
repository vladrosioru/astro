@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        <div class="adm-head">
            <h2 class="adm-head__title">Dashboard</h2>
            <span class="adm-head__grow"></span>
        </div>

        <div class="adm-tiles">
            <div class="adm-tile">
                <span class="adm-tile__k">Posts</span>
                <span class="adm-tile__v">{{ $postCount }}</span>
                {{-- Drafts are the only figure here that implies an action, so
                     they're the only one that gets a colour. --}}
                <span class="adm-tile__s{{ $draftCount > 0 ? ' is-warn' : '' }}">{{ $draftCount }} {{ Str::plural('draft', $draftCount) }}</span>
            </div>
            <div class="adm-tile">
                <span class="adm-tile__k">Authors</span>
                <span class="adm-tile__v">{{ $authorCount }}</span>
                <span class="adm-tile__s">{{ $authorsWithoutPosts }} without posts</span>
            </div>
            <div class="adm-tile">
                <span class="adm-tile__k">Backups</span>
                <span class="adm-tile__v">{{ $backupCount }}</span>
                <span class="adm-tile__s">{{ $latestBackupAt ? 'newest '.$latestBackupAt->diffForHumans() : 'none yet' }}</span>
            </div>
            <div class="adm-tile">
                <span class="adm-tile__k">Active theme</span>
                <span class="adm-tile__v adm-tile__v--text">{{ $activeTheme['title'] ?? $activeTheme['name'] ?? '—' }}</span>
                <span class="adm-tile__s">v{{ $activeTheme['version'] ?? '—' }}</span>
            </div>
        </div>

        <div class="adm-stack">
            <div class="adm-panel">
                <div class="adm-panel__head">
                    <h3>Recent posts</h3>
                    <span class="adm-panel__grow"></span>
                    @if (Route::has('admin.posts.index'))
                        <a class="adm-btn adm-btn--sm" href="{{ route('admin.posts.index') }}">All posts</a>
                        <a class="adm-btn adm-btn--sm adm-btn--primary" href="{{ route('admin.posts.create') }}">New post</a>
                    @endif
                </div>
                @if ($recentPosts->isEmpty())
                    <div class="adm-panel__body"><p class="adm-note">No posts yet.</p></div>
                @else
                    <div class="adm-rows">
                        @foreach ($recentPosts as $post)
                            @php($translation = $post->translation('en'))
                            @include('admin.partials._row', [
                                'image' => $post->featured_image,
                                'title' => $translation?->title ?: '(untitled)',
                                'sub' => $translation?->subtitle,
                                'url' => route('admin.posts.edit', $post),
                                'pill' => ['label' => $post->status, 'kind' => $post->status === 'published' ? 'ok' : 'draft'],
                                'metas' => [$post->published_at?->toDateString() ?? '—'],
                                'actions' => null,
                            ])
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="adm-panel">
                <div class="adm-panel__head"><h3>Maintenance</h3></div>
                <div class="adm-panel__body adm-panel__actions">
                    @if (Route::has('admin.database.backup'))
                        <form method="POST" action="{{ route('admin.database.backup') }}">
                            @csrf
                            <button class="adm-btn" type="submit">Back up database</button>
                        </form>
                    @endif
                    @if (Route::has('admin.themes.index'))
                        <a class="adm-btn" href="{{ route('admin.themes.index') }}">Change theme</a>
                    @endif
                    @if (Route::has('admin.authors.create'))
                        <a class="adm-btn" href="{{ route('admin.authors.create') }}">Add author</a>
                    @endif
                    @if (Route::has('admin.payments.edit'))
                        <a class="adm-btn" href="{{ route('admin.payments.edit') }}">Payment settings</a>
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection
