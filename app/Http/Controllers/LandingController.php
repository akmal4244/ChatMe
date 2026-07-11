<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\Plan;
use Illuminate\Support\Str;

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
        $checkoutKeys = $plans
            ->reject(fn (Plan $plan): bool => $plan->slug === 'free')
            ->mapWithKeys(fn (Plan $plan): array => [$plan->id => (string) Str::uuid()]);

        return view('subscription.plans', compact('checkoutKeys', 'plans'));
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
