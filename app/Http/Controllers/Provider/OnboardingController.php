<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderProfileResource;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Profile editor + KYC uploads + review status. This is the only
     * provider screen visible until the profile is approved, and stays
     * reachable afterwards as "Profile".
     */
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $profile = $user->providerProfile()->with(['categories', 'documents'])->first();

        return Inertia::render('provider/onboarding', [
            'profile' => $profile === null ? null : new ProviderProfileResource($profile),
            'categories' => Category::query()
                ->active()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'parent_id' => $category->parent_id,
                ])
                ->all(),
        ]);
    }
}
