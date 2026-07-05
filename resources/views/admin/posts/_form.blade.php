@php($t = isset($post) ? fn ($l) => optional($post->translation($l)) : fn ($l) => null)
<link rel="stylesheet" href="{{ asset('vendor/ckeditor/ckeditor5.css') }}">
<script src="{{ asset('vendor/ckeditor/ckeditor5.umd.js') }}"></script>

@if ($errors->any())
    <div class="form-errors">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<p><label>Status
    <select name="status">
        <option value="draft" @selected(old('status', isset($post) ? $post->status : 'draft') === 'draft')>Draft</option>
        <option value="published" @selected(old('status', isset($post) ? $post->status : 'draft') === 'published')>Published</option>
    </select>
</label></p>

<fieldset>
    <legend>Card image</legend>
    @if (isset($post) && $post->featured_image)
        <p><img src="{{ $post->featured_image }}" alt="" style="max-width:160px;height:auto;display:block;border-radius:var(--radius)"></p>
        <p><label><input type="checkbox" name="remove_card_image" value="1"> Remove card image</label></p>
    @endif
    <p><label>{{ isset($post) && $post->featured_image ? 'Replace image' : 'Upload image' }}
        <input type="file" name="card_image" accept="image/*"></label></p>
    <p class="muted">Shown on the blog listing card. Cropped to a square (1200×1200).</p>
</fieldset>

@foreach (['en', 'ro'] as $locale)
    <fieldset>
        <legend>{{ strtoupper($locale) }}</legend>
        <p><label>Title <input name="{{ $locale }}_title" value="{{ old("{$locale}_title", $t($locale)?->title) }}"></label></p>
        <p><label>Slug <input name="{{ $locale }}_slug" value="{{ old("{$locale}_slug", $t($locale)?->slug) }}"></label></p>
        <p><label>Subtitle <input name="{{ $locale }}_subtitle" value="{{ old("{$locale}_subtitle", $t($locale)?->subtitle) }}"></label></p>
        <textarea name="{{ $locale }}_body" id="editor_{{ $locale }}">{{ old("{$locale}_body", $t($locale)?->body) }}</textarea>
    </fieldset>
@endforeach

<script>
const {
    ClassicEditor, Essentials, Paragraph, Heading, Bold, Italic, Underline, Strikethrough, RemoveFormat,
    Link, List, BlockQuote, Alignment, Indent, HorizontalLine,
    Image, ImageToolbar, ImageCaption, ImageStyle, ImageResize, ImageUpload, ImageInsert, LinkImage,
    Table, TableToolbar, SourceEditing, SimpleUploadAdapter
} = CKEDITOR;

['en', 'ro'].forEach(function (loc) {
    ClassicEditor.create(document.querySelector('#editor_' + loc), {
        licenseKey: 'GPL',
        plugins: [Essentials, Paragraph, Heading, Bold, Italic, Underline, Strikethrough, RemoveFormat,
                  Link, List, BlockQuote, Alignment, Indent, HorizontalLine,
                  Image, ImageToolbar, ImageCaption, ImageStyle, ImageResize, ImageUpload, ImageInsert, LinkImage,
                  Table, TableToolbar, SourceEditing, SimpleUploadAdapter],
        toolbar: ['undo', 'redo', '|', 'sourceEditing', '|', 'heading', '|',
                  'bold', 'italic', 'underline', 'strikethrough', 'removeFormat', '|',
                  'link', 'insertImage', 'insertTable', 'blockQuote', 'horizontalLine', '|',
                  'alignment', '|', 'bulletedList', 'numberedList', 'outdent', 'indent'],
        image: {
            resizeUnit: '%',
            toolbar: ['toggleImageCaption', 'imageTextAlternative', '|',
                      'imageStyle:inline', 'imageStyle:wrapText', 'imageStyle:breakText', '|',
                      'resizeImage']
        },
        table: {
            contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
        },
        simpleUpload: {
            uploadUrl: '{{ route('admin.attachments.store') }}',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }
    }).catch(function (e) { console.error(e); });
});
</script>
