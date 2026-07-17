<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Bookings\CartManager;
use App\Domain\Bookings\PriceQuote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AddToCartRequest;
use App\Http\Requests\Customer\UpdateCartLineRequest;
use App\Models\ServiceAddon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Session cart — works for guests; checkout is where auth kicks in.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartManager $cart,
        private readonly PriceQuote $quote,
    ) {}

    public function show(Request $request): Response
    {
        $lines = $this->cart->detailed();
        $quote = $this->quote->fromLines($lines);

        $zoneId = $request->user()?->addresses()->where('is_default', true)->value('zone_id');

        return Inertia::render('cart', [
            'lines' => array_map(fn (array $line): array => [
                'key' => $line['key'],
                'qty' => $line['qty'],
                'service' => [
                    'id' => $line['service']->id,
                    'name' => $line['service']->name,
                    'slug' => $line['service']->slug,
                    'category_slug' => $line['service']->category?->slug,
                    'image_thumb_url' => $line['service']->getFirstMediaUrl('images', 'thumb') ?: null,
                    'price' => $line['service']->price,
                    'duration_minutes' => $line['service']->duration_minutes,
                ],
                'addons' => $line['addons']->map(fn (ServiceAddon $addon): array => [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'price' => $addon->price,
                ])->all(),
                'unit_total' => $line['unit_total'],
                'line_total' => $line['line_total'],
            ], $lines),
            'summary' => [
                'subtotal' => number_format($quote['subtotal'] + $quote['addon_total'], 2, '.', ''),
                'tax' => number_format($quote['tax'], 2, '.', ''),
                'tax_label' => $quote['tax_label'],
                'tax_percent' => $quote['tax_percent'],
                'total' => number_format($quote['total'], 2, '.', ''),
            ],
            'blocked_services' => $this->cart->blockedServiceNames(
                $lines,
                $zoneId === null ? null : (int) $zoneId,
            ),
            'has_default_address' => $zoneId !== null
                || ($request->user()?->addresses()->where('is_default', true)->exists() ?? false),
        ]);
    }

    public function add(AddToCartRequest $request): RedirectResponse
    {
        /** @var list<int> $addonIds */
        $addonIds = array_map(intval(...), $request->validated('addon_ids', []));

        $this->cart->add(
            (int) $request->validated('service_id'),
            (int) $request->validated('qty'),
            $addonIds,
        );

        // Book now skips the cart page: for a single service it decides
        // nothing. The line is still added — checkout reads the same cart —
        // and a guest is bounced to login by the checkout route's own guard.
        if ($request->boolean('book_now')) {
            return redirect()->route('checkout.show');
        }

        // Landing on the cart rather than staying put: the running total and
        // the one next step are on it. "Continue shopping" is the way back.
        return redirect()->route('cart.show')->with('success', __('Added to cart.'));
    }

    public function update(UpdateCartLineRequest $request, string $key): RedirectResponse
    {
        $this->cart->updateQty($key, (int) $request->validated('qty'));

        return back();
    }

    public function remove(string $key): RedirectResponse
    {
        $this->cart->remove($key);

        return back()->with('success', __('Removed from cart.'));
    }
}
