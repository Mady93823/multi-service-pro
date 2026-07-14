<?php

namespace App\Http\Concerns;

use App\Domain\Comms\Enums\NotificationChannel;
use App\Domain\Comms\Enums\NotificationEvent;
use Illuminate\Validation\Rule;

/**
 * The matrix payload, shared by the admin's defaults screen and the user's
 * opt-out screen (M23) — identical rows, one nullable owner.
 *
 * It lives outside App\Http\Requests because the arch suite requires every
 * class in that namespace to *be* a FormRequest, and a trait is not one.
 */
trait ResolvesNotificationPreferences
{
    /**
     * @return array<string, mixed>
     */
    public function preferenceRules(): array
    {
        return [
            'preferences' => ['present', 'array', 'max:200'],
            'preferences.*.event' => ['required', Rule::in(array_column(NotificationEvent::cases(), 'value'))],
            'preferences.*.channel' => ['required', Rule::in(array_column(NotificationChannel::cases(), 'value'))],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<array{event: string, channel: string, enabled: bool}>
     */
    public function preferences(): array
    {
        /** @var list<array{event: string, channel: string, enabled: bool}> $rows */
        $rows = array_values($this->safe()->array('preferences'));

        return $rows;
    }
}
