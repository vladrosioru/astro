{{-- One list row, shared by the dashboard, Posts and Authors.

     Slots: $title, $url (required), $image (?string), $round (bool),
     $sub (?string), $pill (?['label' => string, 'kind' => ok|draft|neutral]),
     $metas (string[]), $actions (?string of trusted, view-authored markup),
     $attrs (?string of extra attributes on the row, e.g. data-status). --}}
@php
    $round = $round ?? false;
    $sub = $sub ?? null;
    $pill = $pill ?? null;
    $metas = $metas ?? [];
    $actions = $actions ?? null;
    $attrs = $attrs ?? '';
@endphp
<div class="adm-row" {!! $attrs !!}>
    @if (! empty($image))
        <img class="adm-row__thumb{{ $round ? ' is-round' : '' }}" src="{{ $image }}" alt="">
    @else
        <span class="adm-row__thumb{{ $round ? ' is-round' : '' }}"></span>
    @endif
    <span class="adm-row__main">
        <a href="{{ $url }}">{{ $title }}</a>
        @if ($sub)
            <span class="adm-row__sub">{{ $sub }}</span>
        @endif
    </span>
    @if ($pill)
        <span class="adm-pill adm-pill--{{ $pill['kind'] }}">{{ $pill['label'] }}</span>
    @endif
    @foreach ($metas as $meta)
        <span class="adm-row__meta">{{ $meta }}</span>
    @endforeach
    @if ($actions)
        {{-- Trusted, view-authored markup (edit links and delete forms) — never user input. --}}
        <span class="adm-row__acts">{!! $actions !!}</span>
    @endif
</div>
