<?php

namespace Tests\Unit;

use Tests\TestCase;

class TestimonialsCssTest extends TestCase
{
    public function test_avatar_placeholder_rule_is_removed(): void
    {
        $css = file_get_contents(public_path('css/about.css'));

        $this->assertStringNotContainsString('.about-testi__avatar', $css);
    }

    public function test_arrow_stacks_above_the_transformed_track(): void
    {
        $css = file_get_contents(public_path('css/about.css'));

        // The carousel JS applies an inline transform to .about-testi__track
        // on every slide change, which gives the track its own stacking
        // context. Because .about-testi__arrow--prev sits earlier in the DOM
        // than the track, without an explicit z-index the track's context
        // paints over the right half of the prev arrow (it overlaps the
        // viewport) and swallows hover/click there. An explicit z-index
        // above the track's implicit stacking level keeps both arrows on top
        // regardless of DOM order.
        $this->assertMatchesRegularExpression(
            '/\.about-testi__arrow\s*\{[^}]*z-index:\s*[1-9]\d*/s',
            $css
        );
    }
}
