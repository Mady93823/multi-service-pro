<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payments\Actions\DeleteBankAccount;
use App\Domain\Payments\Actions\SaveBankAccount;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Concerns\ResolvesMediaAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The accounts customers transfer into for an offline payment (M22). Admin-owned
 * data rather than a settings blob, so a payment row can reference the exact
 * account it was paid into.
 */
class BankAccountController extends Controller
{
    use ResolvesMediaAsset;

    public function index(SettingsRegistry $settings): Response
    {
        return Inertia::render('admin/bank-accounts/index', [
            'accounts' => BankAccountResource::collection(
                BankAccount::query()->with('media')->orderBy('sort_order')->orderBy('id')->get(),
            ),
            // Shown as a warning on the screen: accounts exist but the method is
            // switched off, so no customer is being offered them.
            'offline_enabled' => $settings->boolean('payments.offline_enabled', false),
        ]);
    }

    public function store(StoreBankAccountRequest $request, SaveBankAccount $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['media_asset_id']),
            $this->resolveAsset($request),
        );

        return back()->with('success', __('Bank account added.'));
    }

    public function update(StoreBankAccountRequest $request, BankAccount $account, SaveBankAccount $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['media_asset_id']),
            $this->resolveAsset($request),
            $account,
        );

        return back()->with('success', __('Bank account updated.'));
    }

    public function destroy(BankAccount $account, DeleteBankAccount $action): RedirectResponse
    {
        $action->handle($account);

        return back()->with('success', __('Bank account deleted.'));
    }
}
