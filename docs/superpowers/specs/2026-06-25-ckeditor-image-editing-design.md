# CKEditor 5 Image Editing — Design Spec

**Date:** 2026-06-25
**Status:** Approved design
**Context:** Replaces the Trix editor (Plan 2) with CKEditor 5 to support image **resize** and **text-wrap/alignment** around images, which Trix cannot do.

## 1. Goal

In the admin blog editor, authors can: resize an image (drag handles), align it (left/center/right), wrap text around it (side image), add a caption, and upload inline — producing sanitized HTML that renders correctly on the public blog.

## 2. Constraints

- **Node-free:** self-host CKEditor 5's pre-built browser bundle in `public/vendor/ckeditor/`; load via `<script>`/`<link>`. No Vite/npm, no build step.
- **Server stays PHP/MySQL:** the editor is browser JS only (like Trix was). Uploads still handled by the existing PHP `AttachmentController`.
- **Free licensing:** CKEditor 5 open-source (GPL 2+); `licenseKey: 'GPL'`. No premium features.
- **Strict sanitization preserved:** richer image markup is allowed via an explicit whitelist; scripts/handlers still stripped.

## 3. Components

### 3.1 Editor assets
- Download `ckeditor5.umd.js` + `ckeditor5.css` (+ translations if needed) into `public/vendor/ckeditor/`. No external CDN at runtime.

### 3.2 Editor configuration (admin post form)
- Replace the Trix `<trix-editor>` markup with a `<textarea>` per locale, upgraded by CKEditor `ClassicEditor`.
- Plugins: `Essentials`, `Paragraph`, `Heading`, `Bold`, `Italic`, `Link`, `List`, `BlockQuote`, `Image`, `ImageToolbar`, `ImageCaption`, `ImageStyle`, `ImageResize`, `LinkImage`, `SimpleUploadAdapter`.
- Image toolbar: `imageStyle:alignLeft`, `imageStyle:alignCenter`, `imageStyle:alignRight`, `imageStyle:side`, `toggleImageCaption`, `imageTextAlternative`, `linkImage`.
- `simpleUpload`: `uploadUrl = route('admin.attachments.store')`, header `X-CSRF-TOKEN`.
- On submit, CKEditor writes HTML back into the textarea (standard ClassicEditor behavior).

### 3.3 Upload endpoint
- `AttachmentController` accepts the file under either `upload` (CKEditor's field name) or `file` (back-compat / tests); returns `{ "url": "/storage/media/<uuid>.jpg" }` (root-relative, from the existing fix). Unchanged processing (Intervention Image resize + store on `public` disk + `Media` row).

### 3.4 Sanitization — "blog" purifier profile
- A dedicated `purifier` profile `blog` that allows CKEditor image markup safely:
  - Elements: `figure`, `figcaption` (added via custom HTML definition).
  - `figure` allows `class` limited to: `image`, `image-style-align-left`, `image-style-align-right`, `image-style-align-center`, `image-style-side`, `image_resized`.
  - `img` allows `src`, `alt`, `width`, `height`, `style`; `style` restricted to the CSS `width` property (HTMLPurifier sanitizes CSS values).
  - Standard text/formatting tags (`p`, `h2`/`h3`, `strong`, `em`, `a[href]`, `ul`/`ol`/`li`, `blockquote`, `figcaption`).
- `PostController` calls `clean($body, 'blog')`. Scripts, event handlers, and unknown attributes are still removed.

### 3.5 Public rendering CSS (token-driven skin)
- Add rules so the alignment classes lay out: `.image-style-align-left{float:left;margin:…}`, `…-align-right{float:right}`, `…-align-center{display:block;margin-inline:auto}`, `.image-style-side{float:right;max-width:50%}`, `figure.image img{max-width:100%;height:auto}`, `.image_resized{max-width:100%}` (respect inline `width`). Spacing uses `var(--space-unit)`.

## 4. Migration / compatibility
- Existing Trix-authored posts are plain `<div><a><img></a></div>` HTML — render unchanged. No data migration needed.
- Trix assets (`public/vendor/trix/*`) and the Trix attachment script are removed.

## 5. Testing
- Sanitization: `clean($html,'blog')` keeps `figure.image`, `img[style=width]`, `figcaption`, allowed classes; strips `<script>` and `onerror`.
- Upload: posting `upload` field returns a relative `url`, stores file + `Media` row (existing test extended to cover `upload`).
- Admin CRUD: creating a post with image markup persists sanitized body (existing test still green).

## 6. Out of scope
- CKEditor premium features (collaboration, export, etc.).
- WYSIWYG image cropping/editing beyond resize+align.
- Migrating the editor for any non-blog field.
