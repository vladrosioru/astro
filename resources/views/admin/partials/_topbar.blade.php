{{-- Admin top bar — the module's only chrome. No theme is involved: the site
     nav lives on layouts/app.blade.php and public pages only. Section links
     stay Route::has()-guarded so a section whose routes don't exist yet
     (Payments, Plan 3) simply doesn't appear. --}}
@php
    $sections = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'pattern' => 'admin.dashboard'],
        ['route' => 'admin.posts.index', 'label' => 'Posts', 'pattern' => 'admin.posts.*'],
        ['route' => 'admin.authors.index', 'label' => 'Authors', 'pattern' => 'admin.authors.*'],
        ['route' => 'admin.payments.edit', 'label' => 'Payments', 'pattern' => 'admin.payments.*'],
        ['route' => 'admin.themes.index', 'label' => 'Themes', 'pattern' => 'admin.themes.*'],
        ['route' => 'admin.database.index', 'label' => 'Database', 'pattern' => 'admin.database.*'],
    ];
@endphp
<header class="adm-bar">
    <a class="adm-bar__brand" href="{{ route('admin.dashboard') }}"><b>{{ config('app.name') }}</b><span>admin</span></a>
    @foreach ($sections as $section)
        @if (Route::has($section['route']))
            <a class="adm-bar__link{{ request()->routeIs($section['pattern']) ? ' is-on' : '' }}" href="{{ route($section['route']) }}">{{ $section['label'] }}</a>
        @endif
    @endforeach
    <span class="adm-bar__spacer"></span>
    <a class="adm-bar__site" href="/{{ app()->getLocale() }}">View site &#8599;</a>
    <span class="adm-bar__who">{{ auth()->user()?->email }}</span>
    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="adm-linkbtn" type="submit">Log out</button></form>
</header>
