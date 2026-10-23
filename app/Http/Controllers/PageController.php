<?php

namespace App\Http\Controllers;

use App\Support\Cart;

/**
 * Static policy pages (text lives in resources/views/pages).
 */
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

    public function returns()
    {
        return view('pages.return');
    }

    public function delivery()
    {
        // Delivery charge comes from the admin Settings, so this page always shows the current rates
        return view('pages.delivery', ['rates' => Cart::rates()]);
    }

    public function warranty()
    {
        return view('pages.warranty');
    }
}
