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
        <div class="card__meta">
            <p class="card__date">{{ $post->published_at->format('M j, Y') }}</p>
            <h2 class="card__title"><a href="{{ $url }}">{{ $translation->title }}</a></h2>
            @if (!empty($translation->subtitle))
                <p class="card__subtitle">{{ $translation->subtitle }}</p>
            @endif
        </div>
        <a class="card__media-link" href="{{ $url }}">
            <img class="card__media" src="{{ $post->featured_image }}" alt="{{ $translation->title }}">
        </a>
    @endif
    <div class="card__body">
        @unless ($post->featured_image)
            <p class="card__date">{{ $post->published_at->format('M j, Y') }}</p>
            <h2 class="card__title"><a href="{{ $url }}">{{ $translation->title }}</a></h2>
            @if (!empty($translation->subtitle))
                <p class="card__subtitle">{{ $translation->subtitle }}</p>
            @endif
        @endunless
        @if ($frag['lead'] !== '')
            <p class="card__excerpt">{{ $frag['lead'] }}@if($frag['continued']) {{ $frag['continued'] }}@endif <a class="card__ellipsis" href="{{ $url }}">[...]</a></p>
        @endif
        <a class="card__more btn btn-primary" href="{{ $url }}">Read more</a>
    </div>
</article>
