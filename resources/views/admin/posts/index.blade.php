@extends('layouts.admin')

@section('title', 'Posts')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        @php($draftCount = $posts->where('status', 'draft')->count())
        <div class="adm-head">
            <h2 class="adm-head__title">Posts</h2>
            <span class="adm-head__count">{{ $posts->count() }} total &middot; {{ $draftCount }} {{ Str::plural('draft', $draftCount) }}</span>
            <span class="adm-head__grow"></span>
            <a class="adm-btn adm-btn--primary" href="{{ route('admin.posts.create') }}">New post</a>
        </div>

        <div class="adm-panel">
            <div class="adm-panel__head">
                <div class="adm-filters">
                    <button class="adm-chip is-on" type="button" data-filter="all">All</button>
                    <button class="adm-chip" type="button" data-filter="draft">Drafts</button>
                    <button class="adm-chip" type="button" data-filter="published">Published</button>
                </div>
                <span class="adm-panel__grow"></span>
                <div class="adm-search"><input type="text" id="post-search" placeholder="Filter by title&hellip;" aria-label="Filter posts by title"></div>
            </div>

            @if ($posts->isEmpty())
                <div class="adm-panel__body"><p class="adm-note">No posts yet.</p></div>
            @else
                <div class="adm-rows" id="post-rows">
                    @foreach ($posts as $post)
                        @php($translation = $post->translation('en'))
                        @php($title = $translation?->title ?: '(untitled)')
                        @include('admin.partials._row', [
                            'image' => $post->featured_image,
                            'title' => $title,
                            'sub' => $translation?->subtitle,
                            'url' => route('admin.posts.edit', $post),
                            'pill' => ['label' => $post->status, 'kind' => $post->status === 'published' ? 'ok' : 'draft'],
                            'metas' => [
                                $post->author?->name ?? '—',
                                $post->published_at?->toDateString() ?? '—',
                            ],
                            'attrs' => 'data-status="'.e($post->status).'" data-title="'.e(Str::lower($title)).'"',
                            'actions' => '<a class="adm-btn adm-btn--sm" href="'.route('admin.posts.edit', $post).'">Edit</a>'
                                .'<form method="POST" action="'.route('admin.posts.destroy', $post).'">'
                                .csrf_field().method_field('DELETE')
                                .'<button class="adm-btn adm-btn--sm adm-btn--danger" type="submit">Delete</button></form>',
                        ])
                    @endforeach
                </div>
            @endif
        </div>
    </main>
@endsection

@push('scripts')
<script>
// Client-side only: the status chips and the title box hide rows in place.
// No route, no query string, no controller change — the list is already fully
// rendered, and it is small enough that paging it server-side would cost more
// than it saves.
(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('#post-rows .adm-row'));
    var chips = Array.prototype.slice.call(document.querySelectorAll('.adm-chip[data-filter]'));
    var search = document.getElementById('post-search');
    if (! rows.length) return;

    var status = 'all';

    function apply() {
        var needle = (search.value || '').trim().toLowerCase();
        rows.forEach(function (row) {
            var matchesStatus = status === 'all' || row.dataset.status === status;
            var matchesTitle = needle === '' || (row.dataset.title || '').indexOf(needle) !== -1;
            row.hidden = !(matchesStatus && matchesTitle);
        });
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (other) { other.classList.remove('is-on'); });
            chip.classList.add('is-on');
            status = chip.dataset.filter;
            apply();
        });
    });

    search.addEventListener('input', apply);
})();
</script>
@endpush
