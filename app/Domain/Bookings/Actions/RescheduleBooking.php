<?php

namespace App\Domain\Bookings\Actions;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\SlotGenerator;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RescheduleBooking
{
    public function __construct(
        private readonly SlotGenerator $slots,
        private readonly BookingStateMachine $machine,
        private readonly SettingsRegistry $settings,
    ) {}

    public function handle(Booking $booking, User $customer, CarbonImmutable $newSlot): Booking
    {
        if (! $booking->status->customerReschedulable()) {
            throw ValidationException::withMessages([
                'scheduled_at' => __('This booking can no longer be rescheduled.'),
            ]);
        }

        $cutoff = $booking->scheduled_at->toImmutable()
            ->subHours($this->settings->integer('booking.reschedule_min_hours', 2));

        if (CarbonImmutable::now()->greaterThan($cutoff)) {
            throw ValidationException::withMessages([
                'scheduled_at' => __('It is too close to the visit to reschedule. Please cancel instead or contact support.'),
            ]);
        }

        // Same grid, same clock as the original placement — the booking's zone
        // still names its city (M25).
        if (! $this->slots->isBookable($newSlot, $booking->zone?->city)) {
            throw ValidationException::withMessages([
                'scheduled_at' => __('That time slot is no longer available. Please pick another.'),
            ]);
        }

        return DB::transaction(function () use ($booking, $customer, $newSlot) {
            $previous = $booking->scheduled_at->toImmutable();

            $booking->update([
                'scheduled_at' => $newSlot,
                'slot_end_at' => $newSlot->addMinutes($this->slots->slotMinutes()),
            ]);

            if (in_array($booking->status, [BookingStatus::Assigned, BookingStatus::Accepted], true)) {
                // Release the provider; dispatch (M06) will re-offer the new slot.
                $this->machine->transition(
                    $booking,
                    BookingStatus::Searching,
                    BookingActor::Customer,
                    $customer,
                    __('Rescheduled by the customer.'),
                );
            } else {
                // No status change — record the slot move in the audit trail anyway.
                $booking->statusHistory()->create([
                    'from_status' => $booking->status->value,
                    'to_status' => $booking->status->value,
                    'actor_id' => $customer->id,
                    'actor_type' => BookingActor::Customer->value,
                    'note' => __('Rescheduled from :from to :to.', [
                        'from' => $previous->toDayDateTimeString(),
                        'to' => $newSlot->toDayDateTimeString(),
                    ]),
                    'created_at' => now(),
                ]);
            }

            return $booking;
        });
    }
}
