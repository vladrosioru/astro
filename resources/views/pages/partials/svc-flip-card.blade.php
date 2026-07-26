{{-- Single Archetypes pentagon flip-card. Expects $a (glyph/title/desc/method/names/center). --}}
<div class="flip-card @if(!empty($a['center'])) flip-card--center @endif" data-flip tabindex="0" role="button" aria-pressed="false">
    <div class="flip-card__inner">
        <div class="flip-card__face">
            <span class="svc-card__icon" aria-hidden="true">{{ $a['glyph'] }}&#xFE0E;</span>
            <h3 class="svc-card__title">{{ $a['title'] }}</h3>
            <p class="svc-card__desc">{{ $a['desc'] }}</p>
        </div>
        <div class="flip-card__face flip-card__face--back">
            <p class="flip-card__kicker">Read through</p>
            <p class="flip-card__method">{{ $a['method'] }}</p>
            <p class="flip-card__kicker">Archetypes</p>
            <p class="flip-card__names">{{ $a['names'] }}</p>
        </div>
    </div>
</div>
