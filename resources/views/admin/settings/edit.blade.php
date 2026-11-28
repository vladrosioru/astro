@extends('layouts.admin')

@section('title', 'Site Settings')

@section('content')
    @include('admin.partials._topbar')

    <main class="adm-main">
        <div class="adm-head">
            <h2 class="adm-head__title">Site Settings</h2>
            <span class="adm-head__grow"></span>
        </div>

        @if (session('status'))
            <p class="adm-note" style="margin-bottom:14px">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div class="adm-err">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" class="adm-stack">
            @csrf
            @method('PATCH')

            <section class="adm-panel">
                <div class="adm-panel__head">
                    <h3>Contact Details</h3>
                    <span class="adm-panel__grow"></span>
                    <span class="adm-note">Shown on Contact page; Email also receives Contact form submissions.</span>
                </div>
                <div class="adm-panel__body">
                    <div class="adm-field">
                        <label for="contact-email">Contact Email</label>
                        <input id="contact-email" type="email" name="contact[email]" value="{{ old('contact.email', $contact['email']) }}" placeholder="office@astrotherapia.com">
                    </div>
                    <div class="adm-field">
                        <label for="contact-phone">Phone</label>
                        <input id="contact-phone" type="text" name="contact[phone]" value="{{ old('contact.phone', $contact['phone']) }}">
                    </div>
                    <div class="adm-field">
                        <label for="contact-address">Address (optional)</label>
                        <input id="contact-address" type="text" name="contact[address]" value="{{ old('contact.address', $contact['address']) }}">
                    </div>
                </div>
            </section>

            <section class="adm-panel">
                <div class="adm-panel__head">
                    <h3>Visible Sections</h3>
                    <span class="adm-panel__grow"></span>
                    <span class="adm-note">Unchecked pages are hidden from nav/footer and return 404.</span>
                </div>
                <div class="adm-panel__body">
                    @foreach (['about' => 'About', 'blog' => 'Journal (Blog)', 'services' => 'Services', 'contact' => 'Contact'] as $key => $label)
                        <div class="adm-field">
                            <label>
                                <input type="checkbox" name="sections[{{ $key }}]" value="1" @checked(old('sections.'.$key, $setting->sectionVisible($key)))>
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="adm-panel">
                <div class="adm-panel__head">
                    <h3>Home Hero</h3>
                </div>
                <div class="adm-panel__body">
                    <div class="adm-field">
                        <label for="hero-eyebrow">Brand Eyebrow</label>
                        <input id="hero-eyebrow" type="text" name="hero[eyebrow]" value="{{ old('hero.eyebrow', $hero['eyebrow']) }}">
                    </div>
                    <div class="adm-field">
                        <label for="hero-headline">Headline</label>
                        <input id="hero-headline" type="text" name="hero[headline]" value="{{ old('hero.headline', $hero['headline']) }}">
                    </div>
                    <div class="adm-field">
                        <label for="hero-subhead">Subhead</label>
                        <textarea id="hero-subhead" name="hero[subhead]" rows="3">{{ old('hero.subhead', $hero['subhead']) }}</textarea>
                    </div>
                    <div class="adm-field">
                        <label for="hero-cta-label">Primary CTA Label (Default Theme)</label>
                        <input id="hero-cta-label" type="text" name="hero[cta_label]" value="{{ old('hero.cta_label', $hero['cta_label']) }}">
                    </div>
                    <div class="adm-field">
                        <label for="hero-cta-url">Primary CTA URL</label>
                        <input id="hero-cta-url" type="text" name="hero[cta_url]" value="{{ old('hero.cta_url', $hero['cta_url']) }}">
                    </div>
                    <div class="adm-field">
                        <label for="hero-cta2-label">Secondary CTA Label (Default Theme)</label>
                        <input id="hero-cta2-label" type="text" name="hero[cta2_label]" value="{{ old('hero.cta2_label', $hero['cta2_label']) }}">
                    </div>
                    <div class="adm-field">
                        <label for="hero-cta2-url">Secondary CTA URL</label>
                        <input id="hero-cta2-url" type="text" name="hero[cta2_url]" value="{{ old('hero.cta2_url', $hero['cta2_url']) }}">
                    </div>
                </div>
            </section>

            <section class="adm-panel">
                <div class="adm-panel__head">
                    <h3>About Page Content</h3>
                    <span class="adm-panel__grow"></span>
                    <span class="adm-note">Separate body paragraphs with a blank line.</span>
                </div>
                <div class="adm-panel__body">
                    <div class="adm-field">
                        <label for="about-manifesto-eyebrow">Manifesto Eyebrow</label>
                        <input id="about-manifesto-eyebrow" type="text" name="about[manifesto_eyebrow]" value="{{ old('about.manifesto_eyebrow', $about['manifesto_eyebrow']) }}">
                    </div>
                    <div class="adm-field">
                        <label for="about-manifesto-title">Manifesto Headline</label>
                        <input id="about-manifesto-title" type="text" name="about[manifesto_title]" value="{{ old('about.manifesto_title', $about['manifesto_title']) }}">
                    </div>
                    <div class="adm-field">
                        <label for="about-manifesto-subhead">Manifesto Subhead</label>
                        <textarea id="about-manifesto-subhead" name="about[manifesto_subhead]" rows="3">{{ old('about.manifesto_subhead', $about['manifesto_subhead']) }}</textarea>
                    </div>
                    <div class="adm-field">
                        <label for="about-manifesto-body">Manifesto Paragraphs</label>
                        <textarea id="about-manifesto-body" name="about[manifesto_body]" rows="16">{{ old('about.manifesto_body', $about['manifesto_body']) }}</textarea>
                    </div>

                    <div class="adm-field">
                        <label for="about-core-eyebrow">"Your Core" Eyebrow</label>
                        <input id="about-core-eyebrow" type="text" name="about[core_eyebrow]" value="{{ old('about.core_eyebrow', $about['core_eyebrow']) }}">
                    </div>
                    <div class="adm-field">
                        <label for="about-core-title">"Your Core" Heading</label>
                        <input id="about-core-title" type="text" name="about[core_title]" value="{{ old('about.core_title', $about['core_title']) }}">
                    </div>
                    <div class="adm-field">
                        <label for="about-core-body">"Your Core" Paragraphs</label>
                        <textarea id="about-core-body" name="about[core_body]" rows="8">{{ old('about.core_body', $about['core_body']) }}</textarea>
                    </div>

                    <div class="adm-field">
                        <label>Frequently Asked Questions</label>
                        <div class="adm-stack" id="about-faqs-list">
                            @php($faqItems = old('about.faqs', $about['faqs']))
                            @foreach ($faqItems as $i => $faq)
                                <div class="adm-panel" data-faq-row>
                                    <div class="adm-panel__body">
                                        <div class="adm-field">
                                            <label>Question #{{ $i + 1 }}</label>
                                            <input type="text" name="about[faqs][{{ $i }}][q]" value="{{ $faq['q'] ?? '' }}">
                                        </div>
                                        <div class="adm-field">
                                            <label>Answer #{{ $i + 1 }}</label>
                                            <textarea name="about[faqs][{{ $i }}][a]" rows="3">{{ $faq['a'] ?? '' }}</textarea>
                                        </div>
                                        <button type="button" class="adm-btn adm-btn--sm" data-faq-remove>Remove FAQ</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div style="margin-top:10px">
                            <button type="button" class="adm-btn adm-btn--sm" id="about-faq-add">+ Add FAQ</button>
                        </div>
                    </div>
                </div>
            </section>

            <div class="adm-actions">
                <button type="submit" class="adm-btn adm-btn--primary">Save Settings</button>
            </div>
        </form>
    </main>

    <script>
    (function () {
        var list = document.getElementById('about-faqs-list');
        var addBtn = document.getElementById('about-faq-add');
        if (!list || !addBtn) return;

        list.addEventListener('click', function (e) {
            if (e.target && e.target.hasAttribute('data-faq-remove')) {
                var row = e.target.closest('[data-faq-row]');
                if (row) row.remove();
            }
        });

        addBtn.addEventListener('click', function () {
            var idx = list.querySelectorAll('[data-faq-row]').length;
            var card = document.createElement('div');
            card.className = 'adm-panel';
            card.setAttribute('data-faq-row', '');
            card.innerHTML =
                '<div class="adm-panel__body">' +
                    '<div class="adm-field"><label>Question</label><input type="text" name="about[faqs][' + idx + '][q]" value=""></div>' +
                    '<div class="adm-field"><label>Answer</label><textarea name="about[faqs][' + idx + '][a]" rows="3"></textarea></div>' +
                    '<button type="button" class="adm-btn adm-btn--sm" data-faq-remove>Remove FAQ</button>' +
                '</div>';
            list.appendChild(card);
        });
    })();
    </script>
@endsection
