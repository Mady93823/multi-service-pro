<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Register/forget a device push token (M11). The frontend only calls this once
 * Firebase is configured and the user grants permission; the token is stored
 * regardless so push can start the moment credentials land.
 */
class FcmTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'device_type' => ['nullable', 'string', 'in:web,android,ios'],
        ]);

        $user->fcmTokens()->updateOrCreate(
            ['token' => $validated['token']],
            ['device_type' => $validated['device_type'] ?? 'web', 'last_used_at' => now()],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate(['token' => ['required', 'string']]);

        $user->fcmTokens()->where('token', $validated['token'])->delete();

        return response()->json(['ok' => true]);
    }
}
