<?php

namespace App\Http\Controllers;

use App\Models\Plan;

class LandingController extends Controller
{
    public function index()
    {
        $plans = Plan::visibleForSale()->get();

        return view('landing', compact('plans'));
    }

    public function pricing()
    {
        $plans = Plan::visibleForSale()->get();

        return view('subscription.plans', compact('plans'));
    }

    public function features()
    {
        return view('landing');
    }

    public function contact()
    {
        return view('landing');
    }
}
