<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Payments\Actions\RejectOfflinePayment;
use App\Domain\Payments\Actions\VerifyOfflinePayment;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectOfflinePaymentRequest;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The payments hub (M22): every payment row the platform has ever seen, in one
 * place. The `payments` table has existed since M08 but was only reachable one
 * booking at a time.
 *
 * Offline rows are verified or rejected from here — through the domain actions,
 * which route into the same `ConfirmPayment` a webhook calls (D27).
 */
class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'gateway' => (string) $request->query('gateway', ''),
            'status' => (string) $request->query('status', ''),
            'search' => trim((string) $request->query('search', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $query = Payment::query()
            ->with(['booking:id,code,customer_id', 'booking.customer:id,name,email', 'bankAccount:id,label'])
            ->when(
                PaymentProvider::tryFrom($filters['gateway']) !== null,
                fn ($builder) => $builder->where('gateway', $filters['gateway']),
            )
            ->when(
                PaymentState::tryFrom($filters['status']) !== null,
                fn ($builder) => $builder->where('status', $filters['status']),
            )
            ->when($filters['search'] !== '', fn ($builder) => $builder->where(function ($builder) use ($filters): void {
                $builder->where('reference', 'like', "%{$filters['search']}%")
                    ->orWhere('gateway_ref', 'like', "%{$filters['search']}%")
                    ->orWhereHas('booking', fn ($booking) => $booking->where('code', 'like', "%{$filters['search']}%"));
            }))
            ->when($filters['from'] !== '', fn ($builder) => $builder->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($builder) => $builder->whereDate('created_at', '<=', $filters['to']));

        // Totals answer the filtered question, not the global one — a date-range
        // total that silently ignores the range is worse than no total.
        $totals = (clone $query)
            ->withoutEagerLoads()
            ->selectRaw('count(*) as rows_count')
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as captured', [PaymentState::Captured->value])
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as refunded', [PaymentState::Refunded->value])
            ->reorder()
            ->first();

        $payments = $query->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('admin/payments/index', [
            // data/links/meta, not a raw paginator — <Pagination> reads
            // meta.last_page (landmine 19).
            'payments' => [
                'data' => collect($payments->items())->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'gateway' => $payment->gateway->value,
                    'status' => $payment->status->value,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'reference' => $payment->reference ?? $payment->gateway_ref,
                    'failure_reason' => $payment->failure_reason,
                    'bank_account' => $payment->bankAccount?->label,
                    'has_proof' => $payment->getFirstMedia('proof') !== null,
                    'proof_url' => $this->proofUrl($payment),
                    'booking' => [
                        'id' => $payment->booking_id,
                        'code' => $payment->booking?->code,
                        'customer' => $payment->booking?->customer?->name,
                    ],
                    'created_at' => $payment->created_at?->format('j M Y, g:i a'),
                    'captured_at' => $payment->captured_at?->format('j M Y, g:i a'),
                ])->all(),
                'links' => [
                    'first' => $payments->url(1),
                    'last' => $payments->url($payments->lastPage()),
                    'prev' => $payments->previousPageUrl(),
                    'next' => $payments->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'from' => $payments->firstItem(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'to' => $payments->lastItem(),
                    'total' => $payments->total(),
                    'links' => $payments->linkCollection()->toArray(),
                ],
            ],
            'totals' => [
                'count' => (int) ($totals?->getAttribute('rows_count') ?? 0),
                'captured' => (float) ($totals?->getAttribute('captured') ?? 0),
                'refunded' => (float) ($totals?->getAttribute('refunded') ?? 0),
                'awaiting' => Payment::query()->awaitingVerification()->count(),
            ],
            'filters' => $filters,
            'gateways' => array_map(fn (PaymentProvider $case): string => $case->value, PaymentProvider::cases()),
            'statuses' => array_map(fn (PaymentState $case): string => $case->value, PaymentState::cases()),
        ]);
    }

    public function verify(Request $request, Payment $payment, VerifyOfflinePayment $action, ActivityLogger $activity): RedirectResponse
    {
        $admin = $request->user();
        abort_if($admin === null, 403);

        $action->handle($payment, $admin);

        $activity->log($admin, 'payment.verify', $payment, [
            'booking_id' => $payment->booking_id,
            'amount' => (float) $payment->amount,
        ]);

        return back()->with('success', __('Payment verified. The booking is confirmed.'));
    }

    public function reject(RejectOfflinePaymentRequest $request, Payment $payment, RejectOfflinePayment $action, ActivityLogger $activity): RedirectResponse
    {
        $admin = $request->user();
        abort_if($admin === null, 403);

        $reason = (string) $request->validated('reason');

        $action->handle($payment, $admin, $reason);

        $activity->log($admin, 'payment.reject', $payment, [
            'booking_id' => $payment->booking_id,
            'reason' => $reason,
        ]);

        return back()->with('success', __('Payment rejected. The customer has been notified.'));
    }

    private function proofUrl(Payment $payment): ?string
    {
        $media = $payment->getFirstMedia('proof');

        return $media === null
            ? null
            : route('payments.proof.show', [$payment->id, $media->id]);
    }
}
