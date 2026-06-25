@php($t = isset($post) ? fn ($l) => optional($post->translation($l)) : fn ($l) => null)
<link rel="stylesheet" href="{{ asset('vendor/trix/trix.css') }}">
<script src="{{ asset('vendor/trix/trix.umd.min.js') }}"></script>

<p><label>Status
    <select name="status">
        <option value="draft" @selected(isset($post) && $post->status === 'draft')>Draft</option>
        <option value="published" @selected(isset($post) && $post->status === 'published')>Published</option>
    </select>
</label></p>

@foreach (['en', 'ro'] as $locale)
    <fieldset>
        <legend>{{ strtoupper($locale) }}</legend>
        <p><label>Title <input name="{{ $locale }}_title" value="{{ old("{$locale}_title", $t($locale)?->title) }}"></label></p>
        <p><label>Slug <input name="{{ $locale }}_slug" value="{{ old("{$locale}_slug", $t($locale)?->slug) }}"></label></p>
        <p><label>Excerpt <input name="{{ $locale }}_excerpt" value="{{ old("{$locale}_excerpt", $t($locale)?->excerpt) }}"></label></p>
        <input id="{{ $locale }}_body_input" type="hidden" name="{{ $locale }}_body" value="{{ old("{$locale}_body", $t($locale)?->body) }}">
        <trix-editor input="{{ $locale }}_body_input"></trix-editor>
    </fieldset>
@endforeach

<script>
document.addEventListener('trix-attachment-add', function (event) {
    const attachment = event.attachment;
    if (!attachment.file) return;
    const body = new FormData();
    body.append('file', attachment.file);
    body.append('_token', '{{ csrf_token() }}');
    fetch('{{ route('admin.attachments.store') }}', { method: 'POST', body })
        .then(r => r.json())
        .then(data => attachment.setAttributes({ url: data.url, href: data.url }));
});
</script>
