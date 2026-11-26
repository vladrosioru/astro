<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function __construct(private MediaUploadService $uploads) {}

    public function store(Request $request)
    {
        $uploaded = $request->file('upload') ?? $request->file('file');
        abort_if($uploaded === null, 422, 'No file provided.');
        validator(['file' => $uploaded], ['file' => ['required', 'image', 'max:8192']])->validate();

        $media = $this->uploads->store(
            file: $uploaded,
            maxWidth: 1600,
            errorField: 'file',
            preserveGif: true,
        );

        return response()->json(['url' => $media->url]);
    }
}
