<?php

namespace Tests\Unit;

use Tests\TestCase;

class ArticleCssTest extends TestCase
{
    public function test_article_paper_reskins_ckeditor_chrome_with_theme_tokens(): void
    {
        $css = file_get_contents(public_path('css/article.css'));

        // CKEditor's toolbar/dropdowns/balloons all cascade from these
        // --ck-color-base-* custom properties. Overriding them on
        // .article-paper (the element wrapping CKEditor's UI in the admin
        // editor) re-skins the whole panel, not just the text, to match the
        // published page — and is inert on the public page, which has no
        // CKEditor UI to inherit these vars.
        $this->assertMatchesRegularExpression(
            '/\.article-paper\s*\{[^}]*--ck-color-base-background:\s*var\(--color-[a-z-]+\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.article-paper\s*\{[^}]*--ck-color-base-foreground:\s*var\(--color-[a-z-]+\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.article-paper\s*\{[^}]*--ck-color-base-text:\s*var\(--color-[a-z-]+\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.article-paper\s*\{[^}]*--ck-color-base-border:[^;]*var\(--color-[a-z-]+\)/s',
            $css
        );

        // CKEditor derives e.g. --ck-color-toolbar-background from the
        // --ck-color-base-* vars only once, at :root — overriding just the
        // base vars on .article-paper has no effect on the toolbar itself,
        // since var() references inside a custom property resolve at the
        // element where they're *declared* (root), then inherit as an
        // already-frozen value. The derived tokens actually painting the
        // toolbar/dropdowns must be redeclared directly here too.
        $this->assertMatchesRegularExpression(
            '/\.article-paper\s*\{[^}]*--ck-color-toolbar-background:\s*var\(--color-[a-z-]+\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.article-paper\s*\{[^}]*--ck-color-dropdown-panel-background:\s*var\(--color-[a-z-]+\)/s',
            $css
        );
    }

    public function test_article_image_sets_height_auto_so_html_dimensions_do_not_stretch_image(): void
    {
        $css = file_get_contents(public_path('css/article.css'));

        $this->assertMatchesRegularExpression(
            '/\.article-image\s+img\s*\{[^}]*height:\s*auto/s',
            $css
        );
    }

    public function test_article_css_uses_font_base_token_instead_of_system_sans_fallback(): void
    {
        $css = (string) file_get_contents(public_path('css/article.css'));

        $this->assertStringNotContainsString('Helvetica', $css);
        $this->assertMatchesRegularExpression('/\.article-paper\s+\.ck-content\s*\{[^}]*font-family:\s*var\(--font-base\)/s', $css);
    }
}
