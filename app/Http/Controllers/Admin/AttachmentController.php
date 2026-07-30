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

        if ($uploaded->getMimeType() === 'image/gif') {
            // GD (the only driver installed) can't read/write multi-frame
            // GIF at all, so routing an animated GIF through Intervention's
            // resize/encode pipeline always flattens it to one static frame.
            // Store the original bytes untouched instead — this keeps every
            // frame intact, at the cost of skipping the resize/compression
            // step (still bounded by the upload size cap validated above).
            $path = 'media/'.Str::uuid().'.gif';
            Storage::disk('public')->put($path, file_get_contents($uploaded->getRealPath()));
            [$width, $height] = getimagesize($uploaded->getRealPath());
        } else {
            $manager = new ImageManager(new Driver);
            $image = $manager->decodePath($uploaded->getRealPath());
            $image->scaleDown(width: 1600);

            // Formats that can carry an alpha channel keep it by staying PNG;
            // re-encoding those to JPEG (no alpha) flattens transparent pixels
            // to white. Everything else (plain photos) still gets JPEG for size.
            $keepsAlpha = in_array($uploaded->getMimeType(), ['image/png', 'image/webp'], true);
            $extension = $keepsAlpha ? 'png' : 'jpg';
            $encoded = $keepsAlpha
                ? $image->encodeUsingFileExtension('png')
                : $image->encodeUsingFileExtension('jpg', quality: 82);

            $path = 'media/'.Str::uuid().'.'.$extension;
            Storage::disk('public')->put($path, (string) $encoded);
            $width = $image->width();
            $height = $image->height();
        }

        // Store a root-relative URL (e.g. /storage/media/uuid.jpg) so embedded
        // images resolve on any host/port/domain — never bake in APP_URL.
        $url = parse_url(Storage::disk('public')->url($path), PHP_URL_PATH);

        $media = Media::create([
            'path' => $path,
            'url' => $url,
            'width' => $width,
            'height' => $height,
        ]);

        return response()->json(['url' => $media->url]);
    }
}
