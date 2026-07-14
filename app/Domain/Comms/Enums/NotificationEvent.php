<?php

namespace App\Domain\Comms\Enums;

/**
 * Every notification the platform can send (M23).
 *
 * The value is the key an `email_templates` row and a `notification_preferences`
 * row hang off, so it is a wire contract: renaming a case orphans an admin's
 * template. Each notification class in App\Notifications names its event, which
 * is what lets one channel resolver decide delivery for all of them.
 */
enum NotificationEvent: string
{
    case BookingStatus = 'booking_status';
    case JobOffer = 'job_offer';
    case OfflinePaymentRejected = 'offline_payment_rejected';
    case ReferralReward = 'referral_reward';
    case ReviewReceived = 'review_received';
    case TicketReply = 'ticket_reply';
    case TicketStatus = 'ticket_status';
    case Announcement = 'announcement';

    public function label(): string
    {
        return match ($this) {
            self::BookingStatus => __('Booking update'),
            self::JobOffer => __('New job offer'),
            self::OfflinePaymentRejected => __('Bank transfer rejected'),
            self::ReferralReward => __('Referral reward'),
            self::ReviewReceived => __('New review'),
            self::TicketReply => __('Support reply'),
            self::TicketStatus => __('Ticket status change'),
            self::Announcement => __('Announcement'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BookingStatus => __('Sent to the customer every time their booking moves.'),
            self::JobOffer => __('Sent to a professional when a job is offered to them.'),
            self::OfflinePaymentRejected => __('Sent to the customer when you reject their bank transfer.'),
            self::ReferralReward => __('Sent to the referrer when their referral earns a reward.'),
            self::ReviewReceived => __('Sent to the professional when a customer reviews their work.'),
            self::TicketReply => __('Sent when a new message lands on a support ticket.'),
            self::TicketStatus => __('Sent when a support ticket is resolved or closed.'),
            self::Announcement => __('Sent by you from the announcement composer.'),
        };
    }

    /**
     * The placeholders an admin may use in this event's email template.
     *
     * These are the keys every notification of this event puts in its payload
     * (see PlatformNotification::variables()). An unknown placeholder renders
     * as nothing rather than as itself — a template is never a leak of internals.
     *
     * @return list<string>
     */
    public function variables(): array
    {
        $common = ['app_name', 'name', 'title', 'body', 'url'];

        return match ($this) {
            self::BookingStatus => [...$common, 'code', 'status'],
            self::JobOffer => [...$common, 'code'],
            self::OfflinePaymentRejected => [...$common, 'code', 'reason'],
            self::ReferralReward => [...$common, 'amount'],
            self::ReviewReceived => [...$common, 'rating'],
            self::TicketReply, self::TicketStatus => [...$common, 'code', 'subject'],
            self::Announcement => $common,
        };
    }

    /**
     * Stand-in values for the preview and the test send — the admin sees what a
     * recipient would see, without a real booking behind it.
     *
     * @return array<string, string>
     */
    public function sample(): array
    {
        $sample = [
            'app_name' => (string) config('app.name'),
            'name' => __('Sample recipient'),
            'title' => $this->label(),
            'body' => $this->description(),
            'url' => url('/'),
            'code' => 'BK-2026-000123',
            'status' => 'completed',
            'reason' => __('No transfer found with that reference.'),
            'amount' => '100.00',
            'rating' => '5',
            'subject' => __('Sample subject'),
        ];

        return array_intersect_key($sample, array_flip($this->variables()));
    }

    /**
     * Shipped delivery defaults, used until an admin says otherwise.
     *
     * `database` and `broadcast` are deliberately absent: the in-app feed and
     * the live bell are the product, not a channel, and turning them off would
     * leave a user with no record of what happened to their money.
     *
     * SMS is off everywhere — it costs the operator real money per message and
     * no install ships with a gateway configured.
     *
     * @return array<string, bool> channel value => enabled
     */
    public function defaults(): array
    {
        return match ($this) {
            // Time-critical and noisy: a professional wants the push, not the inbox.
            self::JobOffer => [
                NotificationChannel::Mail->value => false,
                NotificationChannel::Sms->value => false,
                NotificationChannel::Fcm->value => true,
            ],
            default => [
                NotificationChannel::Mail->value => true,
                NotificationChannel::Sms->value => false,
                NotificationChannel::Fcm->value => true,
            ],
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
