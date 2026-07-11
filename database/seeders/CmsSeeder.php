<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Language;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * M14 demo content: footer pages, storefront FAQs, and the language rows.
 * The `en` row is load-bearing (the language manager treats it as the
 * protected source catalog); everything else is replaceable demo copy.
 */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        Language::query()->firstOrCreate(
            ['code' => Language::DEFAULT_CODE],
            ['name' => 'English', 'native_name' => 'English', 'is_active' => true],
        );

        Language::query()->firstOrCreate(
            ['code' => 'hi'],
            ['name' => 'Hindi', 'native_name' => 'हिन्दी', 'is_active' => false],
        );

        $pages = [
            [
                'title' => 'About Us',
                'slug' => 'about-us',
                'sort_order' => 1,
                'body' => "## Who we are\n\nWe connect trusted home-service professionals with customers in your city — cleaning, repairs, beauty and more, delivered at your doorstep.\n\n## Why book with us\n\n- Verified, background-checked professionals\n- Transparent upfront pricing with GST invoices\n- Live tracking from acceptance to arrival\n- Pay online or after the service",
            ],
            [
                'title' => 'Terms & Conditions',
                'slug' => 'terms-and-conditions',
                'sort_order' => 2,
                'body' => "## 1. Bookings\n\nA booking is confirmed once payment is authorised or pay-after-service is selected. Time slots are subject to professional availability.\n\n## 2. Cancellations\n\nCancellations inside the free-cancellation window are free of charge; later cancellations may attract a fee shown at the time of cancelling.\n\n## 3. Service guarantee\n\nIf you are unhappy with a completed service, raise it from the booking within 48 hours and we will make it right.",
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'sort_order' => 3,
                'body' => "## Data we collect\n\nYour name, contact details, service addresses and booking history — used only to deliver and improve the service.\n\n## Location\n\nProfessional location is shared with you only while a job is en route. Location history is pruned automatically.\n\n## Your rights\n\nYou can request an export or deletion of your account data at any time from support.",
            ],
        ];

        foreach ($pages as $data) {
            Page::query()->firstOrCreate(
                ['slug' => $data['slug']],
                [...$data, 'is_published' => true, 'show_in_footer' => true],
            );
        }

        $faqs = [
            ['question' => 'How do I book a service?', 'answer' => 'Pick a service, choose a convenient time slot and address, and confirm. A verified professional is assigned and you can track them live on the day.'],
            ['question' => 'Can I pay after the service is done?', 'answer' => 'Yes — pay-after-service lets you pay in cash or online once the job is completed, wherever it is enabled.'],
            ['question' => 'What if I need to cancel or reschedule?', 'answer' => 'You can cancel or reschedule from the booking screen. Cancelling inside the free-cancellation window costs nothing; later cancellations show any applicable fee before you confirm.'],
            ['question' => 'Are the professionals verified?', 'answer' => 'Every professional completes KYC document verification and approval before they can take a single job.'],
            ['question' => 'How do refunds work?', 'answer' => 'Approved refunds are credited to your in-app wallet instantly and can be used on any future booking.'],
        ];

        foreach ($faqs as $index => $data) {
            Faq::query()->firstOrCreate(
                ['question' => $data['question']],
                [...$data, 'is_active' => true, 'sort_order' => $index + 1],
            );
        }
    }
}
