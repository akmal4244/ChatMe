<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Chatbot;
use App\Models\ChatLog;
use App\Models\KnowledgeItem;
use App\Models\Plan;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_chatbots' => Chatbot::count(),
            'total_knowledge' => KnowledgeItem::count(),
            'total_messages' => ChatLog::count(),
            'total_plans' => Plan::count(),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_chatbots_today' => Chatbot::whereDate('created_at', today())->count(),
            'messages_today' => ChatLog::whereDate('created_at', today())->count(),
        ];

        $recent_users = User::latest()->take(10)->get();
        $recent_chatbots = Chatbot::with('user')->latest()->take(10)->get();

        return view('admin.dashboard', compact('stats', 'recent_users', 'recent_chatbots'));
    }

    public function users()
    {
        $users = User::withCount('chatbots')->latest()->paginate(20);
        return view('admin.users', compact('users'));
    }

    public function chatbots()
    {
        $chatbots = Chatbot::with('user')->withCount('knowledgeItems')->latest()->paginate(20);
        return view('admin.chatbots', compact('chatbots'));
    }

    public function toggleAdmin(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak boleh menukar status admin sendiri.');
        }
        $user->is_admin = !$user->is_admin;
        $user->save();
        return back()->with('success', 'Status pentadbir ' . $user->name . ' dikemaskini.');
    }
}
