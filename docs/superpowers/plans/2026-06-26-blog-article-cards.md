# Blog Article Cards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Present blog articles as cards — a square representative image on top (2/3), title + excerpt below (1/3) — and let admins upload that card image in the post editor.

**Architecture:** Reuse the existing (currently unused) `posts.featured_image` column and the existing Intervention Image pipeline. The card image is per-post (shared across EN/RO), square-cropped server-side at upload time, stored to the public disk with a root-relative URL. The blog listing renders a responsive CSS grid; posts without an image fall back to today's text-only card.

**Tech Stack:** Laravel 11 (PHP), Blade, Intervention Image v4 (GD driver), PHPUnit, token-driven CSS (`public/css/skin.css`).

## Global Constraints

- **No new migration** — `posts.featured_image` (nullable string) already exists (`database/migrations/2026_06_26_000002_create_posts_table.php:14`).
- **Root-relative image URLs only** — store `parse_url(Storage::disk('public')->url($path), PHP_URL_PATH)`; never bake in `APP_URL` (matches `AttachmentController`).
- **Upload validation** — card image must pass `['image', 'max:8192']` (matches `AttachmentController`).
- **Square crop dimensions** — 1200×1200, JPEG quality 82, centered cover crop.
- **Token-driven CSS** — all new visual CSS in `public/css/skin.css` uses existing CSS custom properties (no raw color/font/spacing literals); positional-only CSS may use literals but prefer tokens. Preserve the SkinCssTest contract strings.
- **Docs upkeep (CLAUDE.md)** — any style/infra change updates `README.md` and `docs/theme-style-map.json` in the same change.
- **Run tests with:** `php artisan test --filter <TestClass>` (or the local PHP toolchain prefix per memory `local-php-toolchain.md`).

---

### Task 1: Controller — save & remove the card image

**Files:**
- Modify: `app/Http/Controllers/Admin/PostController.php`
- Test: `tests/Feature/AdminPostCrudTest.php`

**Interfaces:**
- Consumes: existing `Post` model (`featured_image` fillable via `$guarded = []`), `Media` model, Intervention `ImageManager` + GD `Driver` (already a dependency, see `AttachmentController`).
- Produces: `posts.featured_image` is set to a root-relative URL string when a `card_image` file is uploaded, or `null` when `remove_card_image` is truthy. Private helper `cardImageUrl(Request $request): ?string`.

- [ ] **Step 1: Write the failing tests**

Add these imports to the top of `tests/Feature/AdminPostCrudTest.php` (after the existing `use` lines):

```php
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
```

Add these two test methods inside the `AdminPostCrudTest` class:

```php
    public function test_uploading_a_card_image_saves_a_square_featured_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post('/admin/posts', [
            'status' => 'draft',
            'en_title' => 'Hello', 'en_slug' => 'hello',
            'card_image' => UploadedFile::fake()->image('card.jpg', 2000, 1000),
        ])->assertRedirect('/admin/posts');

        $post = Post::first();
        $this->assertNotNull($post->featured_image);
        // Root-relative URL (portable across host/port/domain), not absolute.
        $this->assertStringStartsWith('/', $post->featured_image);
        $this->assertStringNotContainsString('http', $post->featured_image);

        $media = Media::first();
        $this->assertNotNull($media);
        $this->assertSame(1200, $media->width);
        $this->assertSame(1200, $media->height);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_removing_a_card_image_nulls_featured_image(): void
    {
        $post = Post::create(['status' => 'draft', 'featured_image' => '/storage/media/old.jpg']);
        $post->translations()->create(['locale' => 'en', 'title' => 'T', 'slug' => 't']);

        $this->actingAs($this->admin())->put("/admin/posts/{$post->id}", [
            'status' => 'draft',
            'en_title' => 'T', 'en_slug' => 't',
            'remove_card_image' => '1',
        ])->assertRedirect('/admin/posts');

        $this->assertNull($post->fresh()->featured_image);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter AdminPostCrudTest`
Expected: the two new tests FAIL — `featured_image` is `null` after upload (no handling yet) and not nulled on remove.

- [ ] **Step 3: Implement the controller changes**

In `app/Http/Controllers/Admin/PostController.php`, add these imports below the existing `use` lines:

```php
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
```

Replace the existing `postData()` method:

```php
    private function postData(Request $request): array
    {
        return [
            'status' => $request->input('status', 'draft'),
            'published_at' => $request->input('status') === 'published' ? now() : null,
        ];
    }
```

with:

```php
    private function postData(Request $request): array
    {
        $data = [
            'status' => $request->input('status', 'draft'),
            'published_at' => $request->input('status') === 'published' ? now() : null,
        ];

        $cardImage = $this->cardImageUrl($request);
        if ($cardImage !== null) {
            $data['featured_image'] = $cardImage;
        } elseif ($request->boolean('remove_card_image')) {
            $data['featured_image'] = null;
        }

        return $data;
    }

    // Upload a per-post card image: validate, square-crop (centered cover) to
    // 1200x1200, store on the public disk, record a Media row, and return the
    // root-relative URL. Returns null when no file was submitted.
    private function cardImageUrl(Request $request): ?string
    {
        $file = $request->file('card_image');
        if ($file === null) {
            return null;
        }

        validator(['card_image' => $file], ['card_image' => ['image', 'max:8192']])->validate();

        $manager = new ImageManager(new Driver());
        $image = $manager->decodePath($file->getRealPath());
        $image->cover(1200, 1200);

        $path = 'media/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($path, (string) $image->encodeUsingFileExtension('jpg', quality: 82));

        // Root-relative URL so the image resolves on any host/port/domain.
        $url = parse_url(Storage::disk('public')->url($path), PHP_URL_PATH);

        Media::create([
            'path' => $path,
            'url' => $url,
            'width' => $image->width(),
            'height' => $image->height(),
        ]);

        return $url;
    }
```

(`store()` and `update()` already call `$this->postData($request)` and `Post::create` / `$post->update`, so no change is needed there.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter AdminPostCrudTest`
Expected: PASS (all tests in the class, including the pre-existing ones).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/PostController.php tests/Feature/AdminPostCrudTest.php
git commit -m "feat: save square-cropped card image to posts.featured_image

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Editor — card-image upload field

**Files:**
- Modify: `resources/views/admin/posts/_form.blade.php`
- Modify: `resources/views/admin/posts/create.blade.php`
- Modify: `resources/views/admin/posts/edit.blade.php`
- Test: `tests/Feature/AdminPostFormTest.php`

**Interfaces:**
- Consumes: posts the field `card_image` (file) and `remove_card_image` (checkbox) that Task 1's controller reads; on edit, reads `$post->featured_image` for the thumbnail.
- Produces: the create/edit forms send `enctype="multipart/form-data"` so file uploads reach the server.

- [ ] **Step 1: Write the failing test**

Add this test method inside `tests/Feature/AdminPostFormTest.php`:

```php
    public function test_create_form_has_card_image_upload(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/posts/create')
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="card_image"', false);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter AdminPostFormTest`
Expected: FAIL — neither `enctype` nor `name="card_image"` is present yet.

- [ ] **Step 3: Add `enctype` to both forms**

In `resources/views/admin/posts/create.blade.php`, change:

```blade
        <form method="POST" action="{{ route('admin.posts.store') }}">
```
to:
```blade
        <form method="POST" action="{{ route('admin.posts.store') }}" enctype="multipart/form-data">
```

In `resources/views/admin/posts/edit.blade.php`, change:

```blade
        <form method="POST" action="{{ route('admin.posts.update', $post) }}">
```
to:
```blade
        <form method="POST" action="{{ route('admin.posts.update', $post) }}" enctype="multipart/form-data">
```

- [ ] **Step 4: Add the card-image field to the shared form**

In `resources/views/admin/posts/_form.blade.php`, insert the following block immediately **after** the `Status` paragraph (after its closing `</label></p>` on line 10) and **before** the `@foreach (['en', 'ro'] ...)` loop:

```blade
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
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter AdminPostFormTest`
Expected: PASS (both the new test and the existing `test_create_form_uses_ckeditor`).

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/posts/_form.blade.php resources/views/admin/posts/create.blade.php resources/views/admin/posts/edit.blade.php tests/Feature/AdminPostFormTest.php
git commit -m "feat: add card-image upload field to the post editor

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Listing — render cards (image + fallback)

**Files:**
- Modify: `resources/views/blog/index.blade.php`
- Test: `tests/Feature/PublicBlogTest.php`

**Interfaces:**
- Consumes: `$posts` (each a `Post` with `featured_image` and `->translation($locale)`), `$locale` — both already passed by `BlogController@index`.
- Produces: listing markup with a `.blog-grid` wrapper; each card uses `.card__media` (image present) + `.card__body`, or a text-only `.card` (no image); the whole card links to the article.

- [ ] **Step 1: Write the failing tests**

Add these two test methods inside `tests/Feature/PublicBlogTest.php`:

```php
    public function test_card_shows_image_when_featured_image_set(): void
    {
        $post = $this->publishedPost();
        $post->update(['featured_image' => '/storage/media/pic.jpg']);

        $this->get('/en/blog')->assertOk()
            ->assertSee('card__media', false)
            ->assertSee('/storage/media/pic.jpg', false);
    }

    public function test_card_is_text_only_without_featured_image(): void
    {
        $this->publishedPost();

        $response = $this->get('/en/blog')->assertOk();
        $response->assertSee('blog-grid', false);
        $response->assertDontSee('card__media', false);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter PublicBlogTest`
Expected: FAIL — `blog-grid` / `card__media` are not in the current markup.

- [ ] **Step 3: Rewrite the listing view**

Replace the entire contents of `resources/views/blog/index.blade.php` with:

```blade
@extends('layouts.app')
@section('title', 'Blog')
@section('content')
    <div class="container">
        <h1>Blog</h1>
        <div class="blog-grid">
            @foreach ($posts as $post)
                @php($t = $post->translation($locale))
                <a class="card{{ $post->featured_image ? ' card--media' : '' }}" href="/{{ $locale }}/blog/{{ $t->slug }}">
                    @if ($post->featured_image)
                        <img class="card__media" src="{{ $post->featured_image }}" alt="{{ $t->title }}">
                    @endif
                    <div class="card__body">
                        <h2>{{ $t->title }}</h2>
                        <p class="muted">{{ $t->excerpt }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endsection
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter PublicBlogTest`
Expected: PASS (including the pre-existing listing tests, e.g. `test_index_lists_published_posts`).

- [ ] **Step 5: Commit**

```bash
git add resources/views/blog/index.blade.php tests/Feature/PublicBlogTest.php
git commit -m "feat: render blog listing as image/text cards in a grid

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Styling — card grid + square media

**Files:**
- Modify: `public/css/skin.css`
- Test: `tests/Unit/SkinCssTest.php`

**Interfaces:**
- Consumes: the class names produced by Task 3 (`.blog-grid`, `.card--media`, `.card__media`, `.card__body`) and existing tokens (`--space-unit`, `--radius`, `--color-muted`).
- Produces: CSS rules selectable by those names; preserves all existing SkinCssTest contract strings.

- [ ] **Step 1: Write the failing test**

Add this test method inside `tests/Unit/SkinCssTest.php`:

```php
    public function test_skin_defines_blog_card_grid(): void
    {
        $css = file_get_contents(public_path('css/skin.css'));

        $this->assertStringContainsString('.blog-grid', $css);
        $this->assertStringContainsString('.card__media', $css);
        $this->assertStringContainsString('.card__body', $css);
        // Square image area via aspect-ratio + cover crop.
        $this->assertMatchesRegularExpression('/\.card__media\s*\{[^}]*aspect-ratio:\s*1/', $css);
        $this->assertMatchesRegularExpression('/\.card__media\s*\{[^}]*object-fit:\s*cover/', $css);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter SkinCssTest`
Expected: FAIL — none of `.blog-grid` / `.card__media` / `.card__body` exist yet.

- [ ] **Step 3: Add the card styles**

In `public/css/skin.css`, immediately **after** the `.card a { text-decoration: none; }` line (line 100), insert:

```css

/* Blog listing: responsive card grid. Each card with an image shows a square
   media area (2/3 of a ~3:2 card) above the title + excerpt body (1/3). Cards
   without an image fall back to the plain text .card. Token-driven. */
.blog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
    gap: calc(var(--space-unit) * 6);
    margin-top: calc(var(--space-unit) * 6);
}
.card--media {
    display: flex;
    flex-direction: column;
    padding: 0;                 /* image is flush to the card edges */
    overflow: hidden;           /* clip the image to the card's rounded corners */
}
.card__media {
    display: block;
    width: 100%;
    aspect-ratio: 1 / 1;        /* square: the top 2/3 of the card */
    object-fit: cover;          /* fill the square, crop overflow, no distortion */
}
.card--media .card__body {
    padding: calc(var(--space-unit) * 4);
}
.card__body h2 {
    margin-top: 0;
}
/* Keep card heights tidy: clamp the excerpt to a few lines. */
.card__body .muted {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter SkinCssTest`
Expected: PASS (the new test plus all existing SkinCssTest contract tests).

- [ ] **Step 5: Commit**

```bash
git add public/css/skin.css tests/Unit/SkinCssTest.php
git commit -m "feat: style blog card grid with square media

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Documentation — README + theme-style-map

**Files:**
- Modify: `README.md`
- Modify: `docs/theme-style-map.json`

**Interfaces:**
- Consumes: nothing at runtime — documentation only.
- Produces: docs that accurately describe the card image feature and the new style classes/tokens (required by `CLAUDE.md`).

- [ ] **Step 1: Update `docs/theme-style-map.json`**

In the `elements` array, replace the existing "Cards / content panels (.card)" element object:

```json
    {
      "element": "Cards / content panels (.card)",
      "applies_to": "all pages (e.g. blog list items)",
      "selectors": [".card @ skin.css line 6 (radius, shadow)", ".card @ skin.css line 95 (bg, border, padding)", ".card a @ skin.css"],
      "tokens": ["color-bg-alt", "color-fg", "radius", "shadow", "space-unit"],
      "notes": "Two .card rules exist (later wins for border opacity); behavior correct, could be consolidated."
    },
```

with (adds the blog card-grid selectors/notes):

```json
    {
      "element": "Cards / content panels (.card, .blog-grid, .card--media, .card__media, .card__body)",
      "applies_to": "all pages (e.g. blog list items)",
      "selectors": [
        ".card @ skin.css line 6 (radius, shadow)",
        ".card @ skin.css line 95 (bg, border, padding)",
        ".card a @ skin.css",
        ".blog-grid @ skin.css (responsive grid: auto-fill minmax(16rem,1fr))",
        ".card--media @ skin.css (image card: flex column, padding:0, overflow:hidden)",
        ".card__media @ skin.css (square image: aspect-ratio 1/1, object-fit cover)",
        ".card__body @ skin.css (title + line-clamped excerpt)"
      ],
      "tokens": ["color-bg-alt", "color-fg", "color-muted", "radius", "shadow", "space-unit"],
      "notes": "Blog listing (resources/views/blog/index.blade.php) renders posts as a .blog-grid of cards. A post with posts.featured_image shows a square .card__media (top 2/3) above .card__body (title + excerpt, bottom 1/3); a post without one falls back to the plain text .card. The whole card is a link. Two base .card rules exist (later wins for border opacity); behavior correct, could be consolidated."
    },
```

- [ ] **Step 2: Update `README.md`**

Find the section that documents the blog / posts data model or the admin editor (search for `featured_image`, `post_translations`, or `AttachmentController`). Add (adapt the wording to the surrounding prose):

```markdown
- **Blog card image.** Each post can have a representative card image, stored on
  `posts.featured_image` (one image per post, shared across locales). Admins upload
  it via the **Card image** field in the post editor; on save it is validated
  (`image`, max 8 MB), square-cropped server-side to 1200×1200 (Intervention Image,
  centered cover crop), stored on the public disk as a `Media` row, and saved as a
  root-relative URL. The blog listing (`resources/views/blog/index.blade.php`) renders
  posts as a `.blog-grid` of cards — a square image on top and title + excerpt below;
  posts without an image render a text-only card.
```

- [ ] **Step 3: Verify the JSON is valid**

Run: `php -r "json_decode(file_get_contents('docs/theme-style-map.json')); echo json_last_error_message();"`
Expected: prints `No error`.

- [ ] **Step 4: Commit**

```bash
git add README.md docs/theme-style-map.json
git commit -m "docs: document blog card image + card-grid styles

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: Full suite verification

**Files:** none (verification only).

- [ ] **Step 1: Run the entire test suite**

Run: `php artisan test`
Expected: PASS — no regressions across the suite (PostController, blog listing, skin CSS, attachment upload, theme tokens).

- [ ] **Step 2: Manual smoke check (optional but recommended)**

Start the app, log in as admin, create a post with a non-square image, publish it, and view `/en/blog`. Confirm: the card shows a centered square crop on top with title + excerpt below; a post without an image shows a text-only card; the whole card is clickable.
