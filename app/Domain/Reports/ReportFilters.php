<?php

namespace App\Domain\Reports;

use Illuminate\Support\Carbon;

/**
 * Date-range + status filter set shared by every admin report (M13). Kept as
 * a plain DTO so the queued CSV export can serialize it onto the job payload.
 */
class ReportFilters
{
    public function __construct(
        public readonly ?Carbon $from = null,
        public readonly ?Carbon $to = null,
        public readonly ?string $status = null,
    ) {}

    /**
     * @param  array{from?: string|null, to?: string|null, status?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        $status = $data['status'] ?? null;

        return new self(
            from: $from !== null && $from !== '' ? Carbon::parse($from)->startOfDay() : null,
            to: $to !== null && $to !== '' ? Carbon::parse($to)->endOfDay() : null,
            status: $status !== null && $status !== '' ? $status : null,
        );
    }

    /**
     * @return array{from: string|null, to: string|null, status: string|null}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'status' => $this->status,
        ];
    }
}
