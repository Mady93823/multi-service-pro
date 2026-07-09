<?php

namespace App\Http\Requests\Customer;

use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\GatewayManager;
use App\Domain\Settings\SettingsRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceBookingRequest extends FormRequest
{
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
            'payment_method' => ['required', Rule::in(self::availableMethods())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos' => ['array', 'max:4'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * Methods offered at checkout right now (M08): pay-after-service when
     * enabled, each configured gateway by name, wallet when enabled. These
     * are the form values; the booking column stores the coarse
     * cash|gateway|wallet enum via paymentMethod().
     *
     * @return list<string>
     */
    public static function availableMethods(): array
    {
        $settings = app(SettingsRegistry::class);
        $methods = [];

        if ($settings->boolean('payments.pay_after_service', true)) {
            $methods[] = PaymentMethod::Cash->value;
        }

        foreach (app(GatewayManager::class)->configured() as $gateway) {
            $methods[] = $gateway->provider()->value;
        }

        if ($settings->boolean('payments.wallet_enabled', true)) {
            $methods[] = PaymentMethod::Wallet->value;
        }

        return $methods;
    }

    /** The coarse booking-column enum for the chosen method. */
    public function paymentMethod(): PaymentMethod
    {
        return match ($this->chosenMethod()) {
            'cash' => PaymentMethod::Cash,
            'wallet' => PaymentMethod::Wallet,
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
            'photos.max' => __('You can attach up to 4 photos.'),
            'photos.*.max' => __('Each photo must be 4 MB or smaller.'),
        ];
    }
}
