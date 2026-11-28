<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $setting = SiteSetting::current();

        return view('admin.settings.edit', [
            'setting' => $setting,
            'contact' => array_merge(['email' => '', 'phone' => '', 'address' => ''], $setting->contact ?? []),
            'hero' => array_merge(SiteSetting::heroDefaults(), $setting->hero ?? []),
            'about' => $setting->aboutContent(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact.email' => ['nullable', 'email', 'max:190'],
            'contact.phone' => ['nullable', 'string', 'max:40'],
            'contact.address' => ['nullable', 'string', 'max:255'],
            'hero.eyebrow' => ['nullable', 'string', 'max:100'],
            'hero.headline' => ['nullable', 'string', 'max:255'],
            'hero.subhead' => ['nullable', 'string', 'max:1000'],
            'hero.cta_label' => ['nullable', 'string', 'max:80'],
            'hero.cta_url' => ['nullable', 'string', 'max:255'],
            'hero.cta2_label' => ['nullable', 'string', 'max:80'],
            'hero.cta2_url' => ['nullable', 'string', 'max:255'],
            'about.manifesto_eyebrow' => ['nullable', 'string', 'max:100'],
            'about.manifesto_title' => ['nullable', 'string', 'max:255'],
            'about.manifesto_subhead' => ['nullable', 'string', 'max:1000'],
            'about.manifesto_body' => ['nullable', 'string', 'max:20000'],
            'about.core_eyebrow' => ['nullable', 'string', 'max:100'],
            'about.core_title' => ['nullable', 'string', 'max:255'],
            'about.core_body' => ['nullable', 'string', 'max:10000'],
            'about.faqs' => ['nullable', 'array'],
            'about.faqs.*.q' => ['nullable', 'string', 'max:300'],
            'about.faqs.*.a' => ['nullable', 'string', 'max:2000'],
        ]);

        $setting = SiteSetting::current();

        $sections = [
            'about' => $request->boolean('sections.about'),
            'blog' => $request->boolean('sections.blog'),
            'services' => $request->boolean('sections.services'),
            'contact' => $request->boolean('sections.contact'),
        ];

        $contact = [
            'email' => trim((string) ($data['contact']['email'] ?? '')),
            'phone' => trim((string) ($data['contact']['phone'] ?? '')),
            'address' => trim((string) ($data['contact']['address'] ?? '')),
        ];

        $hero = array_merge($setting->hero ?? [], array_filter(
            $data['hero'] ?? [],
            fn ($v) => is_string($v) && trim($v) !== ''
        ));

        $rawAbout = $data['about'] ?? [];
        $faqs = array_values(array_filter(
            array_map(fn ($row) => [
                'q' => trim((string) ($row['q'] ?? '')),
                'a' => trim((string) ($row['a'] ?? '')),
            ], is_array($rawAbout['faqs'] ?? null) ? $rawAbout['faqs'] : []),
            fn ($row) => $row['q'] !== '' && $row['a'] !== ''
        ));

        $about = [
            'manifesto_eyebrow' => trim((string) ($rawAbout['manifesto_eyebrow'] ?? '')),
            'manifesto_title' => trim((string) ($rawAbout['manifesto_title'] ?? '')),
            'manifesto_subhead' => trim((string) ($rawAbout['manifesto_subhead'] ?? '')),
            'manifesto_body' => trim((string) ($rawAbout['manifesto_body'] ?? '')),
            'core_eyebrow' => trim((string) ($rawAbout['core_eyebrow'] ?? '')),
            'core_title' => trim((string) ($rawAbout['core_title'] ?? '')),
            'core_body' => trim((string) ($rawAbout['core_body'] ?? '')),
            'faqs' => $faqs,
        ];

        $setting->update([
            'sections' => $sections,
            'contact' => $contact,
            'hero' => $hero,
            'about' => $about,
        ]);

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
