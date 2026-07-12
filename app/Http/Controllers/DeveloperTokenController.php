<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeveloperTokenController extends Controller
{
    public function __invoke(Request $request, Chatbot $chatbot): Response
    {
        Gate::authorize('update', $chatbot);
        abort_unless((bool) $chatbot->user->currentPlan()?->api_access, 403);

        $request->validate([
            'current_password' => ['required', 'current_password:web'],
        ], attributes: [
            'current_password' => 'kata laluan semasa',
        ]);

        $rawToken = $chatbot->rotateDeveloperApiToken();

        return response()
            ->view('chatbots.developer-token', [
                'chatbot' => $chatbot->fresh(),
                'rawToken' => $rawToken,
            ])
            ->withHeaders([
                'Cache-Control' => 'no-store, private, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
    }
}
