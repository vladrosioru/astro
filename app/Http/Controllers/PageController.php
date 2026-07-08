<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PageController extends Controller
{
    public function home()
    {
        $locale = app()->getLocale();

        // Same query the Journal page uses — every published post with a
        // translation for the current locale. The newest post is shown as a
        // featured card; the rest page through the "From the Journal" carousel.
        $journalPosts = Post::published()
            ->with('translations')
            ->latest('published_at')
            ->get()
            ->map(fn (Post $p) => ['post' => $p, 'translation' => $p->translation($locale)])
            ->filter(fn (array $entry) => $entry['translation'] !== null)
            ->values();

        return view('pages.home', [
            'featuredPost' => $journalPosts->first(),
            'journalPosts' => $journalPosts->slice(1)->filter(fn (array $entry) => $entry['post']->featured_image)->values(),
            'locale' => $locale,
        ]);
    }

    public function about()
    {
        abort_unless(SiteSetting::current()->sectionVisible('about'), 404);

        return view('pages.about');
    }

    public function services()
    {
        abort_unless(SiteSetting::current()->sectionVisible('services'), 404);

        return view('pages.services');
    }

    public function contact()
    {
        abort_unless(SiteSetting::current()->sectionVisible('contact'), 404);

        return view('pages.contact', ['contact' => SiteSetting::current()->contact]);
    }

    public function contactSubmit(Request $request): RedirectResponse
    {
        abort_unless(SiteSetting::current()->sectionVisible('contact'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // Honeypot ('website') and a render-timestamp trap: both are invisible to
        // real visitors but reliably tripped by form-filling bots. Either one
        // pretends success so the bot doesn't learn to route around it.
        $renderedAt = (int) $request->input('rendered_at');
        $tooFast = $renderedAt > 0 && (time() - $renderedAt) < 3;

        if ($request->filled('website') || $tooFast) {
            return back()->with('contact_status', 'sent');
        }

        Mail::to(config('mail.from.address'))->send(new ContactMessage($data));

        return back()->with('contact_status', 'sent');
    }
}
