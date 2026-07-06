<?php

namespace App\Http\Controllers;

use App\Events\DemoPing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 1 WebSocket smoke test trigger. Removed once real broadcast
 * features (tracking, notifications) land in Phase 3.
 */
class DemoPingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:100'],
        ]);

        DemoPing::dispatch($validated['message'] ?? 'Hello from Reverb!');

        return back();
    }
}
