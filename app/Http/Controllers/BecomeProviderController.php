<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public pitch for prospective service professionals (M19). Its CTA lands on
 * the register form with the provider role preselected (?as=provider) — the
 * account is created there, not here. This page only sells the opportunity.
 */
class BecomeProviderController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('become-provider', [
            // Real service lines, so the pitch shows what the platform actually
            // dispatches — not invented copy.
            'categories' => Category::query()
                ->active()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->take(8)
                ->pluck('name')
                ->all(),
        ]);
    }
}
