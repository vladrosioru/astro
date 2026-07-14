<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;

class ThemeManager
{
    private ?array $manifestCache = null;

    private ?string $activeCache = null;

    public function active(): string
    {
        if ($this->activeCache !== null) {
            return $this->activeCache;
        }

        $name = SiteSetting::current()->theme ?: config('theme.fallback');

        if (! is_dir($this->dir($name))) {
            $fallback = config('theme.fallback');
            if ($name !== $fallback) {
                Log::warning("Theme folder missing for '{$name}', falling back to '{$fallback}'.");
            }
            $name = $fallback;
        }

        return $this->activeCache = $name;
    }

    public function manifest(): array
    {
        if ($this->manifestCache !== null) {
            return $this->manifestCache;
        }

        $path = $this->dir($this->active()).'/theme.json';
        $data = is_file($path) ? json_decode(file_get_contents($path), true) : null;

        if (! is_array($data)) {
            Log::warning("Invalid or missing theme.json for '{$this->active()}'.");
            $data = ['tokens' => [], 'assets' => ['css' => [], 'js' => []], 'views' => []];
        }

        return $this->manifestCache = $data;
    }

    /** name => value, merged defaults <- theme.json <- branding. */
    public function tokens(): array
    {
        $defaults = config('tokens.defaults');

        $themeValues = [];
        foreach ($this->manifest()['tokens'] ?? [] as $name => $def) {
            $themeValues[$name] = $def['value'] ?? null;
        }

        $branding = SiteSetting::current()->branding ?? [];

        return array_merge($defaults, array_filter($themeValues, fn ($v) => $v !== null), $branding);
    }

    /** @return array<string> ordered absolute CSS URLs, cache-busted by mtime */
    public function cssUrls(): array
    {
        return array_map(
            fn ($rel) => versioned_asset($this->rel($rel)),
            $this->manifest()['assets']['css'] ?? []
        );
    }

    /** @return array<array{url:string,defer:bool,async:bool}> */
    public function jsAssets(): array
    {
        return array_map(fn ($js) => [
            'url' => asset($this->rel($js['src'])),
            'defer' => (bool) ($js['defer'] ?? false),
            'async' => (bool) ($js['async'] ?? false),
        ], $this->manifest()['assets']['js'] ?? []);
    }

    public function viewsPath(): ?string
    {
        $path = $this->dir($this->active()).'/views';

        return is_dir($path) ? $path : null;
    }

    /** @return array<int,array{name:string,title:string,description:string,screenshot:?string,active:bool}> */
    public function available(): array
    {
        $active = $this->active();
        $out = [];

        foreach (glob(public_path(config('theme.path').'/theme_*'), GLOB_ONLYDIR) as $dir) {
            $m = json_decode(@file_get_contents($dir.'/theme.json'), true) ?: [];
            // The folder name is authoritative: it is what active() and the
            // Rule::in() allow-list compare against, so derive the name from it.
            $name = preg_replace('/^theme_/', '', basename($dir));
            $out[] = [
                'name' => $name,
                'title' => $m['title'] ?? ucfirst($name),
                'description' => $m['description'] ?? '',
                'screenshot' => isset($m['screenshot'])
                    ? asset(config('theme.path')."/theme_{$name}/".$m['screenshot'])
                    : null,
                'active' => $name === $active,
            ];
        }

        return $out;
    }

    private function dir(string $name): string
    {
        return public_path(config('theme.path').'/theme_'.$name);
    }

    private function rel(string $assetRelativeToTheme): string
    {
        return config('theme.path').'/theme_'.$this->active().'/'.ltrim($assetRelativeToTheme, '/');
    }
}
