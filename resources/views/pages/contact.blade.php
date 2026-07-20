@extends('layouts.app')

@section('title', 'Contact — ' . config('app.name'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/contact.css') }}">
@endpush

@section('content')
<main class="contact">

    {{-- Title band --------------------------------------------------------- --}}
    <header class="contact-hero">
        <div class="contact-shell">
            <h1 class="contact-hero__title">Contact</h1>
            <p class="contact-hero__sub">Questions about a reading, a session, or the site itself — send a note
                and I'll get back to you.</p>
        </div>
    </header>

    {{-- Info + form ---------------------------------------------------------- --}}
    <section class="contact-section">
        <div class="contact-shell contact-grid">

            <div class="contact-info">
                <p class="contact-eyebrow">Get In Touch</p>
                <h2 class="contact-h2">Reach Out</h2>
                <p class="contact-lede">Prefer e-mail or a quick message? Either works — or find us on social.</p>

                <dl class="contact-details">
                    @if (!empty($contact['email']))
                        <div class="contact-details__row">
                            <dt>Email</dt>
                            <dd><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></dd>
                        </div>
                    @endif
                    @if (!empty($contact['phone']))
                        <div class="contact-details__row">
                            <dt>Phone</dt>
                            <dd><a href="tel:{{ $contact['phone'] }}">{{ $contact['phone'] }}</a></dd>
                        </div>
                    @endif
                </dl>

                {{-- No physical address is published; Facebook/Instagram stand in for it. --}}
                <div class="contact-social">
                    <a class="contact-social__link" href="https://www.facebook.com/astrotherapia.ro" aria-label="Facebook" target="_blank" rel="noopener noreferrer" data-fb-page="astrotherapia.ro">
                        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M13.5 21v-8.5h2.9l.4-3.4h-3.3V7c0-.9.3-1.6 1.7-1.6h1.8V2.3C16.7 2.2 15.6 2 14.3 2c-2.7 0-4.6 1.7-4.6 4.7v2.4H6.8v3.4h2.9V21h3.8z"/></svg>
                    </a>
                    <a class="contact-social__link" href="https://www.instagram.com/astrotherapia/" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>
                    </a>
                </div>
            </div>

            <div class="contact-form-wrap">
                @if (session('contact_status') === 'sent')
                    <p class="contact-alert contact-alert--success" role="status">Thanks — your message is on its
                        way. I'll reply as soon as I can.</p>
                @else
                @if ($errors->any())
                    <p class="contact-alert contact-alert--error" role="alert">{{ $errors->first() }}</p>
                @endif

                <form class="contact-form" method="POST" action="/{{ app()->getLocale() }}/contact" novalidate>
                    @csrf
                    {{-- Render-timestamp trap: submissions faster than a human could
                         type are treated as spam server-side. --}}
                    <input type="hidden" name="rendered_at" value="{{ time() }}">

                    {{-- Honeypot: invisible to real visitors, reliably filled by bots. --}}
                    <div class="contact-hp" aria-hidden="true">
                        <label for="website">Leave this field empty</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="contact-field @error('name') contact-field--invalid @enderror">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="120">
                        @error('name') <span class="contact-field__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="contact-field @error('email') contact-field--invalid @enderror">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="190">
                        @error('email') <span class="contact-field__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="contact-field">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" maxlength="30">
                    </div>
                    <div class="contact-field">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" value="{{ old('subject') }}" maxlength="150">
                    </div>
                    <div class="contact-field @error('message') contact-field--invalid @enderror">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="6" required maxlength="5000">{{ old('message') }}</textarea>
                        @error('message') <span class="contact-field__error">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="contact-btn">Send Message</button>
                </form>
                @endif
            </div>

        </div>
    </section>

</main>
@endsection
