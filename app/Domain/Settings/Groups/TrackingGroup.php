<?php

namespace App\Domain\Settings\Groups;

class TrackingGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'tracking';
    }

    public function label(): string
    {
        return __('Live tracking');
    }

    public function description(): string
    {
        return __('How often the professional’s device reports its position, and how long the route is kept.');
    }

    public function keys(): array
    {
        return [
            'tracking.ping_interval_seconds',
            'tracking.min_move_meters',
            'tracking.max_accuracy_meters',
            'tracking.stale_after_seconds',
            'tracking.points_retention_days',
        ];
    }

    public function rules(array $input): array
    {
        return [
            'ping_interval_seconds' => ['required', 'integer', 'min:1', 'max:60'],
            'min_move_meters' => ['required', 'integer', 'min:0', 'max:500'],
            'max_accuracy_meters' => ['required', 'integer', 'min:10', 'max:1000'],
            'stale_after_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'points_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function values(): array
    {
        return [
            'ping_interval_seconds' => $this->settings->integer('tracking.ping_interval_seconds', 3),
            'min_move_meters' => $this->settings->integer('tracking.min_move_meters', 5),
            'max_accuracy_meters' => $this->settings->integer('tracking.max_accuracy_meters', 100),
            'stale_after_seconds' => $this->settings->integer('tracking.stale_after_seconds', 30),
            'points_retention_days' => $this->settings->integer('tracking.points_retention_days', 30),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('tracking.ping_interval_seconds', $data['ping_interval_seconds']);
        $this->settings->set('tracking.min_move_meters', $data['min_move_meters']);
        $this->settings->set('tracking.max_accuracy_meters', $data['max_accuracy_meters']);
        $this->settings->set('tracking.stale_after_seconds', $data['stale_after_seconds']);
        $this->settings->set('tracking.points_retention_days', $data['points_retention_days']);
    }
}
