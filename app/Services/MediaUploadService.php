<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class MediaUploadService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Store an uploaded image on the public disk and record a Media row.
     *
     * Animated GIFs bypass Intervention GD so all frames remain intact.
     */
    public function store(
        UploadedFile $file,
        ?int $squareSize = null,
        ?int $maxWidth = null,
        string $errorField = 'file',
        bool $preserveGif = false,
    ): Media {
        if ($preserveGif && $file->getMimeType() === 'image/gif') {
            $dimensions = @getimagesize($file->getRealPath());
            if ($dimensions === false) {
                throw ValidationException::withMessages([
                    $errorField => 'The uploaded GIF image is invalid or unreadable.',
                ]);
            }

            $path = 'media/'.Str::uuid().'.gif';
            Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));
            [$width, $height] = $dimensions;
        } else {
            $image = $this->manager->decodePath($file->getRealPath());

            if ($squareSize !== null) {
                $image->cover($squareSize, $squareSize);
            } elseif ($maxWidth !== null) {
                $image->scaleDown(width: $maxWidth);
            }

            $keepsAlpha = in_array($file->getMimeType(), ['image/png', 'image/webp', 'image/gif'], true);
            $extension = $keepsAlpha ? 'png' : 'jpg';
            $encoded = $keepsAlpha
                ? $image->encodeUsingFileExtension('png')
                : $image->encodeUsingFileExtension('jpg', quality: 82);

            $path = 'media/'.Str::uuid().'.'.$extension;
            Storage::disk('public')->put($path, (string) $encoded);
            $width = $image->width();
            $height = $image->height();
        }

        $url = parse_url(Storage::disk('public')->url($path), PHP_URL_PATH);

        return Media::create([
            'path' => $path,
            'url' => $url,
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Remove a local `/storage/media/...` file and its Media record if present.
     */
    public function deleteByUrl(?string $url): void
    {
        if (! is_string($url) || ! str_starts_with($url, '/storage/media/')) {
            return;
        }

        $relativePath = substr($url, strlen('/storage/'));
        Storage::disk('public')->delete($relativePath);
        Media::where('path', $relativePath)->delete();
    }
}
