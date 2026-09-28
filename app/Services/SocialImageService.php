<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class SocialImageService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Generate a 1200x630 social card for a post.
     *
     * @param  string|null  $coverImagePath  Local path or root-relative URL to cover image (or null for logo)
     * @return string The root-relative URL of the generated image (e.g. /storage/media/social-uuid.jpg)
     */
    public function generateForPost(?string $coverImagePath = null): string
    {
        $bgPath = public_path('img/social-bg-stars.jpg');
        $bg = $this->manager->decodePath($bgPath);

        $overlay = null;

        if ($coverImagePath) {
            $resolvedPath = $this->resolveLocalPath($coverImagePath);
            if ($resolvedPath && file_exists($resolvedPath)) {
                $overlay = $this->manager->decodePath($resolvedPath);
                // Cover image is square -> scale to full-height 630x630
                $overlay->cover(630, 630);
            }
        }

        if (! $overlay) {
            // Fallback: logo centered at 500x500
            $logoPath = public_path('img/logo-nav.png');
            if (file_exists($logoPath)) {
                $overlay = $this->manager->decodePath($logoPath);
                $overlay->resize(500, 500);
            }
        }

        if ($overlay) {
            $x = (int) (($bg->width() - $overlay->width()) / 2);
            $y = (int) (($bg->height() - $overlay->height()) / 2);
            $bg->insert($overlay, $x, $y);
        }

        $encoded = $bg->encodeUsingFileExtension('jpg', quality: 85);
        $path = 'media/social-'.Str::uuid().'.jpg';

        Storage::disk('public')->put($path, (string) $encoded);

        $url = parse_url(Storage::disk('public')->url($path), PHP_URL_PATH);

        Media::create([
            'path' => $path,
            'url' => $url,
            'width' => 1200,
            'height' => 630,
        ]);

        return $url;
    }

    private function resolveLocalPath(string $urlOrPath): ?string
    {
        $cleaned = ltrim(parse_url($urlOrPath, PHP_URL_PATH), '/');

        if (str_starts_with($cleaned, 'storage/')) {
            $storageRelative = substr($cleaned, strlen('storage/'));
            $fullPath = Storage::disk('public')->path($storageRelative);
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        if (Storage::disk('public')->exists($cleaned)) {
            return Storage::disk('public')->path($cleaned);
        }

        if (file_exists(public_path($cleaned))) {
            return public_path($cleaned);
        }

        if (file_exists($urlOrPath)) {
            return $urlOrPath;
        }

        return null;
    }
}
