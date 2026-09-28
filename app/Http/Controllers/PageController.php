<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function terms()
    {
        return view('pages.terms');
    }

    public function privacy()
    {
        return view('pages.privacy');
    }

    public function refund()
    {
        return view('pages.refund');
    }

    public function contact()
    {
        return view('pages.contact');
    }
}
