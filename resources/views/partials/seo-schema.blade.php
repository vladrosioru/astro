@php
    $contactEmail = trim((string) (\App\Models\SiteSetting::current()->contact['email'] ?? ''));

    $orgSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => config('app.name', 'AstroTherapia'),
        'url' => url('/'),
        'logo' => asset('img/logo-nav.png'),
        'email' => $contactEmail !== '' ? $contactEmail : null,
        'sameAs' => [
            'https://www.facebook.com/astrotherapia.ro',
            'https://www.instagram.com/astrotherapia/',
        ],
    ], fn ($v) => $v !== null);

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
