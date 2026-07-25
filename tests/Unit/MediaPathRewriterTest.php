<?php

namespace Tests\Unit;

use App\Services\Database\MediaPathRewriter;
use Tests\TestCase;

class MediaPathRewriterTest extends TestCase
{
    public function test_it_prefixes_a_bare_media_url(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com');

        $this->assertSame(
            'https://example.com/storage/media/abc.jpg',
            $rewriter->rewriteUrl('/storage/media/abc.jpg'),
        );
    }

    public function test_it_strips_a_trailing_slash_from_the_base(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com/');

        $this->assertSame(
            'https://example.com/storage/media/abc.jpg',
            $rewriter->rewriteUrl('/storage/media/abc.jpg'),
        );
    }

    public function test_it_leaves_unrelated_urls_alone(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com');

        $this->assertSame('/en/journal', $rewriter->rewriteUrl('/en/journal'));
        $this->assertSame('https://other.test/x.jpg', $rewriter->rewriteUrl('https://other.test/x.jpg'));
    }

    public function test_it_rewrites_quoted_attributes_in_a_body(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com');

        $this->assertSame(
            '<img src="https://example.com/storage/media/a.jpg"><img src=\'https://example.com/storage/media/b.jpg\'>',
            $rewriter->rewriteBody('<img src="/storage/media/a.jpg"><img src=\'/storage/media/b.jpg\'>'),
        );
    }

    public function test_it_leaves_other_links_in_a_body_alone(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com');
        $body = '<a href="/en/journal">Read</a>';

        $this->assertSame($body, $rewriter->rewriteBody($body));
    }

    public function test_it_is_a_no_op_without_a_base_url(): void
    {
        foreach ([null, ''] as $base) {
            $rewriter = new MediaPathRewriter($base);

            $this->assertSame('/storage/media/a.jpg', $rewriter->rewriteUrl('/storage/media/a.jpg'));
            $this->assertSame('<img src="/storage/media/a.jpg">', $rewriter->rewriteBody('<img src="/storage/media/a.jpg">'));
        }
    }

    public function test_it_handles_nulls(): void
    {
        $rewriter = new MediaPathRewriter('https://example.com');

        $this->assertNull($rewriter->rewriteUrl(null));
        $this->assertNull($rewriter->rewriteBody(null));
    }
}
