<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Blocks\PageBlocks;
use App\Domain\Cities\ActiveCity;
use App\Domain\Seo\SchemaBuilder;
use App\Domain\Seo\SeoMeta;
use App\Http\Concerns\ResolvesActiveCity;
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
    use ResolvesActiveCity;

    public function index(
        Request $request,
        PageBlocks $blocks,
        BlockPresenter $presenter,
        SeoMeta $seo,
        SchemaBuilder $schema,
        ActiveCity $cities,
    ): Response {
        $city = $this->activeCity($request);
        $zoneId = $cities->zoneIdFor($request->user(), $city);

        return Inertia::render('home', [
            'blocks' => $presenter->collection($blocks->forHome($zoneId, $city?->id)),
            'meta' => $seo->resolve(url('/')),
            // LocalBusiness — the one schema a services marketplace must have (M24).
            'schema' => $schema->localBusiness(),
        ]);
    }
}
