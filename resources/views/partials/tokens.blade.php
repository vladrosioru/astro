@php
    $tokens = app('theme.manager')->tokens();
@endphp
<style>
:root {
@foreach ($tokens as $name => $value)
    {{-- Emit raw: these are trusted, schema-validated token values (config/tokens.php
         defaults ← theme.json ← SiteSetting.branding), and this is a CSS context.
         Blade's default {{ }} HTML-escaping would turn quoted font stacks like
         'Cinzel', serif into &#039;Cinzel&#039;, serif — invalid CSS inside <style>,
         which silently drops the quoted family and falls back to the browser serif. --}}
    --{{ $name }}: {!! $value !!};
@endforeach
}
</style>
