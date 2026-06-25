<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AttachmentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['file' => ['required', 'image', 'max:8192']]);

        $manager = new ImageManager(new Driver());
        $image = $manager->decodePath($request->file('file')->getRealPath());
        $image->scaleDown(width: 1600);

        $path = 'media/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($path, (string) $image->encodeUsingFileExtension('jpg', quality: 82));

        $media = Media::create([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'width' => $image->width(),
            'height' => $image->height(),
        ]);

        return response()->json(['url' => $media->url]);
    }
}
