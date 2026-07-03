<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home');
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
}
