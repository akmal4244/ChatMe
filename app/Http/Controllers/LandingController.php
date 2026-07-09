<?php
namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index()
    {
        $plans = Plan::where('is_active', true)->get();
        return view('landing', compact('plans'));
    }

    public function pricing()
    {
        $plans = Plan::where('is_active', true)->get();
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
