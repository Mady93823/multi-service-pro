<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Bookings\Actions\PlaceBooking;
use App\Domain\Bookings\CartManager;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\PriceQuote;
use App\Domain\Bookings\SlotGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\PlaceBookingRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartManager $cart,
        private readonly PriceQuote $quote,
        private readonly SlotGenerator $slots,
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
        $quote = $this->quote->fromLines($lines);

        return Inertia::render('checkout', [
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
            'payment_methods' => [PaymentMethod::Cash->value],
        ]);
    }

    public function store(PlaceBookingRequest $request, PlaceBooking $action): RedirectResponse
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
            PaymentMethod::from((string) $request->validated('payment_method')),
            $request->validated('notes'),
            $photos,
        );

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', __('Booking placed! Your booking code is :code.', ['code' => $booking->code]));
    }
}
