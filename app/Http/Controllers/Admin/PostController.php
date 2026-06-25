<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

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
        return [
            'status' => $request->input('status', 'draft'),
            'published_at' => $request->input('status') === 'published' ? now() : null,
        ];
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
