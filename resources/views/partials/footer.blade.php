@php
    $setting = \App\Models\SiteSetting::current();
    $locale = app()->getLocale();
@endphp
<footer class="site-footer">
    <div class="container">
        <p class="site-footer__quote">&ldquo;Every man and every woman is a Star&rdquo;</p>
        <p class="site-footer__copyright">AstroTherapia © 2024</p>
        @if ($setting->sectionVisible('contact'))
            <p class="site-footer__contact"><a href="/{{ $locale }}/contact">Contact Us</a></p>
        @endif
        <a class="site-footer__social" href="https://www.facebook.com/astrotherapia.ro"
           aria-label="Facebook" target="_blank" rel="noopener noreferrer" data-fb-page="astrotherapia.ro">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                <path d="M15 3h3a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-2a1 1 0 0 0-1 1v2h3.5a.5.5 0 0 1 .5.6l-.6 3a.5.5 0 0 1-.5.4H15v8h-4v-8H9v-3.5h2V8a5 5 0 0 1 5-5Z"/>
            </svg>
        </a>
    </div>
</footer>
<script src="{{ versioned_asset('js/footer-social.js') }}" defer></script>
