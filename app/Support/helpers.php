<?php

if (! function_exists('versioned_asset')) {
    /**
     * asset() with a `?v=` query string tied to the file's mtime, so the URL
     * itself changes whenever the file's content does. The host serves
     * static assets with a long Cache-Control max-age, so without this a
     * browser that already cached the old file at the same URL won't
     * re-fetch it until that cache expires.
     */
    function versioned_asset(string $path): string
    {
        $full = public_path($path);
        $version = is_file($full) ? filemtime($full) : time();

        return asset($path).'?v='.$version;
    }
}
