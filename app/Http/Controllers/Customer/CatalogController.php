<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ServiceResource;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    /**
     * Public storefront: category grid + featured services; doubles as
     * the search results page when ?search= is present.
     */
    public function index(Request $request): Response
    {
        $search = (string) $request->string('search');
        $zoneId = $this->customerZoneId($request);

        $results = null;

        if ($search !== '') {
            $results = Service::query()
                ->active()
                ->inZone($zoneId)
                ->search($search)
                ->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->with(['category', 'media'])
                ->paginate(12)
                ->withQueryString();
        }

        return Inertia::render('catalog/index', [
            'categories' => CategoryResource::collection(
                Category::root()
                    ->active()
                    ->with(['children' => fn ($query) => $query->where('is_active', true)])
                    ->orderBy('sort_order')
                    ->get()
            ),
            'featured' => ServiceResource::collection(
                Service::query()
                    ->active()
                    ->inZone($zoneId)
                    ->where('is_featured', true)
                    ->whereHas('category', fn ($query) => $query->where('is_active', true))
                    ->with(['category', 'media'])
                    ->orderBy('sort_order')
                    ->limit(8)
                    ->get()
            ),
            'search' => $search,
            'results' => $results !== null ? ServiceResource::collection($results) : null,
        ]);
    }

    public function category(Request $request, Category $category): Response
    {
        abort_unless($category->is_active, 404);

        $category->load(['children' => fn ($query) => $query->where('is_active', true)]);

        $categoryIds = $category->children->pluck('id')->push($category->id);

        $services = Service::query()
            ->active()
            ->inZone($this->customerZoneId($request))
            ->whereIn('category_id', $categoryIds)
            ->with(['category', 'media'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('catalog/category', [
            'category' => new CategoryResource($category),
            'services' => ServiceResource::collection($services),
        ]);
    }

    public function show(Request $request, Category $category, Service $service): Response
    {
        abort_unless($category->is_active && $service->is_active, 404);

        $service->load([
            'category',
            'addons' => fn ($query) => $query->where('is_active', true),
            'related' => fn ($query) => $query->where('is_active', true)->with(['category', 'media']),
            'media',
        ]);

        $zoneId = $this->customerZoneId($request);

        return Inertia::render('catalog/show', [
            'service' => new ServiceResource($service),
            'available_in_zone' => $zoneId === null
                || ! $service->zones()->exists()
                || $service->zones()->whereKey($zoneId)->exists(),
        ]);
    }

    /**
     * Zone of the signed-in customer's default address; null for guests
     * or when the default address is outside every zone (no filtering).
     */
    protected function customerZoneId(Request $request): ?int
    {
        $zoneId = $request->user()?->addresses()->where('is_default', true)->value('zone_id');

        return $zoneId === null ? null : (int) $zoneId;
    }
}
