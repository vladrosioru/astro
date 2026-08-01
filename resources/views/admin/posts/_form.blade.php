@php($t = isset($post) ? fn ($l) => optional($post->translation($l)) : fn ($l) => null)
@php($submitLabel = $submitLabel ?? 'Save')

@push('head')
    {{-- CKEditor's own UI stylesheet. article.css is deliberately NOT loaded
         here: it is token-driven and belongs to the themed article, which the
         Preview frame renders in isolation. admin.css re-skins the editor
         chrome in --adm-* terms instead. --}}
    <link rel="stylesheet" href="{{ asset('vendor/ckeditor/ckeditor5.css') }}">
    <script src="{{ asset('vendor/ckeditor/ckeditor5.umd.js') }}"></script>
@endpush

{{-- Everything the preview iframe needs to render the article exactly as the
     public site will: the active theme's stylesheets and token values, plus
     the app-level article CSS. None of it is applied to this page. --}}
{{-- Keep every PHP directive in this file in the inline, parenthesised form.
     Blade stores block-form PHP as a raw region before it strips comments, so
     a block opener/closer pair anywhere here — even inside a comment — would
     swallow everything between them, uncompiled. --}}
@php($previewAssets = ['css' => array_merge(app('theme.manager')->cssUrls(), [versioned_asset('css/article.css'), asset('vendor/ckeditor/ckeditor5.css')]), 'tokens' => app('theme.manager')->tokens(), 'image' => isset($post) ? $post->featured_image : null])
<script type="application/json" id="adm-preview-assets">@json($previewAssets)</script>

@if ($errors->any())
    <div class="adm-err">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php($isPublished = isset($post) && $post->status === 'published')

<div class="adm-grid">
    <div>
        {{-- One locale on screen at a time. Both panes stay in the DOM so both
             locales submit with the form — the inactive one is only hidden. --}}
        <div class="adm-tabs">
            @foreach (['en', 'ro'] as $index => $locale)
                <button class="adm-tabs__btn{{ $index === 0 ? ' is-on' : '' }}" type="button" data-locale-tab="{{ $locale }}">{{ strtoupper($locale) }}</button>
            @endforeach
        </div>

        @foreach (['en', 'ro'] as $index => $locale)
            @php($existingSlug = $t($locale)?->slug)
            @php($canRegenerate = isset($post) ? (is_null($post->first_published_at) && $post->status !== 'published') : true)
            <div class="adm-locale" data-locale-pane="{{ $locale }}" @if($index > 0) hidden @endif>
                <div class="adm-field">
                    <label for="{{ $locale }}_title">Title</label>
                    <input id="{{ $locale }}_title" type="text" name="{{ $locale }}_title" value="{{ old("{$locale}_title", $t($locale)?->title) }}">
                </div>

                <div class="adm-two">
                    <div class="adm-field">
                        <label for="{{ $locale }}_slug_display">Slug</label>
                        <input type="text" id="{{ $locale }}_slug_display" value="{{ $existingSlug }}" readonly>
                        @if ($existingSlug && $canRegenerate)
                            <span class="adm-field__hint"><label><input type="checkbox" id="{{ $locale }}_regenerate_slug_cb" name="{{ $locale }}_regenerate_slug" value="1"> Regenerate from title on save</label></span>
                        @elseif ($existingSlug)
                            <span class="adm-field__hint">Locked (this post has been published)</span>
                        @else
                            <span class="adm-field__hint">Generated from the title on save</span>
                        @endif
                    </div>
                    <div class="adm-field">
                        <label for="{{ $locale }}_subtitle">Subtitle</label>
                        <input id="{{ $locale }}_subtitle" type="text" name="{{ $locale }}_subtitle" value="{{ old("{$locale}_subtitle", $t($locale)?->subtitle) }}">
                    </div>
                </div>

                <div class="adm-field">
                    <label>Body</label>
                    <div class="adm-editor" id="editorPaper_{{ $locale }}"><textarea name="{{ $locale }}_body" id="editor_{{ $locale }}">{{ old("{$locale}_body", $t($locale)?->body) }}</textarea></div>
                    <iframe class="adm-editor__frame" id="preview_{{ $locale }}" title="Preview of the {{ strtoupper($locale) }} article" hidden></iframe>
                    <span class="adm-field__hint">The editing surface is admin-styled. <strong>Preview</strong> renders the article in an isolated frame using the live theme, so what you check is what readers get.</span>
                </div>

                <div class="adm-actions">
                    <button class="adm-btn adm-btn--primary" type="submit">{{ $submitLabel }}</button>
                    <button class="preview-toggle adm-btn" type="button" data-locale="{{ $locale }}">Preview</button>
                </div>
            </div>
        @endforeach
    </div>

    <aside class="adm-rail">
        <div class="adm-panel">
            <div class="adm-panel__head"><h3>Publishing</h3></div>
            <div class="adm-panel__body">
                <div class="adm-field">
                    <label for="post-status">Status</label>
                    <select id="post-status" name="status">
                        <option value="draft" @selected(old('status', isset($post) ? $post->status : 'draft') === 'draft')>Draft</option>
                        <option value="published" @selected(old('status', isset($post) ? $post->status : 'draft') === 'published')>Published</option>
                    </select>
                </div>

                <div class="adm-field">
                    <label for="published_date_input">Date</label>
                    <input type="date" name="published_date" id="published_date_input"
                           min="2026-01-01" max="{{ now()->toDateString() }}"
                           @if ($isPublished) readonly class="date-locked" @endif
                           value="{{ old('published_date', isset($post) ? $post->published_at?->toDateString() : null) }}">
                    @if ($isPublished)
                        <span class="adm-field__hint"><label><input type="checkbox" name="unlock_date" value="1"> This post is already published — check to change its date</label></span>
                    @endif
                </div>

                <div class="adm-field">
                    <label for="post-author">Author</label>
                    <select id="post-author" name="author_id">
                        <option value="">&mdash; none &mdash;</option>
                        @foreach ($authors as $author)
                            <option value="{{ $author->id }}" @selected(old('author_id', isset($post) ? $post->author_id : null) == $author->id)>{{ $author->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="adm-field">
                    <label for="post-reading-time">Minutes to read</label>
                    <input id="post-reading-time" type="number" name="reading_time" min="1" max="99" step="1"
                           value="{{ old('reading_time', isset($post) ? $post->reading_time : null) }}">
                </div>
            </div>
        </div>

        <div class="adm-panel">
            <div class="adm-panel__head"><h3>Card image</h3></div>
            <div class="adm-panel__body">
                @if (isset($post) && $post->featured_image)
                    <div class="adm-field">
                        <img class="adm-field__preview" src="{{ $post->featured_image }}" alt="">
                        <span class="adm-field__hint"><label><input type="checkbox" name="remove_card_image" value="1"> Remove card image</label></span>
                    </div>
                @endif
                <div class="adm-field">
                    <label for="card-image">{{ isset($post) && $post->featured_image ? 'Replace image' : 'Upload image' }}</label>
                    <input id="card-image" type="file" name="card_image" accept="image/*">
                    <span class="adm-field__hint">Shown on the blog listing card. Cropped to a square (1200×1200).</span>
                </div>
            </div>
        </div>
    </aside>
</div>

@push('scripts')
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

// Locale tabs: one language on screen at a time. Both panes stay in the DOM
// (only `hidden` toggles) so both locales still submit with the form.
(function () {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-locale-tab]'));
    var panes = Array.prototype.slice.call(document.querySelectorAll('[data-locale-pane]'));

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (other) { other.classList.remove('is-on'); });
            tab.classList.add('is-on');
            panes.forEach(function (pane) {
                pane.hidden = pane.dataset.localePane !== tab.dataset.localeTab;
            });
        });
    });
})();

// Live preview: renders the article the way the public site will, inside an
// iframe. The theme's stylesheets and token values are loaded *in the frame*
// only — the admin page itself stays theme-free (see layouts/admin.blade.php).
// The body HTML is injected after the frame loads rather than interpolated
// into the srcdoc string, so no amount of quoting in the article can break the
// document.
(function () {
    var assets = JSON.parse(document.getElementById('adm-preview-assets').textContent);

    function frameDocument() {
        var vars = Object.keys(assets.tokens).map(function (name) {
            return '--' + name + ':' + assets.tokens[name] + ';';
        }).join('');
        var links = assets.css.map(function (href) {
            return '<link rel="stylesheet" href="' + href + '">';
        }).join('');

        return '<!DOCTYPE html><html><head><meta charset="utf-8">' + links +
            '<style>:root{' + vars + '}body{margin:0}</style></head><body>' +
            '<header class="journal-hero"><h1 class="journal-hero__title"></h1></header>' +
            (assets.image ? '<div class="article-image"><img src="' + assets.image + '" alt=""></div>' : '') +
            '<article><div class="article-paper"><div class="ck-content"></div></div></article>' +
            '</body></html>';
    }

    ['en', 'ro'].forEach(function (loc) {
        var button = document.querySelector('.preview-toggle[data-locale="' + loc + '"]');
        var editorPaper = document.getElementById('editorPaper_' + loc);
        var frame = document.getElementById('preview_' + loc);
        var titleInput = document.querySelector('input[name="' + loc + '_title"]');
        if (! button || ! editorPaper || ! frame) return;

        button.addEventListener('click', function () {
            if (! frame.hidden) {
                editorPaper.hidden = false;
                frame.hidden = true;
                button.textContent = 'Preview';

                return;
            }

            var editable = editorPaper.querySelector('[contenteditable]');
            var editor = editable && editable.ckeditorInstance;

            frame.addEventListener('load', function fill() {
                frame.removeEventListener('load', fill);
                var doc = frame.contentDocument;
                doc.querySelector('.journal-hero__title').textContent = titleInput ? titleInput.value : '';
                doc.querySelector('.ck-content').innerHTML = editor ? editor.getData() : '';
                // Grow the frame to its content so the preview scrolls with the
                // page instead of inside a fixed-height box.
                frame.style.height = doc.body.scrollHeight + 'px';
            });

            frame.srcdoc = frameDocument();
            editorPaper.hidden = true;
            frame.hidden = false;
            button.textContent = 'Edit';
        });
    });
})();

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
@endpush
