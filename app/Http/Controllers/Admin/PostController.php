<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Services\MediaUploadService;
use App\Services\SocialImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    private array $locales = ['en', 'ro'];

    public function __construct(
        private MediaUploadService $uploads,
        private SocialImageService $socialImages,
    ) {}

    public function index()
    {
        return view('admin.posts.index', [
            'posts' => Post::with(['translations', 'author'])->latest()->paginate(25),
            'draftCount' => Post::where('status', 'draft')->count(),
        ]);
    }

    public function create()
    {
        return view('admin.posts.create', ['authors' => Author::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $request->validate($this->dateValidationRules());

        $post = Post::create($this->postData($request));
        $this->saveTranslations($post, $request);

        return redirect()->route('admin.posts.index');
    }

    public function edit(Post $post)
    {
        return view('admin.posts.edit', [
            'post' => $post->load('translations'),
            'authors' => Author::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Post $post)
    {
        $request->validate($this->dateValidationRules());

        $post->update($this->postData($request, $post));
        $this->saveTranslations($post, $request);

        return redirect()->route('admin.posts.index');
    }

    private function dateValidationRules(): array
    {
        return [
            'status' => ['required', Rule::in(['draft', 'published'])],
            'author_id' => ['nullable', 'integer', 'exists:authors,id'],
            'published_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2026-01-01', 'before_or_equal:today'],
            'reading_time' => ['nullable', 'integer', 'min:1', 'max:99'],
            'en_title' => ['required_without:ro_title', 'nullable', 'string', 'max:255'],
            'ro_title' => ['required_without:en_title', 'nullable', 'string', 'max:255'],
            'en_seo_title' => ['nullable', 'string', 'max:255'],
            'ro_seo_title' => ['nullable', 'string', 'max:255'],
            'en_seo_description' => ['nullable', 'string', 'max:500'],
            'ro_seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function destroy(Post $post)
    {
        $this->uploads->deleteByUrl($post->featured_image);
        $this->uploads->deleteByUrl($post->social_image);
        $post->delete();

        return redirect()->route('admin.posts.index');
    }

    private function postData(Request $request, ?Post $post = null): array
    {
        $status = $request->input('status', 'draft');
        $data = [
            'status' => $status,
            'published_at' => $this->publishedAtFor($status, $request, $post),
            // Unlike published_at (which resets to null when unpublished),
            // this is set once and never cleared again — it's the permanent
            // "has this post ever gone live" flag that locks slug regeneration.
            'first_published_at' => $post?->first_published_at ?? ($status === 'published' ? now() : null),
            'author_id' => $request->input('author_id') ?: null,
            'reading_time' => $request->input('reading_time') ?: null,
        ];

        $cardImage = $this->cardImageUrl($request);
        $featuredImage = $post?->featured_image;
        $coverChanged = false;

        if ($cardImage !== null) {
            $this->uploads->deleteByUrl($post?->featured_image);
            $data['featured_image'] = $cardImage;
            $featuredImage = $cardImage;
            $coverChanged = true;
        } elseif ($request->boolean('remove_card_image')) {
            $this->uploads->deleteByUrl($post?->featured_image);
            $data['featured_image'] = null;
            $featuredImage = null;
            $coverChanged = true;
        }

        if ($post === null || $coverChanged || empty($post->social_image)) {
            $this->uploads->deleteByUrl($post?->social_image);
            $data['social_image'] = $this->socialImages->generateForPost($featuredImage);
        }

        return $data;
    }

    // The Date field lets an editor set/override published_at directly. Once
    // a post is published, that date is locked (red, readonly in the UI) and
    // the server enforces the same lock: only an explicit "unlock_date" flag
    // lets a re-save change it. Draft/never-published posts stay fully
    // editable. With no explicit date supplied, first publish still
    // auto-stamps "now" — the pre-existing default behavior.
    private function publishedAtFor(string $status, Request $request, ?Post $post): ?Carbon
    {
        if ($status !== 'published') {
            return null;
        }

        $wasPublished = $post?->status === 'published';
        $unlocked = ! $wasPublished || $request->boolean('unlock_date');

        if (! $unlocked) {
            return $post->published_at;
        }

        $submittedDate = $request->input('published_date');

        return $submittedDate ? Carbon::parse($submittedDate)->startOfDay() : ($post?->published_at ?? now());
    }

    private function cardImageUrl(Request $request): ?string
    {
        $file = $request->file('card_image');
        if ($file === null) {
            return null;
        }

        validator(['card_image' => $file], ['card_image' => ['image', 'max:8192']])->validate();

        return $this->uploads->store(file: $file, squareSize: 1200, errorField: 'card_image')->url;
    }

    private function saveTranslations(Post $post, Request $request): void
    {
        // Slugs are never trusted from the request: they're auto-derived
        // from the title and, once a translation exists, kept untouched
        // unless the caller both requests regeneration and the post has
        // never been published (enforced here, not just hidden in the UI).
        $canRegenerate = is_null($post->first_published_at) && $post->status !== 'published';

        foreach ($this->locales as $locale) {
            $title = $request->input("{$locale}_title");
            if (! $title) {
                continue;
            }

            $existing = $post->translations()->where('locale', $locale)->first();
            $regenerate = $canRegenerate && $request->boolean("{$locale}_regenerate_slug");

            $slug = ($existing && ! $regenerate)
                ? $existing->slug
                : PostTranslation::uniqueSlug($title, $locale, $existing?->id);

            $post->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => $title,
                    'slug' => $slug,
                    'subtitle' => $request->input("{$locale}_subtitle"),
                    'body' => clean($request->input("{$locale}_body", ''), 'blog'),
                    'seo_title' => $request->input("{$locale}_seo_title"),
                    'seo_description' => $request->input("{$locale}_seo_description"),
                ],
            );
        }
    }
}
