<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Bookings\CartManager;
use App\Domain\Catalog\Enums\CategoryType;
use App\Domain\Seo\SchemaBuilder;
use App\Domain\Seo\SeoMeta;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Concerns\ResolvesActiveCity;
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
    use ResolvesActiveCity;

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
        $cityId = $this->activeCity($request)?->id;
        $zoneId = $this->customerZoneId($request);

        $results = null;

        if ($search !== '') {
            $results = Service::query()
                ->active()
                ->inCity($cityId)
                ->inZone($zoneId)
                ->search($search)
                ->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->with(['category', 'media'])
                ->paginate(12)
                ->withQueryString();
        }

        return Inertia::render('catalog/index', [
            // Event categories live on their own page (/events) — this grid is
            // the everyday services surface only.
            'categories' => CategoryResource::collection(
                Category::root()
                    ->active()
                    ->ofType(CategoryType::Service)
                    ->with(['children' => fn ($query) => $query->where('is_active', true)])
                    ->orderBy('sort_order')
                    ->get()
            ),
            'featured' => ServiceResource::collection(
                Service::query()
                    ->active()
                    ->inCity($cityId)
                    ->inZone($zoneId)
                    ->where('is_featured', true)
                    ->whereHas('category', fn ($query) => $query
                        ->where('is_active', true)
                        ->where('type', CategoryType::Service->value))
                    ->with(['category', 'media'])
                    ->orderBy('sort_order')
                    ->limit(8)
                    ->get()
            ),
            'search' => $search,
            'results' => $results !== null ? ServiceResource::collection($results) : null,
        ]);
    }

    /**
     * The dedicated Event Management page: the same catalog machinery —
     * zone/city gates, cart, checkout, dispatch — filtered to `event`
     * categories (weddings, birthdays, kitty parties, ...). A category or
     * service opened from here uses the ordinary catalog routes; only the
     * listing surface is its own.
     */
    public function events(Request $request): Response
    {
        $cityId = $this->activeCity($request)?->id;
        $zoneId = $this->customerZoneId($request);

        return Inertia::render('catalog/events', [
            'categories' => CategoryResource::collection(
                Category::root()
                    ->active()
                    ->ofType(CategoryType::Event)
                    ->with(['children' => fn ($query) => $query->where('is_active', true)])
                    ->orderBy('sort_order')
                    ->get()
            ),
            'featured' => ServiceResource::collection(
                Service::query()
                    ->active()
                    ->inCity($cityId)
                    ->inZone($zoneId)
                    ->whereHas('category', fn ($query) => $query
                        ->where('is_active', true)
                        ->where('type', CategoryType::Event->value))
                    ->with(['category', 'media'])
                    ->orderBy('sort_order')
                    ->limit(8)
                    ->get()
            ),
        ]);
    }

    public function category(Request $request, Category $category): Response
    {
        abort_unless($category->is_active, 404);

        $category->load(['children' => fn ($query) => $query->where('is_active', true)]);

        $categoryIds = $category->children->pluck('id')->push($category->id);

        $services = Service::query()
            ->active()
            ->inCity($this->activeCity($request)?->id)
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
        CartManager $cart,
    ): Response {
        abort_unless($category->is_active && $service->is_active, 404);

        $service->load([
            'category',
            'addons' => fn ($query) => $query->where('is_active', true),
            'related' => fn ($query) => $query->where('is_active', true)->with(['category', 'media']),
            'media',
        ]);

        // Bookable here? The same two scopes the listings are gated with, asked
        // of one service — never a third rule that could disagree with them.
        $available = Service::query()
            ->whereKey($service->id)
            ->inCity($this->activeCity($request)?->id)
            ->inZone($this->customerZoneId($request))
            ->exists();

        return Inertia::render('catalog/show', [
            'service' => new ServiceResource($service),
            'available_in_zone' => $available,
            // Adding the same service twice is a real intention, so the button
            // stays — this is what lets the page say the cart already has some.
            'in_cart_qty' => $cart->qtyForService($service->id),
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
     * Zone of the signed-in customer's default address; null for guests, for an
     * address outside every zone, and (M25) while they are browsing a city that
     * address does not sit in — there the city gate is the only honest filter.
     */
    protected function customerZoneId(Request $request): ?int
    {
        return $this->activeZoneId($request, $this->activeCity($request));
    }
}
