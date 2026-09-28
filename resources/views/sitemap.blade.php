{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($staticPages as $page)
    @foreach (['en', 'ro'] as $loc)
    <url>
        <loc>{{ url($loc . ($page['path'] !== '' ? '/' . $page['path'] : '')) }}</loc>
        <lastmod>{{ $page['lastmod'] }}</lastmod>
        <changefreq>{{ $page['changefreq'] }}</changefreq>
        <priority>{{ $page['priority'] }}</priority>
        <xhtml:link rel="alternate" hreflang="en" href="{{ url('en' . ($page['path'] !== '' ? '/' . $page['path'] : '')) }}" />
        <xhtml:link rel="alternate" hreflang="ro" href="{{ url('ro' . ($page['path'] !== '' ? '/' . $page['path'] : '')) }}" />
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ url('en' . ($page['path'] !== '' ? '/' . $page['path'] : '')) }}" />
    </url>
    @endforeach
@endforeach
@foreach ($posts as $post)
    @php
        $enTrans = $post->translations->firstWhere('locale', 'en');
        $roTrans = $post->translations->firstWhere('locale', 'ro');
        $lastmod = ($post->updated_at ?? $post->published_at ?? now())->format('Y-m-d');
    @endphp
    @foreach ($post->translations as $trans)
        @if (!empty($trans->slug) && !empty($trans->title))
    <url>
        <loc>{{ url($trans->locale . '/journal/' . $trans->slug) }}</loc>
        <lastmod>{{ $lastmod }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
        @if ($enTrans && !empty($enTrans->slug))
        <xhtml:link rel="alternate" hreflang="en" href="{{ url('en/journal/' . $enTrans->slug) }}" />
        @endif
        @if ($roTrans && !empty($roTrans->slug))
        <xhtml:link rel="alternate" hreflang="ro" href="{{ url('ro/journal/' . $roTrans->slug) }}" />
        @endif
        @if ($enTrans && !empty($enTrans->slug))
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ url('en/journal/' . $enTrans->slug) }}" />
        @endif
    </url>
        @endif
    @endforeach
@endforeach
</urlset>
