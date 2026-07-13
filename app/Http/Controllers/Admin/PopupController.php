<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Actions\SavePopup;
use App\Domain\Marketing\Enums\PopupAudience;
use App\Http\Controllers\Concerns\ResolvesMediaAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePopupRequest;
use App\Http\Resources\PopupResource;
use App\Models\Popup;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PopupController extends Controller
{
    use ResolvesMediaAsset;

    public function index(): Response
    {
        return Inertia::render('admin/popups/index', [
            'popups' => PopupResource::collection(
                Popup::query()->with('media')->orderByDesc('id')->get(),
            ),
            'audiences' => array_map(
                fn (PopupAudience $audience): array => ['value' => $audience->value, 'label' => $audience->label()],
                PopupAudience::cases(),
            ),
        ]);
    }

    public function store(StorePopupRequest $request, SavePopup $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['image', 'media_asset_id']),
            $this->resolveAsset($request),
        );

        return back()->with('success', __('Popup created.'));
    }

    public function update(StorePopupRequest $request, Popup $popup, SavePopup $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['image', 'media_asset_id']),
            $this->resolveAsset($request),
            $popup,
        );

        return back()->with('success', __('Popup updated.'));
    }

    public function destroy(Popup $popup): RedirectResponse
    {
        $popup->delete();

        return back()->with('success', __('Popup deleted.'));
    }
}
