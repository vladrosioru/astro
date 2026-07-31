@php($t = isset($post) ? fn ($l) => optional($post->translation($l)) : fn ($l) => null)
<link rel="stylesheet" href="{{ asset('vendor/ckeditor/ckeditor5.css') }}">
<link rel="stylesheet" href="{{ versioned_asset('css/article.css') }}">
<script src="{{ asset('vendor/ckeditor/ckeditor5.umd.js') }}"></script>
<style>.date-locked { border-color: #c00; color: #c00; background: #fee; }</style>

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

@php($isPublished = isset($post) && $post->status === 'published')
<p>
    <label>Date
        <input type="date" name="published_date" id="published_date_input"
               min="2026-01-01" max="{{ now()->toDateString() }}"
               @if ($isPublished) readonly class="date-locked" @endif
               value="{{ old('published_date', isset($post) ? $post->published_at?->toDateString() : null) }}">
    </label>
    @if ($isPublished)
        <label><input type="checkbox" name="unlock_date" value="1"> This post is already published &mdash; check to change its date</label>
    @endif
</p>

<p><label>Author
    <select name="author_id">
        <option value="">&mdash; none &mdash;</option>
        @foreach ($authors as $author)
            <option value="{{ $author->id }}" @selected(old('author_id', isset($post) ? $post->author_id : null) == $author->id)>{{ $author->name }}</option>
        @endforeach
    </select>
</label></p>

<p><label>Minutes to read
    <input type="number" name="reading_time" min="1" max="99" step="1"
           value="{{ old('reading_time', isset($post) ? $post->reading_time : null) }}">
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
        <p><button type="button" class="preview-toggle" data-locale="{{ $locale }}">Preview</button></p>
        <div class="article-paper" id="editorPaper_{{ $locale }}">
            <textarea name="{{ $locale }}_body" id="editor_{{ $locale }}">{{ old("{$locale}_body", $t($locale)?->body) }}</textarea>
        </div>
        <div class="admin-post-preview" id="preview_{{ $locale }}" hidden>
            <header class="journal-hero"><h1 class="journal-hero__title"></h1></header>
            @if (isset($post) && $post->featured_image)
                <div class="article-image"><img src="{{ $post->featured_image }}" alt=""></div>
            @endif
            <article><div class="article-paper"><div class="ck-content"></div></div></article>
        </div>
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

// Live preview toggle: swaps the editing view for a read-only render using
// blog/show.blade.php's own markup/CSS (.journal-hero, .article-paper >
// .ck-content), reading straight from the CKEditor instance so unsaved edits
// show up too, not just what's been saved.
['en', 'ro'].forEach(function (loc) {
    var btn = document.querySelector('.preview-toggle[data-locale="' + loc + '"]');
    var editorPaper = document.getElementById('editorPaper_' + loc);
    var preview = document.getElementById('preview_' + loc);
    var titleInput = document.querySelector('input[name="' + loc + '_title"]');
    if (!btn || !editorPaper || !preview) return;

    btn.addEventListener('click', function () {
        if (preview.hidden) {
            var editable = editorPaper.querySelector('[contenteditable]');
            var editor = editable && editable.ckeditorInstance;
            preview.querySelector('.journal-hero__title').textContent = titleInput.value;
            preview.querySelector('.ck-content').innerHTML = editor ? editor.getData() : '';
            editorPaper.hidden = true;
            preview.hidden = false;
            btn.textContent = 'Edit';
        } else {
            editorPaper.hidden = false;
            preview.hidden = true;
            btn.textContent = 'Preview';
        }
    });
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

// Locked date field (already-published posts): the "unlock" checkbox toggles
// readonly/styling client-side, but the server enforces the lock
// independently (see PostController::postData()) so this is UX only.
(function () {
    var unlockCb = document.querySelector('input[name="unlock_date"]');
    var dateInput = document.getElementById('published_date_input');
    if (!unlockCb || !dateInput) return;

    unlockCb.addEventListener('change', function () {
        dateInput.readOnly = !unlockCb.checked;
        dateInput.classList.toggle('date-locked', !unlockCb.checked);
    });
})();
</script>
