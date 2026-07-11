<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DeveloperTokenController extends Controller
{
    public function __invoke(Chatbot $chatbot): RedirectResponse
    {
        Gate::authorize('update', $chatbot);
        abort_unless((bool) $chatbot->user->currentPlan()?->api_access, 403);

        $rawToken = $chatbot->rotateDeveloperApiToken();

        return redirect()->route('chatbots.embed', $chatbot)
            ->with('developer_token', $rawToken)
            ->with('success', 'Token API pembangun berjaya dijana. Simpan token ini sekarang.');
    }
}
