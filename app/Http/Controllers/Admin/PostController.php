<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PostController extends Controller
{
    private array $locales = ['en', 'ro'];

    public function index()
    {
        return view('admin.posts.index', ['posts' => Post::with('translations')->latest()->get()]);
    }

    public function create()
    {
        return view('admin.posts.create');
    }

    public function store(Request $request)
    {
        $post = Post::create($this->postData($request));
        $this->saveTranslations($post, $request);

        return redirect()->route('admin.posts.index');
    }

    public function edit(Post $post)
    {
        return view('admin.posts.edit', ['post' => $post->load('translations')]);
    }

    public function update(Request $request, Post $post)
    {
        $post->update($this->postData($request));
        $this->saveTranslations($post, $request);

        return redirect()->route('admin.posts.index');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()->route('admin.posts.index');
    }

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

    private function saveTranslations(Post $post, Request $request): void
    {
        foreach ($this->locales as $locale) {
            $title = $request->input("{$locale}_title");
            if (! $title) {
                continue;
            }
            $post->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => $title,
                    'slug' => $request->input("{$locale}_slug"),
                    'excerpt' => $request->input("{$locale}_excerpt"),
                    'body' => clean($request->input("{$locale}_body", ''), 'blog'),
                    'seo_title' => $request->input("{$locale}_seo_title"),
                    'seo_description' => $request->input("{$locale}_seo_description"),
                ],
            );
        }
    }
}
