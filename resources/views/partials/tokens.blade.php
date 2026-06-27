@php
    $tokens = app('theme.manager')->tokens();
@endphp
<style>
:root {
@foreach ($tokens as $name => $value)
    --{{ $name }}: {{ $value }};
@endforeach
}
</style>
