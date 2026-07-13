<?php

namespace Database\Seeders;

use App\Domain\Banners\Enums\BannerPlacement;
use App\Domain\Coupons\Enums\CouponType;
use App\Models\Banner;
use App\Models\Coupon;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Demo coupons + banners (M12) so the storefront and checkout are clickable
 * straight after a fresh install. Banners ship without images — the home
 * page renders a gradient card from the title until the admin uploads one.
 */
class MarketingSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::query()->firstOrCreate(['code' => 'WELCOME10'], [
            'type' => CouponType::Percent,
            'value' => '10.00',
            'max_discount' => '200.00',
            'first_order_only' => true,
            'is_active' => true,
        ]);

        Coupon::query()->firstOrCreate(['code' => 'FLAT50'], [
            'type' => CouponType::Flat,
            'value' => '50.00',
            'min_order' => '500.00',
            'per_user_limit' => 2,
            'is_active' => true,
        ]);

        Banner::query()->firstOrCreate([
            'title' => 'Sparkling homes, happy weekends',
            'placement' => BannerPlacement::HomeHero->value,
        ], [
            'sort_order' => 0,
            'is_active' => true,
        ]);

        Banner::query()->firstOrCreate([
            'title' => 'Use WELCOME10 for 10% off your first booking',
            'placement' => BannerPlacement::HomeStrip->value,
        ], [
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // M19 social proof. Testimonials ship without photos — the card renders
        // the name alone until the admin picks one from the media library.
        $testimonials = [
            ['name' => 'Ananya Rao', 'role' => 'Customer, Bengaluru', 'quote' => 'Booked a deep clean on Friday, the team arrived on time and the flat has never looked better.'],
            ['name' => 'Vikram Shetty', 'role' => 'Customer, Mumbai', 'quote' => 'Live tracking meant I knew exactly when to head home. No waiting around all afternoon.'],
            ['name' => 'Priya Nair', 'role' => 'Customer, Kochi', 'quote' => 'Transparent pricing, GST invoice in the app, and the professional was genuinely skilled.'],
        ];

        foreach ($testimonials as $index => $data) {
            Testimonial::query()->firstOrCreate(
                ['name' => $data['name']],
                [...$data, 'rating' => 5, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
