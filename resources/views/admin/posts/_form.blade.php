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
    @php($existingSlug = $t($locale)?->slug)
    @php($canRegenerate = isset($post) ? (is_null($post->first_published_at) && $post->status !== 'published') : true)
    <fieldset>
        <legend>{{ strtoupper($locale) }}</legend>
        <p><label>Title <input name="{{ $locale }}_title" value="{{ old("{$locale}_title", $t($locale)?->title) }}"></label></p>
        <p>
            <label>Slug <input type="text" id="{{ $locale }}_slug_display" value="{{ $existingSlug }}" readonly></label>
            @if ($existingSlug && $canRegenerate)
                <label><input type="checkbox" id="{{ $locale }}_regenerate_slug_cb" name="{{ $locale }}_regenerate_slug" value="1"> Regenerate from title on save</label>
            @elseif ($existingSlug)
                <span class="muted">Locked (this post has been published)</span>
            @else
                <span class="muted">Generated from the title on save</span>
            @endif
        </p>
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

// Approximate, client-side-only slug preview: the server always recomputes
// the authoritative slug (via Str::slug()) on Save, so this only has to be
// close enough to be useful while typing, not byte-for-byte identical.
function slugifyPreview(str) {
    var diacritics = {
        'ă': 'a', 'â': 'a', 'î': 'i', 'ș': 's', 'ş': 's', 'ț': 't', 'ţ': 't',
        'á': 'a', 'à': 'a', 'ä': 'a', 'é': 'e', 'è': 'e', 'ë': 'e',
        'í': 'i', 'ì': 'i', 'ö': 'o', 'ó': 'o', 'ò': 'o', 'ü': 'u', 'ú': 'u', 'ù': 'u', 'ç': 'c'
    };
    return str.toLowerCase()
        .split('').map(function (ch) { return diacritics[ch] || ch; }).join('')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

['en', 'ro'].forEach(function (loc) {
    var titleInput = document.querySelector('input[name="' + loc + '_title"]');
    var slugDisplay = document.getElementById(loc + '_slug_display');
    var regenerateCb = document.getElementById(loc + '_regenerate_slug_cb');
    if (!titleInput || !slugDisplay) return;

    var isNewTranslation = !slugDisplay.value;
    var storedSlug = slugDisplay.value;

    titleInput.addEventListener('input', function () {
        if (isNewTranslation || (regenerateCb && regenerateCb.checked)) {
            slugDisplay.value = slugifyPreview(titleInput.value);
        }
    });

    if (regenerateCb) {
        regenerateCb.addEventListener('change', function () {
            slugDisplay.value = regenerateCb.checked ? slugifyPreview(titleInput.value) : storedSlug;
        });
    }
});
</script>
