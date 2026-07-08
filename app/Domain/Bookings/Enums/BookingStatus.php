<?php

namespace App\Domain\Bookings\Enums;

/**
 * Canonical booking lifecycle (02-Modules M04). Every status change goes
 * through BookingStateMachine — never assign these to a booking directly.
 */
enum BookingStatus: string
{
    case PendingPayment = 'pending_payment';
    case Placed = 'placed';
    case Searching = 'searching';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case EnRoute = 'en_route';
    case Arrived = 'arrived';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case CancelledCustomer = 'cancelled_customer';
    case CancelledProvider = 'cancelled_provider';
    case CancelledAdmin = 'cancelled_admin';
    case Expired = 'expired';
    case FailedPayment = 'failed_payment';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed,
            self::CancelledCustomer,
            self::CancelledProvider,
            self::CancelledAdmin,
            self::Expired,
            self::FailedPayment => true,
            default => false,
        };
    }

    public function isCancellation(): bool
    {
        return match ($this) {
            self::CancelledCustomer, self::CancelledProvider, self::CancelledAdmin => true,
            default => false,
        };
    }

    /**
     * Statuses from which the customer may still cancel (fee rules apply
     * separately — see CancellationFeeCalculator).
     */
    public function customerCancellable(): bool
    {
        return match ($this) {
            self::PendingPayment,
            self::Placed,
            self::Searching,
            self::Assigned,
            self::Accepted,
            self::EnRoute,
            self::Arrived => true,
            default => false,
        };
    }

    /**
     * Statuses from which the customer may pick a new slot. Assigned/accepted
     * bookings return to searching on reschedule (provider released).
     */
    public function customerReschedulable(): bool
    {
        return match ($this) {
            self::Placed, self::Searching, self::Assigned, self::Accepted => true,
            default => false,
        };
    }
}
