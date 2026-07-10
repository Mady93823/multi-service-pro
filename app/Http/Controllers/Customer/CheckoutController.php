<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Bookings\Actions\PlaceBooking;
use App\Domain\Bookings\CartManager;
use App\Domain\Bookings\PriceQuote;
use App\Domain\Bookings\SlotGenerator;
use App\Domain\Coupons\CouponValidator;
use App\Domain\Payments\Actions\PayWithWallet;
use App\Domain\Payments\WalletService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\PlaceBookingRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartManager $cart,
        private readonly PriceQuote $quote,
        private readonly SlotGenerator $slots,
        private readonly WalletService $wallet,
        private readonly CouponValidator $coupons,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $lines = $this->cart->detailed();

        if ($lines === []) {
            return redirect()->route('cart.show')->with('error', __('Your cart is empty.'));
        }

        $user = $request->user();
        abort_if($user === null, 403);

        $addresses = $user->addresses()->with('zone')->orderByDesc('is_default')->latest()->get();

        // Preview leg of the coupon (M12): a session code that no longer
        // passes (window closed, cap reached, cart shrank under min_order)
        // is dropped with an explanation instead of failing at placement.
        $discount = 0.0;
        $coupon = null;
        $couponError = null;
        $couponCode = $this->cart->couponCode();

        if ($couponCode !== null) {
            try {
                $found = $this->coupons->findByCode($couponCode);

                if ($found === null) {
                    throw ValidationException::withMessages(['coupon' => __('That coupon code is not valid.')]);
                }

                $discount = $this->coupons->discountFor($found, $user, $this->quote->baseFor($lines));
                $coupon = ['code' => $found->code, 'discount' => number_format($discount, 2, '.', '')];
            } catch (ValidationException $exception) {
                $this->cart->setCouponCode(null);
                $couponError = collect($exception->errors())->flatten()->first();
            }
        }

        $quote = $this->quote->fromLines($lines, $discount);

        return Inertia::render('checkout', [
            'coupon' => $coupon,
            'coupon_error' => $couponError,
            'lines' => array_map(fn (array $line): array => [
                'key' => $line['key'],
                'qty' => $line['qty'],
                'name' => $line['service']->name,
                'addon_names' => $line['addons']->pluck('name')->all(),
                'line_total' => $line['line_total'],
            ], $lines),
            'addresses' => $addresses->map(fn (Address $address): array => [
                'address' => new AddressResource($address),
                // Zone gate (M03 done-when): out-of-zone addresses are shown
                // but politely blocked with the offending service names.
                'blocked_services' => $this->cart->blockedServiceNames($lines, $address->zone_id),
            ])->all(),
            'slot_days' => $this->slots->days(),
            'summary' => [
                'subtotal' => number_format($quote['subtotal'] + $quote['addon_total'], 2, '.', ''),
                'discount' => number_format($quote['discount'], 2, '.', ''),
                'tax' => number_format($quote['tax'], 2, '.', ''),
                'tax_label' => $quote['tax_label'],
                'tax_percent' => $quote['tax_percent'],
                'total' => number_format($quote['total'], 2, '.', ''),
            ],
            'payment_methods' => PlaceBookingRequest::availableMethods(),
            'wallet_balance' => number_format($this->wallet->balance($user), 2, '.', ''),
        ]);
    }

    public function store(PlaceBookingRequest $request, PlaceBooking $action, PayWithWallet $wallet): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        /** @var Address $address */
        $address = $user->addresses()->findOrFail((int) $request->validated('address_id'));

        $uploaded = $request->file('photos');
        /** @var list<UploadedFile> $photos */
        $photos = match (true) {
            $uploaded === null => [],
            is_array($uploaded) => array_values($uploaded),
            default => [$uploaded],
        };

        $booking = $action->handle(
            $user,
            $address,
            CarbonImmutable::parse((string) $request->validated('scheduled_at'))->utc(),
            $request->paymentMethod(),
            $request->validated('notes'),
            $photos,
        );

        // Wallet settles instantly; a short balance drops to the pay page
        // where every other method is still on offer (M08).
        if ($request->chosenMethod() === 'wallet') {
            try {
                $wallet->handle($booking, $user);
            } catch (ValidationException) {
                return redirect()
                    ->route('bookings.pay', $booking)
                    ->with('error', __('Your wallet balance is not enough for this payment.'));
            }
        } elseif ($request->gatewayProvider() !== null) {
            return redirect()->route('bookings.pay', $booking);
        }

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', __('Booking placed! Your booking code is :code.', ['code' => $booking->code]));
    }
}
