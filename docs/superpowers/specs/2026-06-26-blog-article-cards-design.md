# Blog Article Cards with Editor Card-Image — Design

**Date:** 2026-06-26
**Status:** Approved (pending spec review)

## Problem

The blog listing (`resources/views/blog/index.blade.php`) renders each article as a
text-only `<article class="card">` (title + excerpt). We want a visual "card"
presentation: the upper **2/3** of each card is a **square representative image**, the
lower **1/3** holds the **title and excerpt**. The article editor must let an admin add
that card image.

## Key context (already in the codebase)

- **`posts.featured_image`** — a nullable string column already exists
  (`database/migrations/2026_06_26_000002_create_posts_table.php:14`) but is currently
  unused: not written by `PostController`, not shown in the form. It lives on the **post**
  (not per-translation), so one card image is shared across the EN/RO versions. This is
  the home for the card image — **no new migration required**.
- **Upload pipeline exists** — `AttachmentController@store` uses Intervention Image (GD
  driver) to resize, stores to `storage/public/media`, records a `Media` row, and returns
  a **root-relative** URL (`parse_url(... , PHP_URL_PATH)`) so images resolve on any
  host/port. The card image reuses this storage + URL convention.
- **`.card` styling** is token-driven in `public/css/skin.css` (radius, shadow,
  background, border, padding via CSS custom properties).

## Decisions (from brainstorming)

1. **Editor mechanism:** dedicated **"Card image" file upload** on the post form (not
   picking from body images, not a URL field). Saved to `posts.featured_image`.
2. **Listing layout:** **responsive grid** — ~3 columns desktop, 2 tablet, 1 phone.
3. **Missing image:** **collapse to a text-only card** (no image area). Mixed card
   heights are acceptable.
4. **Non-square uploads:** **crop to square at upload time**, server-side via Intervention
   (centered cover crop).

## Design

### 1. Editor — `resources/views/admin/posts/_form.blade.php`

- Add a single **"Card image"** `<input type="file" name="card_image" accept="image/*">`
  near the top of the form, **above** the per-locale fieldsets (it is one image per post,
  shared across locales).
- On the **edit** screen, when `post.featured_image` is set, show it as a thumbnail and a
  **"Remove card image"** checkbox (`name="remove_card_image"`).
- Plain multipart form field, submitted with the post — **not** the CKEditor async
  uploader.

### 2. Controller — `app/Http/Controllers/Admin/PostController.php`

- Handle the card image in `store()` and `update()` (via a small private helper, e.g.
  `cardImageData(Request $request, ?Post $post): array` or folded into `postData()`):
  - If a `card_image` file is present:
    - Validate: `['card_image' => ['image', 'max:8192']]` (mirrors `AttachmentController`).
    - **Square-crop** with Intervention: `cover(1200, 1200)` (centered), encode JPEG
      quality 82.
    - Store under `media/<uuid>.jpg` on the `public` disk.
    - Create a `Media` row (`path`, `url`, `width`, `height`).
    - Save the **root-relative** URL to `posts.featured_image`
      (`parse_url(Storage::disk('public')->url($path), PHP_URL_PATH)`).
  - Else if `remove_card_image` is checked: set `featured_image` to `null`.
  - Else: leave `featured_image` unchanged.
- No new migration.
- Removal nulls the column; the stored file is left in place (cleanup is out of scope).

### 3. Listing view — `resources/views/blog/index.blade.php`

- Wrap the `@foreach` output in a `<div class="blog-grid">`.
- Each card links as a whole (`<a>` wrapping the card content) to
  `/{{ $locale }}/blog/{{ $t->slug }}`:
  - **If** `$post->featured_image` is set: render `.card__media` (the square image,
    `alt` = post title) on top + `.card__body` (title + excerpt) below.
  - **Else:** render the existing text-only card (title + excerpt).

### 4. Styling — `public/css/skin.css`

- `.blog-grid`: `display: grid;
  grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: ...;` → ~3/2/1
  columns across desktop/tablet/phone via `auto-fill` + `minmax`.
- `.card__media`: `aspect-ratio: 1 / 1; object-fit: cover; width: 100%;` — a perfect
  square (the top 2/3 of a 3:2 card).
- `.card__body`: holds title + excerpt; excerpt is **line-clamped** so card heights stay
  uniform.
- All values token-driven (colors/radius/shadow/spacing via existing CSS variables); the
  card with an image uses zero internal padding on the media so the image is flush to the
  card edges (rounded to match `--radius`).

### 5. Docs (required by `CLAUDE.md`)

- **`README.md`:** document that `posts.featured_image` is now used, the editor
  card-image upload field, and the square-crop-on-upload behavior.
- **`docs/theme-style-map.json`:** add the new element types (`.blog-grid`,
  `.card__media`, `.card__body`) and the tokens they consume.

## Data flow

```
Admin post form (multipart, card_image file)
  -> PostController@store/update
       -> Intervention cover(1200,1200) JPEG q82
       -> Storage public disk: media/<uuid>.jpg
       -> Media row created
       -> posts.featured_image = root-relative URL
BlogController@index (already passes $posts)
  -> blog/index.blade.php reads $post->featured_image
       -> image present:  .card__media + .card__body
       -> image absent:   text-only card
```

## Edge cases

- **No image** (incl. all existing posts): text-only card. No migration, no broken
  images.
- **Non-image / oversized upload:** rejected by validation (`image`, `max:8192`).
- **Remove image:** checkbox nulls `featured_image`; file left on disk.
- **Long excerpts:** line-clamped in `.card__body` to keep grid tidy.

## Testing (TDD)

Feature test using `Storage::fake('public')`:

1. Uploading a `card_image` on store/update saves `posts.featured_image` and writes a
   **square** (1200×1200) file to the fake disk.
2. A post **with** `featured_image` renders `.card__media` and a card-wide link.
3. A post **without** `featured_image` renders the text-only card (no `.card__media`).
4. The "remove card image" checkbox nulls `featured_image`.

## Out of scope (YAGNI)

- `blog/show.blade.php` (single-article page) is unchanged — it does not display the
  featured image.
- Deleting orphaned media files on image replace/removal.
