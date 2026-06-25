@php($t = isset($post) ? fn ($l) => optional($post->translation($l)) : fn ($l) => null)
<link rel="stylesheet" href="{{ asset('vendor/ckeditor/ckeditor5.css') }}">
<script src="{{ asset('vendor/ckeditor/ckeditor5.umd.js') }}"></script>

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
        <textarea name="{{ $locale }}_body" id="editor_{{ $locale }}">{{ old("{$locale}_body", $t($locale)?->body) }}</textarea>
    </fieldset>
@endforeach

<script>
const {
    ClassicEditor, Essentials, Paragraph, Heading, Bold, Italic, Link, List, BlockQuote,
    Image, ImageToolbar, ImageCaption, ImageStyle, ImageResize, LinkImage, SimpleUploadAdapter
} = CKEDITOR;

['en', 'ro'].forEach(function (loc) {
    ClassicEditor.create(document.querySelector('#editor_' + loc), {
        licenseKey: 'GPL',
        plugins: [Essentials, Paragraph, Heading, Bold, Italic, Link, List, BlockQuote,
                  Image, ImageToolbar, ImageCaption, ImageStyle, ImageResize, LinkImage, SimpleUploadAdapter],
        toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList',
                  'blockQuote', 'insertImage', '|', 'undo', 'redo'],
        image: {
            resizeUnit: '%',
            toolbar: ['toggleImageCaption', 'imageTextAlternative', '|',
                      'imageStyle:inline', 'imageStyle:wrapText', 'imageStyle:breakText', '|',
                      'resizeImage']
        },
        simpleUpload: {
            uploadUrl: '{{ route('admin.attachments.store') }}',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }
    }).catch(function (e) { console.error(e); });
});
</script>
