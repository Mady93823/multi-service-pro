<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Catalog\Enums\CategoryType;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Event Management hub: the event side of the catalog on one screen —
 * covers, packages, trade — instead of scattered across the category and
 * service tables. Everything links back into the ordinary catalog CRUD; this
 * page owns no writes (D42: events are a surface, never a second catalog).
 */
class EventManagementController extends Controller
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function index(): Response
    {
        $roots = Category::query()
            ->ofType(CategoryType::Event)
            ->root()
            ->orderBy('sort_order')
            ->with([
                'children.services' => fn ($query) => $query->orderBy('sort_order'),
                'services' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->get();

        $recent = $this->eventBookings()
            ->with(['customer:id,name', 'items'])
            ->latest()
            ->take(6)
            ->get();

        $windowStart = now()->subDays(30);

        $timezone = $this->settings->string('localization.timezone', 'Asia/Kolkata');

        return Inertia::render('admin/events/index', [
            'stats' => [
                'categories' => Category::query()->ofType(CategoryType::Event)->count(),
                'services' => Service::query()
                    ->whereHas('category', fn ($query) => $query->where('type', CategoryType::Event->value))
                    ->count(),
                'bookings_30' => $this->eventBookings()->where('created_at', '>=', $windowStart)->count(),
                'revenue_30' => $this->eventBookings()
                    ->where('status', BookingStatus::Completed->value)
                    ->where('created_at', '>=', $windowStart)
                    ->sum('total'),
            ],
            'roots' => $roots->map(fn (Category $root): array => [
                'id' => $root->id,
                'name' => $root->name,
                'slug' => $root->slug,
                'is_active' => $root->is_active,
                'image_url' => $this->imageUrl($root),
                'services_count' => $root->services->count() + $root->children->sum(fn (Category $child): int => $child->services->count()),
                'services' => $root->services->map(fn (Service $service): array => $this->serviceRow($service))->all(),
                'children' => $root->children->map(fn (Category $child): array => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'is_active' => $child->is_active,
                    'image_url' => $this->imageUrl($child),
                    'services' => $child->services->map(fn (Service $service): array => $this->serviceRow($service))->all(),
                ])->all(),
            ])->all(),
            'recent' => $recent->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'customer' => $booking->customer->name,
                'status' => $booking->status->value,
                'scheduled_label' => $booking->scheduled_at->timezone($timezone)->format('D, j M Y g:i A'),
                'total' => $booking->total,
                'items_count' => $booking->items->count(),
            ])->all(),
        ]);
    }

    /**
     * Bookings that carry at least one item from the event catalog.
     *
     * @return Builder<Booking>
     */
    private function eventBookings(): Builder
    {
        return Booking::query()->whereHas(
            'items.service.category',
            fn ($category) => $category->where('type', CategoryType::Event->value),
        );
    }

    private function imageUrl(Category $category): ?string
    {
        return $category->image_path !== null
            ? Storage::disk('public')->url($category->image_path)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceRow(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'price' => $service->price,
            'is_active' => $service->is_active,
            'is_featured' => $service->is_featured,
        ];
    }
}
