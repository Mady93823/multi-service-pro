<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Blocks\PageBlocks;
use App\Http\Controllers\Controller;
use App\Http\Presenters\BlockPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The storefront home page is a CMS page built from blocks (M20, ADR D22/D31).
 * Nothing on it is hardcoded any more — the search box, the category grid and
 * the featured services are blocks an admin can reorder or remove.
 */
class HomeController extends Controller
{
    public function index(Request $request, PageBlocks $blocks, BlockPresenter $presenter): Response
    {
        $zoneId = $request->user()?->addresses()->where('is_default', true)->value('zone_id');

        return Inertia::render('home', [
            'blocks' => $presenter->collection($blocks->forHome($zoneId === null ? null : (int) $zoneId)),
        ]);
    }
}
