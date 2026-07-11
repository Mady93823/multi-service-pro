<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Reports\Report;
use App\Domain\Reports\ReportFilters;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every booking in the range, money straight off the snapshot columns.
 *
 * @phpstan-import-type ReportRow from Report
 * @phpstan-import-type PaginatedRows from Report
 */
class BookingsReport implements Report
{
    public function slug(): string
    {
        return 'bookings';
    }

    public function title(): string
    {
        return __('Bookings report');
    }

    public function columns(): array
    {
        return [
            'code' => __('Code'),
            'placed_at' => __('Placed'),
            'scheduled_at' => __('Scheduled'),
            'customer' => __('Customer'),
            'provider' => __('Provider'),
            'status' => __('Status'),
            'payment_method' => __('Payment method'),
            'payment_status' => __('Payment status'),
            'subtotal' => __('Subtotal'),
            'discount' => __('Discount'),
            'tax' => __('Tax'),
            'total' => __('Total'),
        ];
    }

    public function statusOptions(): array
    {
        return array_column(BookingStatus::cases(), 'value');
    }

    /**
     * @return PaginatedRows
     */
    public function paginate(ReportFilters $filters, int $perPage = 25): array
    {
        $page = $this->query($filters)->paginate($perPage)->withQueryString();

        return [
            'data' => array_map(fn (Booking $booking): array => $this->row($booking), $page->items()),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'prev_page_url' => $page->previousPageUrl(),
            'next_page_url' => $page->nextPageUrl(),
        ];
    }

    public function rows(ReportFilters $filters): \Generator
    {
        foreach ($this->query($filters)->lazy(500) as $booking) {
            yield $this->row($booking);
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    /**
     * @return Builder<Booking>
     */
    private function query(ReportFilters $filters): Builder
    {
        return Booking::query()
            ->with(['customer:id,name', 'provider:id,name'])
            ->when($filters->from, fn (Builder $q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters->to, fn (Builder $q, $to) => $q->where('created_at', '<=', $to))
            ->when($filters->status, fn (Builder $q, string $status) => $q->where('status', $status))
            ->orderByDesc('created_at');
    }

    /**
     * @return ReportRow
     */
    private function row(Booking $booking): array
    {
        return [
            'code' => $booking->code,
            'placed_at' => $booking->created_at?->format('Y-m-d H:i'),
            'scheduled_at' => $booking->scheduled_at->format('Y-m-d H:i'),
            'customer' => $booking->customer?->name,
            'provider' => $booking->provider?->name,
            'status' => $booking->status->value,
            'payment_method' => $booking->payment_method->value,
            'payment_status' => $booking->payment_status->value,
            'subtotal' => (float) $booking->subtotal,
            'discount' => (float) $booking->discount,
            'tax' => (float) $booking->tax,
            'total' => (float) $booking->total,
        ];
    }
}
