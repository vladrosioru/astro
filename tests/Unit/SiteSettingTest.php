<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_creates_and_reuses_a_single_row(): void
    {
        $first = SiteSetting::current();
        $this->assertSame(1, $first->id);
        $this->assertSame(1, SiteSetting::count());

        SiteSetting::current();
        $this->assertSame(1, SiteSetting::count());
    }

    public function test_defaults_enable_sections_and_set_locales(): void
    {
        $setting = SiteSetting::current();
        $this->assertTrue($setting->sectionVisible('blog'));
        $this->assertTrue($setting->sectionVisible('about'));
        $this->assertSame('en', $setting->locales['default']);
        $this->assertSame(['en', 'ro'], $setting->locales['supported']);
    }

    public function test_section_can_be_disabled(): void
    {
        $setting = SiteSetting::current();
        $setting->update(['sections' => ['blog' => false] + $setting->sections]);
        $this->assertFalse($setting->fresh()->sectionVisible('blog'));
    }

    public function test_current_has_default_theme_pointer(): void
    {
        $this->assertSame('solarsystem', \App\Models\SiteSetting::current()->theme);
    }
}
