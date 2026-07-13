<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Payments\Actions\AdjustWallet;
use App\Domain\Users\Actions\SetUserActive;
use App\Domain\Users\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustWalletRequest;
use App\Http\Requests\Admin\BlockCustomerRequest;
use App\Models\Booking;
use App\Models\Referral;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M17. The one screen that answers "who is this customer and what have they
 * done" — bookings, wallet, referrals and tickets were each reachable only
 * from their own module before.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $customers = User::query()
            ->role(Role::Customer->value)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when($status === 'blocked', fn ($query) => $query->where('is_active', false))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->withCount('bookings')
            ->withSum([
                'bookings as spent_total' => fn ($query) => $query->where('status', BookingStatus::Completed->value),
            ], 'total')
            ->with('wallet')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/customers/index', [
            // data/links/meta, not a raw paginator — the shared <Pagination>
            // component reads meta.last_page (landmine 19).
            'customers' => [
                'data' => collect($customers->items())->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_active' => $user->is_active,
                    // withCount / withSum aggregates are runtime attributes.
                    'bookings_count' => (int) $user->getAttribute('bookings_count'),
                    'spent_total' => (float) $user->getAttribute('spent_total'),
                    'wallet_balance' => (float) ($user->wallet->balance ?? 0),
                    'joined_at' => $user->created_at?->format('j M Y'),
                ])->all(),
                'links' => [
                    'first' => $customers->url(1),
                    'last' => $customers->url($customers->lastPage()),
                    'prev' => $customers->previousPageUrl(),
                    'next' => $customers->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'from' => $customers->firstItem(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'to' => $customers->lastItem(),
                    'total' => $customers->total(),
                    'links' => $customers->linkCollection()->toArray(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(User $customer): Response
    {
        abort_unless($customer->hasRole(Role::Customer->value), 404);

        $bookings = $customer->bookings()
            ->latest('scheduled_at')
            ->limit(10)
            ->get();

        $walletBalance = (float) ($customer->wallet()->value('balance') ?? 0);

        $transactions = WalletTransaction::query()
            ->whereHas('wallet', fn ($query) => $query->where('user_id', $customer->id))
            ->latest('id')
            ->limit(10)
            ->get();

        $tickets = SupportTicket::query()
            ->where('user_id', $customer->id)
            ->latest('last_reply_at')
            ->limit(5)
            ->get();

        return Inertia::render('admin/customers/show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'is_active' => $customer->is_active,
                'blocked_reason' => $customer->blocked_reason,
                'referral_code' => $customer->referral_code,
                'joined_at' => $customer->created_at?->format('j M Y'),
            ],
            'stats' => [
                'bookings' => $customer->bookings()->count(),
                'completed' => $customer->bookings()->where('status', BookingStatus::Completed->value)->count(),
                'spent_total' => (float) $customer->bookings()
                    ->where('status', BookingStatus::Completed->value)
                    ->sum('total'),
                'wallet_balance' => $walletBalance,
                'referrals' => Referral::query()->where('referrer_id', $customer->id)->count(),
                'tickets' => SupportTicket::query()->where('user_id', $customer->id)->count(),
            ],
            // Status labels come from useBookingStatusLabels() on the client,
            // the same as every other booking list.
            'bookings' => $bookings->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'code' => $booking->code,
                'status' => $booking->status->value,
                'total' => (float) $booking->total,
                'scheduled_at' => $booking->scheduled_at->format('j M Y, g:i a'),
            ])->all(),
            'transactions' => $transactions->map(fn (WalletTransaction $transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'direction' => $transaction->direction,
                'amount' => (float) $transaction->amount,
                'balance_after' => (float) $transaction->balance_after,
                'created_at' => $transaction->created_at->format('j M Y'),
            ])->all(),
            'tickets' => $tickets->map(fn (SupportTicket $ticket): array => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
            ])->all(),
        ]);
    }

    public function block(BlockCustomerRequest $request, User $customer, SetUserActive $action): RedirectResponse
    {
        abort_unless($customer->hasRole(Role::Customer->value), 404);

        /** @var User $admin */
        $admin = $request->user();

        $action->handle($admin, $customer, false, (string) $request->validated('reason'));

        return back()->with('success', __('Customer blocked.'));
    }

    public function unblock(Request $request, User $customer, SetUserActive $action): RedirectResponse
    {
        abort_unless($customer->hasRole(Role::Customer->value), 404);

        /** @var User $admin */
        $admin = $request->user();

        $action->handle($admin, $customer, true);

        return back()->with('success', __('Customer unblocked.'));
    }

    /**
     * Manual wallet correction (M22). It writes through WalletService like every
     * other movement (D15), so the ledger still reconciles and an overdraw is
     * still refused — and the admin who did it is on the activity log.
     */
    public function adjustWallet(
        AdjustWalletRequest $request,
        User $customer,
        AdjustWallet $action,
        ActivityLogger $activity,
    ): RedirectResponse {
        abort_unless($customer->hasRole(Role::Customer->value), 404);

        /** @var User $admin */
        $admin = $request->user();

        $direction = (string) $request->validated('direction');
        $amount = (float) $request->validated('amount');
        $reason = (string) $request->validated('reason');

        $action->handle($customer, $direction, $amount, $reason);

        $activity->log($admin, 'wallet.adjust', $customer, [
            'direction' => $direction,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        return back()->with('success', __('Wallet updated.'));
    }
}
