<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    public function __construct(private MediaUploadService $uploads) {}

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

        $author->update($this->authorData($request, $author));

        return redirect()->route('admin.authors.index');
    }

    public function destroy(Author $author)
    {
        $this->uploads->deleteByUrl($author->picture);
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

    private function authorData(Request $request, ?Author $author = null): array
    {
        $data = [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ];

        $picture = $this->pictureUrl($request);
        if ($picture !== null) {
            $this->uploads->deleteByUrl($author?->picture);
            $data['picture'] = $picture;
        } elseif ($request->boolean('remove_picture')) {
            $this->uploads->deleteByUrl($author?->picture);
            $data['picture'] = null;
        }

        return $data;
    }

    private function pictureUrl(Request $request): ?string
    {
        $file = $request->file('picture');
        if ($file === null) {
            return null;
        }

        validator(['picture' => $file], ['picture' => ['image', 'max:8192']])->validate();

        return $this->uploads->store(file: $file, squareSize: 400, errorField: 'picture')->url;
    }
}
