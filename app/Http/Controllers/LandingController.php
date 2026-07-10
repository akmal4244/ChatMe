<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\Plan;

class LandingController extends Controller
{
    public function index()
    {
        $plans = Plan::visibleForSale()->get();
        $homepageChatbot = Chatbot::query()
            ->where('slug', config('chatme.homepage_chatbot.slug'))
            ->where('is_active', true)
            ->first();

        return view('landing', compact('plans', 'homepageChatbot'));
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
