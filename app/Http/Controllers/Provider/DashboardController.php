<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Approved providers only (provider.approved middleware).
     * Jobs list arrives with M06 dispatch.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $profile = $user->providerProfile()->with(['categories', 'blackouts'])->firstOrFail();

        return Inertia::render('provider/dashboard', [
            'profile' => new ProviderProfileResource($profile),
        ]);
    }
}
