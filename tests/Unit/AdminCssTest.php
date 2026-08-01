<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminCssTest extends TestCase
{
    public function test_admin_css_references_no_theme_token(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        // The admin module owns its palette (--adm-*). A var(--color-…) here
        // would make the admin's appearance a function of the active theme
        // again, which is exactly what the decoupling removed.
        foreach ([
            '--color-', '--font-base', '--font-heading', '--font-display',
            '--space-unit', '--container-width', '--nav-height', '--radius)',
            '--shadow', '--hero-overlay',
        ] as $token) {
            $this->assertStringNotContainsString('var('.$token, $css, "admin.css must not use var({$token}…)");
        }
    }

    public function test_admin_css_reskins_ckeditor_chrome_in_admin_terms(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        // The post editor no longer loads article.css (that file is
        // token-driven and belongs to the themed article), so CKEditor's own
        // UI chrome must be re-skinned here instead — in --adm-* terms.
        $this->assertMatchesRegularExpression(
            '/\.adm-editor\s*\{[^}]*--ck-color-base-background:\s*var\(--adm-[a-z-]+\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.adm-editor\s*\{[^}]*--ck-color-base-text:\s*var\(--adm-[a-z-]+\)/s',
            $css
        );
        // CKEditor resolves the derived vars at :root, so overriding only the
        // base ones would leave the toolbar in its default light palette.
        $this->assertMatchesRegularExpression(
            '/\.adm-editor\s*\{[^}]*--ck-color-toolbar-background:\s*var\(--adm-[a-z-]+\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.adm-editor\s*\{[^}]*--ck-color-dropdown-panel-background:\s*var\(--adm-[a-z-]+\)/s',
            $css
        );
    }
}
