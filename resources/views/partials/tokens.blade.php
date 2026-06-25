@php
    $tokens = array_merge(
        config('tokens.defaults'),
        \App\Models\SiteSetting::current()->branding ?? []
    );
@endphp
<style>
:root {
@foreach ($tokens as $name => $value)
    --{{ $name }}: {{ $value }};
@endforeach
}
</style>
