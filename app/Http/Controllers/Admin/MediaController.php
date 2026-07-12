<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Media\Actions\DeleteMediaAsset;
use App\Domain\Media\Actions\UploadMediaAsset;
use App\Domain\Media\MediaLibrary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaAssetRequest;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M18. The library lists **only** the `library` collection on the public disk.
 * Private-disk collections (KYC, booking photos, review photos, ticket
 * attachments) are customer data, not marketing assets, and never appear here.
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaLibrary $library) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $assets = $this->library->assets($search);
        $usage = $this->library->usageCounts();

        return Inertia::render('admin/media/index', [
            'assets' => [
                'data' => collect($assets->items())
                    ->map(fn (MediaAsset $asset): array => $this->library->toArray($asset, $usage))
                    ->all(),
                'links' => [
                    'first' => $assets->url(1),
                    'last' => $assets->url($assets->lastPage()),
                    'prev' => $assets->previousPageUrl(),
                    'next' => $assets->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $assets->currentPage(),
                    'from' => $assets->firstItem(),
                    'last_page' => $assets->lastPage(),
                    'per_page' => $assets->perPage(),
                    'to' => $assets->lastItem(),
                    'total' => $assets->total(),
                    'links' => $assets->linkCollection()->toArray(),
                ],
            ],
            'stats' => $this->library->stats(),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(StoreMediaAssetRequest $request, UploadMediaAsset $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        foreach ($this->uploadedFiles($request) as $file) {
            $action->handle($admin, $file);
        }

        return back()->with('success', __('Files uploaded.'));
    }

    public function destroy(MediaAsset $asset, DeleteMediaAsset $action): RedirectResponse
    {
        $action->handle($asset);

        return back()->with('success', __('File deleted.'));
    }

    /**
     * JSON feed for the MediaPicker dialog — the picker is not an Inertia visit
     * (it opens over whatever form the admin is filling in).
     */
    public function picker(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $assets = $this->library->assets($search, 18);

        return response()->json([
            'data' => collect($assets->items())
                ->map(fn (MediaAsset $asset): array => $this->library->toArray($asset))
                ->all(),
            'next_page_url' => $assets->nextPageUrl(),
        ]);
    }

    /**
     * Upload from inside the picker: same action, JSON back instead of a redirect.
     */
    public function pickerStore(StoreMediaAssetRequest $request, UploadMediaAsset $action): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $created = [];

        foreach ($this->uploadedFiles($request) as $file) {
            $created[] = $this->library->toArray($action->handle($admin, $file));
        }

        return response()->json(['data' => $created], 201);
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedFiles(Request $request): array
    {
        $files = $request->file('files');
        $files = is_array($files) ? $files : [$files];

        return array_values(array_filter(
            $files,
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }
}
