<?php

namespace App\Domain\Bookings\Actions;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\CancellationFeeCalculator;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelBooking
{
    public function __construct(
        private readonly BookingStateMachine $machine,
        private readonly CancellationFeeCalculator $fees,
    ) {}

    /**
     * Customer cancellation: snapshots the (possibly zero) cancellation fee,
     * then transitions to cancelled_customer. Fee deduction from refunds is
     * a Phase 4 (M08) concern — cash bookings are unpaid anyway.
     */
    public function byCustomer(Booking $booking, User $customer, ?string $reason = null): Booking
    {
        if (! $booking->status->customerCancellable()) {
            throw ValidationException::withMessages([
                'status' => __('This booking can no longer be cancelled.'),
            ]);
        }

        $booking->cancellation_fee = $this->fees->feeFor($booking);

        return $this->machine->transition(
            $booking,
            BookingStatus::CancelledCustomer,
            BookingActor::Customer,
            $customer,
            $reason,
        );
    }

    /**
     * Admin cancellation (support tool): no fee, reason required for the
     * audit trail.
     */
    public function byAdmin(Booking $booking, User $admin, string $reason): Booking
    {
        $booking->cancellation_fee = '0.00';

        return $this->machine->transition(
            $booking,
            BookingStatus::CancelledAdmin,
            BookingActor::Admin,
            $admin,
            $reason,
        );
    }
}
