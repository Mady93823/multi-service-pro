<?php

namespace App\Http\Controllers\Provider;

use App\Domain\Earnings\Actions\DeletePayoutAccount;
use App\Domain\Earnings\Actions\SavePayoutAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\StorePayoutAccountRequest;
use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A provider's saved payout destinations (M22). Managed from the earnings
 * screen; the payout dialog then picks one instead of retyping bank details
 * every time (M09's free-text block is gone).
 */
class PayoutAccountController extends Controller
{
    public function store(StorePayoutAccountRequest $request, SavePayoutAccount $action): RedirectResponse
    {
        /** @var User $provider */
        $provider = $request->user();

        $action->handle($provider, $request->validated());

        return back()->with('success', __('Payout account saved.'));
    }

    public function update(StorePayoutAccountRequest $request, PayoutAccount $account, SavePayoutAccount $action): RedirectResponse
    {
        /** @var User $provider */
        $provider = $request->user();

        $this->owned($provider, $account);

        $action->handle($provider, $request->validated(), $account);

        return back()->with('success', __('Payout account updated.'));
    }

    public function destroy(Request $request, PayoutAccount $account, DeletePayoutAccount $action): RedirectResponse
    {
        /** @var User $provider */
        $provider = $request->user();

        $this->owned($provider, $account);

        $action->handle($account);

        return back()->with('success', __('Payout account removed.'));
    }

    /** Someone else's account is not a 403 — it is not theirs to know about. */
    private function owned(User $provider, PayoutAccount $account): void
    {
        abort_unless($account->provider_id === $provider->id, 404);
    }
}
