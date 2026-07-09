<?php
namespace App\Http\Controllers;

use App\Models\ChatLog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $chatbots = $user->chatbots()->withCount('knowledgeItems')->latest()->get();
        $totalMessages = ChatLog::whereIn('chatbot_id', $chatbots->pluck('id'))->count();
        $subscription = $user->activeSubscription();
        $subscription = $user->activeSubscription();

        return view('dashboard', compact('chatbots', 'totalMessages', 'subscription'));
    }
}