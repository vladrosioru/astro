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
        $uploaded = $request->file('upload') ?? $request->file('file');
        abort_if($uploaded === null, 422, 'No file provided.');
        validator(['file' => $uploaded], ['file' => ['required', 'image', 'max:8192']])->validate();

        $manager = new ImageManager(new Driver());
        $image = $manager->decodePath($uploaded->getRealPath());
        $image->scaleDown(width: 1600);

        $path = 'media/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($path, (string) $image->encodeUsingFileExtension('jpg', quality: 82));

        // Store a root-relative URL (e.g. /storage/media/uuid.jpg) so embedded
        // images resolve on any host/port/domain — never bake in APP_URL.
        $url = parse_url(Storage::disk('public')->url($path), PHP_URL_PATH);

        $media = Media::create([
            'path' => $path,
            'url' => $url,
            'width' => $image->width(),
            'height' => $image->height(),
        ]);

        return response()->json(['url' => $media->url]);
    }
}
