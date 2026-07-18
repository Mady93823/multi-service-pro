<?php

namespace App\Http\Requests\Customer;

use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\GatewayManager;
use App\Domain\Settings\SettingsRegistry;
use App\Models\BankAccount;
use App\Models\Zone;
use App\Support\UploadRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceBookingRequest extends FormRequest
{
    /** Digits with an optional leading +, allowing spaces and dashes. */
    public const PHONE_PATTERN = '/^\+?[0-9][0-9\s\-]{6,18}$/';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where('user_id', $user === null ? 0 : $user->id),
            ],
            'scheduled_at' => ['required', 'date', 'after:now'],
            // The professional must be able to call the doorstep — a booking
            // without a reachable number is not placeable. The alternate is a
            // fallback contact (spouse, neighbour) and stays optional.
            'contact_phone' => ['required', 'string', 'max:20', 'regex:'.self::PHONE_PATTERN],
            'contact_phone_alt' => ['nullable', 'string', 'max:20', 'regex:'.self::PHONE_PATTERN],
            // D43: the offer is judged against the address actually being
            // booked — a cash post for an online-only area must 422 here, the
            // checkout picker filtering the option out is only presentation.
            'payment_method' => ['required', Rule::in(self::availableMethods($this->payloadZone()))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos' => ['array', 'max:4'],
            'photos.*' => UploadRules::image(),
        ];
    }

    /**
     * The zone of the address being booked, resolved from the raw payload —
     * rules() runs before validation, so this must never throw. A missing or
     * foreign address returns null (the address_id rule fails on its own) and
     * the method list falls back to the global one.
     */
    private function payloadZone(): ?Zone
    {
        $addressId = $this->input('address_id');

        if (! is_numeric($addressId)) {
            return null;
        }

        return $this->user()
            ?->addresses()
            ->with('zone.city')
            ->find((int) $addressId)
            ?->zone;
    }

    /**
     * Methods offered at checkout right now (M08): pay-after-service when
     * enabled, each configured gateway by name, wallet when enabled, bank
     * transfer when it is switched on *and* an account exists to transfer into
     * (M22 — an offline option with no instructions behind it is a dead end).
     * These are the form values; the booking column stores the coarse
     * cash|gateway|wallet|offline enum via paymentMethod().
     *
     * With a zone, cash is additionally gated by geography (D43): the global
     * flag decides whether the product takes cash at all, the zone decides
     * whether this area does. No zone (site-wide contexts) means the global
     * flag alone.
     *
     * @return list<string>
     */
    public static function availableMethods(?Zone $zone = null): array
    {
        $settings = app(SettingsRegistry::class);
        $methods = [];

        if ($settings->boolean('payments.pay_after_service', true) && ($zone === null || $zone->allowsCash())) {
            $methods[] = PaymentMethod::Cash->value;
        }

        foreach (app(GatewayManager::class)->configured() as $gateway) {
            $methods[] = $gateway->provider()->value;
        }

        if ($settings->boolean('payments.wallet_enabled', true)) {
            $methods[] = PaymentMethod::Wallet->value;
        }

        if ($settings->boolean('payments.offline_enabled', false) && BankAccount::query()->active()->exists()) {
            $methods[] = PaymentMethod::Offline->value;
        }

        return $methods;
    }

    /** The coarse booking-column enum for the chosen method. */
    public function paymentMethod(): PaymentMethod
    {
        return match ($this->chosenMethod()) {
            'cash' => PaymentMethod::Cash,
            'wallet' => PaymentMethod::Wallet,
            'offline' => PaymentMethod::Offline,
            default => PaymentMethod::Gateway,
        };
    }

    /** Which online gateway was picked, when one was. */
    public function gatewayProvider(): ?PaymentProvider
    {
        $provider = PaymentProvider::tryFrom($this->chosenMethod());

        return $provider?->isOnlineGateway() === true ? $provider : null;
    }

    public function chosenMethod(): string
    {
        return (string) $this->validated('payment_method');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_phone.required' => __('We need a phone number the professional can reach you on.'),
            'contact_phone.regex' => __('That phone number does not look right.'),
            'contact_phone_alt.regex' => __('That phone number does not look right.'),
            'photos.max' => __('You can attach up to 4 photos.'),
            'photos.*.max' => __('Each photo must be 4 MB or smaller.'),
        ];
    }
}
