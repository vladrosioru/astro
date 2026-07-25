<?php

namespace App\Services\Database;

/**
 * Points copied content at the source site's media.
 *
 * Uploads store root-relative URLs on purpose so they resolve on any host
 * (AttachmentController.php:38-41). That is exactly why prod content restored
 * onto dev breaks: /storage/media/x.jpg resolves against dev's docroot, and
 * the file only exists on prod's disk. Rewriting to an absolute prod URL makes
 * dev render prod's images without transferring a single file.
 */
class MediaPathRewriter
{
    private const PREFIX = '/storage/media/';

    private string $base;

    public function __construct(?string $base)
    {
        $this->base = rtrim((string) $base, '/');
    }

    /** For media.url, which holds a bare root-relative path. */
    public function rewriteUrl(?string $value): ?string
    {
        if ($value === null || $this->base === '') {
            return $value;
        }

        return str_starts_with($value, self::PREFIX) ? $this->base.$value : $value;
    }

    /** For post_translations.body, where paths sit inside quoted attributes. */
    public function rewriteBody(?string $value): ?string
    {
        if ($value === null || $this->base === '') {
            return $value;
        }

        return str_replace(
            ['"'.self::PREFIX, "'".self::PREFIX],
            ['"'.$this->base.self::PREFIX, "'".$this->base.self::PREFIX],
            $value
        );
    }
}
