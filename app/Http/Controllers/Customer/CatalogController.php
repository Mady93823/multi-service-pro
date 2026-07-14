<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Seo\SchemaBuilder;
use App\Domain\Seo\SeoMeta;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\ServiceResource;
use App\Models\Category;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    /**
     * The services page: category grid + featured services, doubling as the
     * search results page when ?search= is present.
     *
     * The *home* page is no longer this screen — since M20 it is a page built
     * from blocks (HomeController), and the marketing sections that used to
     * live here (banners, testimonials, sponsors, FAQ) are blocks on it.
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

    public function show(
        Request $request,
        Category $category,
        Service $service,
        SeoMeta $seo,
        SchemaBuilder $schema,
    ): Response {
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
            // M24: the service's own overrides, then the site defaults.
            'meta' => $seo->resolve(
                url: route('catalog.show', [$category->slug, $service->slug]),
                title: $service->meta_title ?? $service->name,
                description: $service->meta_description ?? $service->short_description,
                image: $service->getFirstMediaUrl('images', 'card') ?: null,
                type: 'product',
            ),
            'schema' => $schema->service($service),
            ...$this->reviewProps($service),
        ]);
    }

    /**
     * Storefront reviews for a service (M10): visible reviews whose booking
     * contained it, plus the aggregate the rating header needs. Reviews hang
     * off the provider, not the service — this join through booking_items is
     * how a multi-service booking's review reaches every service page it
     * bought from.
     *
     * @return array<string, mixed>
     */
    protected function reviewProps(Service $service): array
    {
        if (! app(SettingsRegistry::class)->boolean('reviews.enabled', true)) {
            return ['reviews' => null, 'review_summary' => null];
        }

        $base = Review::query()
            ->visible()
            ->whereHas('booking.items', fn ($query) => $query->where('service_id', $service->id));

        /** @var object{total: int, avg: float|string|null} $aggregate */
        $aggregate = (clone $base)
            ->selectRaw('count(*) as total, coalesce(avg(rating), 0) as avg')
            ->first();

        /** @var array<int, int> $counts */
        $counts = (clone $base)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->all();

        $reviews = (clone $base)
            ->with(['customer:id,name', 'media'])
            ->latest('id')
            ->paginate(6, pageName: 'reviews_page')
            ->withQueryString();

        return [
            'reviews' => ReviewResource::collection($reviews),
            'review_summary' => [
                'average' => round((float) $aggregate->avg, 1),
                'count' => (int) $aggregate->total,
                'distribution' => collect([5, 4, 3, 2, 1])
                    ->mapWithKeys(fn (int $stars): array => [$stars => $counts[$stars] ?? 0])
                    ->all(),
            ],
        ];
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
