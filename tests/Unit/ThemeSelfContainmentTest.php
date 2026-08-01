<?php

namespace Tests\Unit;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class ThemeSelfContainmentTest extends TestCase
{
    /**
     * A theme is a self-contained folder: the font binaries it needs ship
     * inside its own package. Two themes wanting the same face each keep a
     * copy — duplication is the price of packages that can be dropped in and
     * removed whole. Stray fonts under public/ have no owner: nothing tells
     * you which theme still needs them, so they survive every cleanup.
     */
    public function test_shipped_font_files_live_inside_a_theme_package(): void
    {
        $strays = [];

        foreach ($this->publicFiles() as $relative) {
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

            if (! in_array($extension, ['woff2', 'woff', 'ttf', 'otf', 'eot'], true)) {
                continue;
            }

            if (! preg_match('#^themes/theme_[a-z0-9_-]+/#', $relative)) {
                $strays[] = $relative;
            }
        }

        $this->assertSame([], $strays, 'Font files must live inside public/themes/theme_<name>/, not loose under public/.');
    }

    public function test_no_app_level_fonts_directory(): void
    {
        $this->assertDirectoryDoesNotExist(public_path('fonts'));
    }

    /**
     * Every file under public/ as a forward-slashed relative path. Skips
     * `storage` (a symlink to user uploads, not shipped code) and `vendor`
     * (self-hosted third-party bundles, whose layout isn't ours to dictate).
     *
     * @return list<string>
     */
    private function publicFiles(): array
    {
        $base = str_replace('\\', '/', public_path());

        $directories = new RecursiveDirectoryIterator(
            public_path(),
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
        );

        $filtered = new RecursiveCallbackFilterIterator(
            $directories,
            function (SplFileInfo $file) use ($base) {
                $relative = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($base)), '/');

                return ! in_array($relative, ['storage', 'vendor'], true);
            }
        );

        $files = [];

        foreach (new RecursiveIteratorIterator($filtered) as $file) {
            $files[] = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($base)), '/');
        }

        return $files;
    }
}
