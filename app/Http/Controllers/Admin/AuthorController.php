<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AuthorController extends Controller
{
    public function index()
    {
        return view('admin.authors.index', [
            'authors' => Author::withCount('posts')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.authors.create');
    }

    public function store(Request $request)
    {
        $request->validate($this->validationRules());

        Author::create($this->authorData($request));

        return redirect()->route('admin.authors.index');
    }

    public function edit(Author $author)
    {
        return view('admin.authors.edit', ['author' => $author]);
    }

    public function update(Request $request, Author $author)
    {
        $request->validate($this->validationRules());

        $author->update($this->authorData($request));

        return redirect()->route('admin.authors.index');
    }

    public function destroy(Author $author)
    {
        $author->delete();

        return redirect()->route('admin.authors.index');
    }

    private function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    private function authorData(Request $request): array
    {
        $data = [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ];

        $picture = $this->pictureUrl($request);
        if ($picture !== null) {
            $data['picture'] = $picture;
        } elseif ($request->boolean('remove_picture')) {
            $data['picture'] = null;
        }

        return $data;
    }

    // Upload an author picture: validate, square-crop (centered cover) to
    // 400x400, store on the public disk, record a Media row, and return the
    // root-relative URL. Returns null when no file was submitted.
    private function pictureUrl(Request $request): ?string
    {
        $file = $request->file('picture');
        if ($file === null) {
            return null;
        }

        validator(['picture' => $file], ['picture' => ['image', 'max:8192']])->validate();

        $manager = new ImageManager(new Driver);
        $image = $manager->decodePath($file->getRealPath());
        $image->cover(400, 400);

        $keepsAlpha = in_array($file->getMimeType(), ['image/png', 'image/webp', 'image/gif'], true);
        $extension = $keepsAlpha ? 'png' : 'jpg';
        $encoded = $keepsAlpha
            ? $image->encodeUsingFileExtension('png')
            : $image->encodeUsingFileExtension('jpg', quality: 82);

        $path = 'media/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, (string) $encoded);

        $url = parse_url(Storage::disk('public')->url($path), PHP_URL_PATH);

        Media::create([
            'path' => $path,
            'url' => $url,
            'width' => $image->width(),
            'height' => $image->height(),
        ]);

        return $url;
    }
}
