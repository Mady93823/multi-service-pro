<?php

namespace App\Domain\Settings\Groups;

use Illuminate\Http\Request;

class ReviewsGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'reviews';
    }

    public function label(): string
    {
        return __('Reviews');
    }

    public function description(): string
    {
        return __('Customer ratings on completed bookings, shown on service pages.');
    }

    public function keys(): array
    {
        return ['reviews.enabled', 'reviews.max_photos'];
    }

    public function rules(Request $request): array
    {
        return [
            'reviews_enabled' => ['boolean'],
            'reviews_max_photos' => ['required', 'integer', 'min:0', 'max:10'],
        ];
    }

    public function values(): array
    {
        return [
            'reviews_enabled' => $this->settings->boolean('reviews.enabled', true),
            'reviews_max_photos' => $this->settings->integer('reviews.max_photos', 4),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('reviews.enabled', $this->toggle($data, 'reviews_enabled'));
        $this->settings->set('reviews.max_photos', $data['reviews_max_photos']);
    }
}
