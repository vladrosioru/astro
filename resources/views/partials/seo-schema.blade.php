@php
    $orgSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => config('app.name', 'AstroTherapia'),
        'url' => url('/'),
        'logo' => asset('img/logo-nav.png'),
        'sameAs' => [
            'https://www.facebook.com/astrotherapia.ro',
        ],
    ];

    $webSiteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => config('app.name', 'AstroTherapia'),
        'url' => url('/'),
        'inLanguage' => app()->getLocale(),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($orgSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
<script type="application/ld+json">
{!! json_encode($webSiteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
