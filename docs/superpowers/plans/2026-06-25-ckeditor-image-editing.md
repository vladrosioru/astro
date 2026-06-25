# CKEditor 5 Image Editing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Trix with a self-hosted CKEditor 5 in the admin blog editor so authors can resize, align, and text-wrap images, with the richer HTML safely sanitized and correctly rendered on the public blog.

**Architecture:** CKEditor 5 ships as a self-hosted browser bundle in `public/vendor/ckeditor/` (no Node). A dedicated HTMLPurifier "blog" profile whitelists CKEditor's image markup. The existing PHP upload endpoint is reused (accepts CKEditor's `upload` field). Token-driven skin CSS lays out the alignment classes on the public page.

**Tech Stack:** PHP 8.2+, Laravel 11, CKEditor 5 (GPL, self-hosted), `mews/purifier` (HTMLPurifier), Intervention Image, SQLite tests, PHPUnit.

Implements `docs/superpowers/specs/2026-06-25-ckeditor-image-editing-design.md`. Depends on Plan 2 (admin blog, `AttachmentController`, `PostController`).

## Global Constraints

- **No Node** — CKEditor self-hosted as `.js`/`.css` in `public/vendor/ckeditor/`, `<script>`/`<link>` only.
- CKEditor `licenseKey: 'GPL'`; no premium plugins.
- Body HTML sanitized with the **`blog`** purifier profile on save; scripts/handlers always stripped.
- Allowed image classes only: `image`, `image-style-align-left`, `image-style-align-right`, `image-style-align-center`, `image-style-side`, `image_resized`.
- Uploads return **root-relative** URLs (`/storage/media/<uuid>.jpg`).
- SQLite `:memory:` tests; conventional commits; commit each task.

---

### Task 1: "blog" HTMLPurifier profile

**Files:**
- Create: `config/purifier.php` (via `vendor:publish`, then edit)
- Modify: `app/Http/Controllers/Admin/PostController.php` (use `clean($body, 'blog')`)
- Test: `tests/Unit/BlogPurifierTest.php`

**Interfaces:**
- Consumes: `clean()` helper (mews/purifier).
- Produces: a `blog` purifier profile that keeps `figure.image`, `figcaption`, `img` with `width`/`style`, and the whitelisted classes; strips `<script>`, event handlers, and unknown classes.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/BlogPurifierTest.php`:
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class BlogPurifierTest extends TestCase
{
    public function test_keeps_ckeditor_image_markup(): void
    {
        $html = '<figure class="image image-style-side"><img src="/storage/media/a.jpg" style="width:40%;" alt="x"><figcaption>Cap</figcaption></figure>';
        $clean = clean($html, 'blog');

        $this->assertStringContainsString('<figure', $clean);
        $this->assertStringContainsString('image-style-side', $clean);
        $this->assertStringContainsString('<figcaption', $clean);
        $this->assertStringContainsString('width:40%', $clean);
    }

    public function test_strips_scripts_and_unknown_classes(): void
    {
        $html = '<p>ok</p><script>alert(1)</script><figure class="evil"><img src="x" onerror="alert(1)"></figure>';
        $clean = clean($html, 'blog');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('evil', $clean);
        $this->assertStringContainsString('<p>ok</p>', $clean);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=BlogPurifierTest`
Expected: FAIL — the default profile strips `figure`/`figcaption` (no `blog` profile yet).

- [ ] **Step 3: Publish the purifier config**

Run: `php artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"`
Expected: `config/purifier.php` created.

- [ ] **Step 4: Add the `blog` profile and HTML5 element definitions**

Edit `config/purifier.php` — inside the `'settings' => [ ... ]` array, add a `blog` profile and a `custom_definition` (siblings of the existing `default` key):
```php
        'blog' => [
            'HTML.Allowed' => 'p,br,strong,em,u,s,h2,h3,blockquote,ul,ol,li,a[href|title|target|rel],figure[class],figcaption,img[src|alt|width|height|style|class]',
            'CSS.AllowedProperties' => 'width,height',
            'Attr.AllowedClasses' => 'image,image-style-align-left,image-style-align-right,image-style-align-center,image-style-side,image_resized',
            'HTML.TargetBlank' => true,
            'Attr.AllowedFrameTargets' => ['_blank'],
        ],

        'custom_definition' => [
            'id'   => 'blog-html5',
            'rev'  => 1,
            'elements' => [
                ['figure', 'Block', 'Flow', 'Common'],
                ['figcaption', 'Block', 'Flow', 'Common'],
            ],
        ],
```

- [ ] **Step 5: Use the profile when saving posts**

Edit `app/Http/Controllers/Admin/PostController.php` — in `saveTranslations()`, change the body line:
```php
                    'body' => clean($request->input("{$locale}_body", ''), 'blog'),
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=BlogPurifierTest`
Expected: PASS — both tests green. (If `figure` is still stripped, confirm the `custom_definition` key sits under `purifier.settings` and `rev` is incremented after edits.)

- [ ] **Step 7: Commit**

```bash
git add config/purifier.php app/Http/Controllers/Admin/PostController.php tests/Unit/BlogPurifierTest.php
git commit -m "feat: add blog HTMLPurifier profile for CKEditor image markup"
```

---

### Task 2: Upload endpoint accepts CKEditor's `upload` field

**Files:**
- Modify: `app/Http/Controllers/Admin/AttachmentController.php`
- Test: `tests/Feature/AttachmentUploadTest.php` (add a case)

**Interfaces:**
- Consumes: `Media`, `public` disk, Intervention Image (existing).
- Produces: `POST /admin/attachments` accepts the uploaded file under `upload` (CKEditor) or `file` (legacy/tests); returns `{ "url": "/storage/media/<uuid>.jpg" }`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/AttachmentUploadTest.php` (new method in the existing class):
```php
    public function test_accepts_ckeditor_upload_field(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/attachments', [
            'upload' => \Illuminate\Http\UploadedFile::fake()->image('p.jpg', 800, 600),
        ])->assertOk()->assertJsonStructure(['url']);

        $this->assertSame(1, \App\Models\Media::count());
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=AttachmentUploadTest`
Expected: FAIL — `test_accepts_ckeditor_upload_field` fails validation (only `file` is read).

- [ ] **Step 3: Read either field name**

Edit `app/Http/Controllers/Admin/AttachmentController.php` — replace the validation + file access at the top of `store()`:
```php
        $uploaded = $request->file('upload') ?? $request->file('file');
        abort_if($uploaded === null, 422, 'No file provided.');
        validator(['file' => $uploaded], ['file' => ['required', 'image', 'max:8192']])->validate();

        $manager = new ImageManager(new Driver());
        $image = $manager->decodePath($uploaded->getRealPath());
```
(Remove the old `$request->validate(['file' => ...])` line and the old `$request->file('file')` reference.)

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=AttachmentUploadTest`
Expected: PASS — all upload tests green (legacy `file` and new `upload`).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/AttachmentController.php tests/Feature/AttachmentUploadTest.php
git commit -m "feat: accept CKEditor upload field in attachment endpoint"
```

---

### Task 3: Swap the editor to CKEditor 5

**Files:**
- Download: `public/vendor/ckeditor/ckeditor5.umd.js`, `public/vendor/ckeditor/ckeditor5.css`
- Delete: `public/vendor/trix/*`
- Modify: `resources/views/admin/posts/_form.blade.php` (replace Trix with CKEditor)
- Test: `tests/Feature/AdminPostFormTest.php`

**Interfaces:**
- Consumes: `admin.attachments.store` route; `admin.posts.*` (existing).
- Produces: the create/edit form renders a `<textarea name="{locale}_body">` upgraded by CKEditor `ClassicEditor`, with image resize/align/caption/upload.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AdminPostFormTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPostFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_uses_ckeditor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/posts/create')
            ->assertOk()
            ->assertSee('vendor/ckeditor/ckeditor5.umd.js')
            ->assertSee('name="en_body"', false)
            ->assertDontSee('trix-editor');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=AdminPostFormTest`
Expected: FAIL — the form still references `trix-editor`, not CKEditor.

- [ ] **Step 3: Download the CKEditor bundle**

Run (pin a v44+ release; verify the exact current path at `https://cdn.ckeditor.com`):
```bash
mkdir -p public/vendor/ckeditor
curl -sL https://cdn.ckeditor.com/ckeditor5/44.1.0/ckeditor5.umd.js -o public/vendor/ckeditor/ckeditor5.umd.js
curl -sL https://cdn.ckeditor.com/ckeditor5/44.1.0/ckeditor5.css -o public/vendor/ckeditor/ckeditor5.css
test -s public/vendor/ckeditor/ckeditor5.umd.js && test -s public/vendor/ckeditor/ckeditor5.css && echo "ckeditor assets OK"
```
Expected: `ckeditor assets OK`. (The UMD build exposes a global `CKEDITOR` object with the named exports used below. If a pinned version's API differs, adjust the destructure in Step 4.)

- [ ] **Step 4: Replace Trix with CKEditor in the form partial**

Replace the entire contents of `resources/views/admin/posts/_form.blade.php`:
```blade
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
            toolbar: ['imageStyle:alignLeft', 'imageStyle:alignCenter', 'imageStyle:alignRight',
                      'imageStyle:side', '|', 'toggleImageCaption', 'imageTextAlternative', 'linkImage'],
            resizeUnit: '%'
        },
        simpleUpload: {
            uploadUrl: '{{ route('admin.attachments.store') }}',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }
    }).catch(function (e) { console.error(e); });
});
</script>
```

- [ ] **Step 5: Remove the Trix assets**

Run:
```bash
rm -rf public/vendor/trix
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=AdminPostFormTest`
Expected: PASS.

- [ ] **Step 7: Confirm the CRUD + sanitization tests still pass**

Run: `php artisan test --filter=AdminPostCrudTest`
Expected: PASS — server-side post saving is unchanged.

- [ ] **Step 8: Commit**

```bash
git add public/vendor/ckeditor resources/views/admin/posts/_form.blade.php tests/Feature/AdminPostFormTest.php
git rm -r --cached public/vendor/trix 2>/dev/null; git add -A
git commit -m "feat: replace Trix with self-hosted CKEditor 5"
```

---

### Task 4: Public image-alignment skin CSS

**Files:**
- Modify: `public/css/skin.css`
- Test: `tests/Unit/SkinCssTest.php`

**Interfaces:**
- Consumes: the alignment classes produced by CKEditor and preserved by the `blog` profile.
- Produces: float/centering rules so images lay out with text on the public article page.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SkinCssTest.php`:
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class SkinCssTest extends TestCase
{
    public function test_skin_defines_image_alignment_rules(): void
    {
        $css = file_get_contents(public_path('css/skin.css'));

        $this->assertStringContainsString('.image-style-align-left', $css);
        $this->assertStringContainsString('.image-style-align-right', $css);
        $this->assertStringContainsString('.image-style-side', $css);
        $this->assertStringContainsString('figure.image img', $css);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=SkinCssTest`
Expected: FAIL — those selectors are not in `skin.css` yet.

- [ ] **Step 3: Append the alignment rules**

Append to `public/css/skin.css`:
```css

/* CKEditor image layout (token-driven spacing). */
figure.image { margin: calc(var(--space-unit) * 4) 0; }
figure.image img { max-width: 100%; height: auto; border-radius: var(--radius); }
figure.image figcaption { color: var(--color-muted); font-size: 0.9em; padding-top: calc(var(--space-unit) * 1); }
.image-style-align-center { display: block; margin-inline: auto; }
.image-style-align-left { float: left; margin: 0 calc(var(--space-unit) * 4) calc(var(--space-unit) * 2) 0; }
.image-style-align-right { float: right; margin: 0 0 calc(var(--space-unit) * 2) calc(var(--space-unit) * 4); }
.image-style-side { float: right; max-width: 50%; margin: 0 0 calc(var(--space-unit) * 2) calc(var(--space-unit) * 4); }
.image_resized { max-width: 100%; }
.image_resized img { width: 100%; }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=SkinCssTest`
Expected: PASS.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS — every test across all plans green.

- [ ] **Step 6: Commit**

```bash
git add public/css/skin.css tests/Unit/SkinCssTest.php
git commit -m "feat: add public CSS for CKEditor image alignment"
```

---

## Self-Review

**Spec coverage:**
- §3.1 self-hosted assets — Task 3. ✔
- §3.2 editor config (plugins, image toolbar, simpleUpload, CSRF) — Task 3. ✔
- §3.3 upload endpoint accepts `upload` — Task 2. ✔
- §3.4 `blog` purifier profile (figure/figcaption, img width/style, class whitelist) + `clean($body,'blog')` — Task 1. ✔
- §3.5 public rendering CSS — Task 4. ✔
- §4 Trix removal — Task 3 (Step 5). ✔
- §5 testing (sanitization, upload, CRUD) — Tasks 1, 2, 3. ✔

**Placeholder scan:** All steps show concrete code/config and exact commands. The only runtime-verified item (CKEditor CDN version/global API) is explicitly flagged in Task 3 Step 3–4. ✔

**Type/name consistency:** `clean($body,'blog')`, profile key `blog`, `custom_definition` id `blog-html5`, fields `upload`/`file`, route `admin.attachments.store`, classes `image-style-*`/`image_resized`, asset paths `vendor/ckeditor/ckeditor5.umd.js|css` are consistent across tasks. ✔

---

## Execution Handoff

Inline execution in this session (consistent with Plans 1–2), via the executing-plans skill.
