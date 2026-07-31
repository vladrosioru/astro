{{-- Shared Journal card: renders one post identically wherever a "journal
     card" is needed — the Journal listing (blog/index.blade.php, one per
     post) and the Home "From the Journal" section (pages/home.blade.php, the
     single featured/newest post). Keeping this in one partial guarantees the
     two stay pixel-identical instead of drifting apart. --}}
@php
    $url = "/{$locale}/journal/{$translation->slug}";
    $frag = $translation->excerptFragments();
@endphp
<article class="card{{ $post->featured_image ? ' card--media' : '' }}{{ ($first ?? false) ? ' card--first' : '' }}">
    @if ($post->featured_image)
        <div class="card__row">
            <a class="card__media-link" href="{{ $url }}">
                <img class="card__media" src="{{ $post->featured_image }}" alt="{{ $translation->title }}">
            </a>
            <div class="card__content">
                <div class="card__meta">
                    <div class="card__date-row">
                        <p class="card__date">{{ $post->published_at->format('M j, Y') }}</p>
                        @if ($post->reading_time)
                            <p class="card__reading-time">{{ $post->reading_time }} min. read</p>
                        @endif
                    </div>
                    <h2 class="card__title"><a href="{{ $url }}">{{ $translation->title }}</a></h2>
                    @if (!empty($translation->subtitle))
                        <p class="card__subtitle">{{ $translation->subtitle }}</p>
                    @endif
                </div>
                <div class="card__body">
                    @if ($frag['lead'] !== '')
                        <p class="card__excerpt">{{ $frag['lead'] }}@if($frag['continued']) {{ $frag['continued'] }}@endif <a class="card__ellipsis" href="{{ $url }}">[...]</a></p>
                    @endif
                    @if ($post->author)
                        <p class="card__author">{{ $post->author->name }}</p>
                    @endif
                </div>
                <div class="card__foot">
                    <hr class="card__rule">
                    <a class="card__more btn btn-primary" href="{{ $url }}">Read more</a>
                </div>
            </div>
        </div>
    @else
        <div class="card__body">
            <div class="card__date-row">
                <p class="card__date">{{ $post->published_at->format('M j, Y') }}</p>
                @if ($post->reading_time)
                    <p class="card__reading-time">{{ $post->reading_time }} min. read</p>
                @endif
            </div>
            <h2 class="card__title"><a href="{{ $url }}">{{ $translation->title }}</a></h2>
            @if (!empty($translation->subtitle))
                <p class="card__subtitle">{{ $translation->subtitle }}</p>
            @endif
            @if ($frag['lead'] !== '')
                <p class="card__excerpt">{{ $frag['lead'] }}@if($frag['continued']) {{ $frag['continued'] }}@endif <a class="card__ellipsis" href="{{ $url }}">[...]</a></p>
            @endif
            <a class="card__more btn btn-primary" href="{{ $url }}">Read more</a>
        </div>
    @endif
</article>
