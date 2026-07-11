<?php

namespace Database\Seeders;

use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo helpdesk data (M16): one live thread linked to a booking and one
 * closed ticket, so the queue, the thread view and the read-only state are
 * all clickable straight after `migrate:fresh --seed`.
 *
 * Adds ticket rows per run — tests must scope counts, never assert
 * `SupportTicket::count()` (landmine-6 family).
 */
class SupportSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->where('email', 'customer@demo.test')->first();
        $admin = User::query()->where('email', 'admin@demo.test')->first();

        if ($customer === null || $admin === null) {
            return;
        }

        if (SupportTicket::query()->where('user_id', $customer->id)->exists()) {
            return;
        }

        $booking = Booking::query()->where('customer_id', $customer->id)->latest('id')->first();

        $open = SupportTicket::query()->create([
            'code' => 'placeholder',
            'user_id' => $customer->id,
            'booking_id' => $booking?->id,
            'subject' => 'Cleaner arrived late and one room was skipped',
            'category' => TicketCategory::Booking,
            'priority' => TicketPriority::Normal,
            'status' => TicketStatus::Pending,
            'assigned_to' => $admin->id,
            'last_reply_at' => now()->subHours(2),
        ]);
        $open->update(['code' => sprintf('TKT-%06d', $open->id)]);

        SupportTicketMessage::query()->create([
            'ticket_id' => $open->id,
            'user_id' => $customer->id,
            'body' => 'The professional arrived 40 minutes after the slot started and the second bedroom was not cleaned. Can someone look into this?',
            'is_staff' => false,
            'created_at' => now()->subHours(3),
        ]);
        SupportTicketMessage::query()->create([
            'ticket_id' => $open->id,
            'user_id' => $admin->id,
            'body' => 'Sorry about that! We have raised this with the professional and will get back to you within 24 hours with a resolution.',
            'is_staff' => true,
            'created_at' => now()->subHours(2),
        ]);

        $closed = SupportTicket::query()->create([
            'code' => 'placeholder-2',
            'user_id' => $customer->id,
            'subject' => 'Invoice missing GSTIN',
            'category' => TicketCategory::Payment,
            'priority' => TicketPriority::Low,
            'status' => TicketStatus::Closed,
            'assigned_to' => $admin->id,
            'resolution_note' => 'Invoice re-issued with the company GSTIN.',
            'last_reply_at' => now()->subDays(3),
            'resolved_at' => now()->subDays(3),
            'closed_at' => now()->subDays(2),
        ]);
        $closed->update(['code' => sprintf('TKT-%06d', $closed->id)]);

        SupportTicketMessage::query()->create([
            'ticket_id' => $closed->id,
            'user_id' => $customer->id,
            'body' => 'My invoice does not show a GSTIN — I need it for my company reimbursement.',
            'is_staff' => false,
            'created_at' => now()->subDays(4),
        ]);
        SupportTicketMessage::query()->create([
            'ticket_id' => $closed->id,
            'user_id' => $admin->id,
            'body' => 'The GSTIN has been added in Settings and your invoice was re-issued — please download it again from the booking page.',
            'is_staff' => true,
            'created_at' => now()->subDays(3),
        ]);
    }
}
