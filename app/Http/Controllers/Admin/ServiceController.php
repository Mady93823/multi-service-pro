<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\CreateService;
use App\Domain\Catalog\Actions\DeleteService;
use App\Domain\Catalog\Actions\UpdateService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceRequest;
use App\Http\Requests\Admin\UpdateServiceRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ServiceResource;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'category_id' => $request->integer('category_id') ?: null,
        ];

        $services = Service::query()
            ->with('category')
            ->withCount('addons')
            ->when($filters['search'] !== '', fn ($query) => $query->search($filters['search']))
            ->when($filters['category_id'] !== null, fn ($query) => $query->where('category_id', $filters['category_id']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/services/index', [
            'services' => ServiceResource::collection($services),
            'categories' => CategoryResource::collection($this->categoryOptions()),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/services/create', [
            'categories' => CategoryResource::collection($this->categoryOptions()),
            'relatable' => ServiceResource::collection(
                Service::query()->orderBy('name')->get(['id', 'category_id', 'name', 'slug', 'pricing_type', 'price'])
            ),
        ]);
    }

    public function store(StoreServiceRequest $request, CreateService $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['addons', 'related_ids', 'image']),
            $request->validated('addons', []),
            $request->validated('related_ids', []),
            $request->file('image'),
        );

        return to_route('admin.services.index')->with('success', __('Service created.'));
    }

    public function edit(Service $service): Response
    {
        $service->load(['addons', 'related']);

        return Inertia::render('admin/services/edit', [
            'service' => new ServiceResource($service),
            'categories' => CategoryResource::collection($this->categoryOptions()),
            'relatable' => ServiceResource::collection(
                Service::query()
                    ->whereKeyNot($service->id)
                    ->orderBy('name')
                    ->get(['id', 'category_id', 'name', 'slug', 'pricing_type', 'price'])
            ),
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service, UpdateService $action): RedirectResponse
    {
        $action->handle(
            $service,
            $request->safe()->except(['addons', 'related_ids', 'image']),
            $request->validated('addons', []),
            $request->validated('related_ids', []),
            $request->file('image'),
        );

        return to_route('admin.services.index')->with('success', __('Service updated.'));
    }

    public function destroy(Service $service, DeleteService $action): RedirectResponse
    {
        $action->handle($service);

        return to_route('admin.services.index')->with('success', __('Service deleted.'));
    }

    /**
     * Flat category list (roots ordered, children after their parent) for selects.
     *
     * @return Collection<int, Category>
     */
    protected function categoryOptions(): Collection
    {
        return Category::root()
            ->with('children')
            ->orderBy('sort_order')
            ->get()
            ->flatMap(fn (Category $root) => collect([$root])->concat($root->children));
    }
}
