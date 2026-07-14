<?php

namespace Database\Seeders;

use App\Domain\Banners\Enums\BannerPlacement;
use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Earnings\Actions\ProcessPayout;
use App\Domain\Earnings\Actions\RequestPayout;
use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Domain\Reviews\Actions\SubmitReview;
use App\Domain\Settings\SettingsRegistry;
use App\Domain\Users\Enums\Role;
use App\Domain\Zones\ZoneResolver;
use App\Models\Address;
use App\Models\Banner;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\Category;
use App\Models\PayoutAccount;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\Sponsor;
use App\Models\Subscriber;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Database\Factories\ProviderProfileFactory;
use Database\Seeders\Support\DemoGraphics;
use Database\Seeders\Support\DemoImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The showcase: what UrbanServe looks like as a business that has been trading
 * for three months, rather than as an application that was installed ten seconds
 * ago.
 *
 * This is **not** part of `DatabaseSeeder` and no test runs it. It is deliberately
 * heavy — a hundred and forty bookings, real photographs, a wallet ledger with a
 * history — because the questions a client actually asks are "what does the
 * dashboard look like?" and "does the revenue chart move?", and an empty chart
 * answers neither. Run it with `php artisan demo:seed`.
 *
 * Everything here goes through the real machinery: bookings move through
 * `BookingStateMachine`, so their history is real; completion fires the earnings
 * listener, so commission and payouts reconcile against the ledger the dashboard
 * reads; reviews go through `SubmitReview`, so provider ratings are recomputed
 * the way they will be in production. A seeder that wrote the numbers directly
 * would produce a demo that cannot be clicked.
 */
class ShowcaseSeeder extends Seeder
{
    /** Root/sub category slug → photo. A service with no photo of its own inherits its category's. */
    private const CATEGORY_PHOTOS = [
        'home-cleaning' => 'cleaning-deep',
        'bathroom-cleaning' => 'bathroom',
        'kitchen-cleaning' => 'kitchen',
        'full-home-cleaning' => 'cleaning-sofa',
        'salon-spa' => 'salon-hair',
        'salon-for-men' => 'barber',
        'salon-for-women' => 'salon-facial',
        'massage-spa' => 'massage',
        'ac-appliance-repair' => 'technician',
        'ac-service-repair' => 'technician',
        'refrigerator-repair' => 'refrigerator',
        'washing-machine-repair' => 'washing-machine',
        'plumbing' => 'plumbing',
        'tap-and-mixer-repair' => 'plumbing',
        'bathroom-fitting-installation' => 'bathroom',
        'electrician' => 'electrician',
        'painting' => 'painting',
        'carpentry' => 'carpentry',
        'pest-control' => 'cleaning-spray',
    ];

    /** Service slug → photo, where the category's own picture would be too generic. */
    private const SERVICE_PHOTOS = [
        'haircut-at-home-men' => 'barber',
        'beard-styling' => 'barber',
        'facial-cleanup' => 'salon-facial',
        'full-body-massage' => 'massage',
        'bathroom-deep-cleaning' => 'bathroom',
        'kitchen-deep-cleaning' => 'kitchen',
        'full-home-deep-cleaning-2bhk' => 'cleaning-deep',
    ];

    private const FALLBACK_PHOTO = 'handyman';

    public function run(): void
    {
        $images = app(DemoImages::class);
        $graphics = app(DemoGraphics::class);

        $this->command->info('Dressing the catalog…');
        $this->catalog($images);

        $this->command->info('Dressing the storefront…');
        $this->marketing($images, $graphics);
        $this->blog($images);

        $this->command->info('Hiring providers and signing up customers…');
        $providers = $this->providers();
        $customers = $this->customers($graphics);

        $this->command->info('Trading for 90 days…');
        $this->trade($providers, $customers);

        $this->command->info('Settling up…');
        $this->payouts($providers);
        $this->subscribers();
    }

    // ---------------------------------------------------------------- catalog

    private function catalog(DemoImages $images): void
    {
        foreach (Category::query()->get() as $category) {
            $key = self::CATEGORY_PHOTOS[$category->slug] ?? null;

            if ($key !== null) {
                $category->update(['image_path' => $images->publicCopy($key, 'categories')]);
            }
        }

        // Every service gets a picture. An imageless card in a grid of photographs
        // reads as a broken site, not as a service without a photo.
        foreach (Service::query()->with('category')->get() as $service) {
            $images->attach($service, $this->photoFor($service), 'images');
        }
    }

    private function photoFor(Service $service): string
    {
        if (isset(self::SERVICE_PHOTOS[$service->slug])) {
            return self::SERVICE_PHOTOS[$service->slug];
        }

        $category = $service->category;

        while ($category !== null) {
            if (isset(self::CATEGORY_PHOTOS[$category->slug])) {
                return self::CATEGORY_PHOTOS[$category->slug];
            }

            $category = $category->parent()->first();
        }

        return self::FALLBACK_PHOTO;
    }

    // -------------------------------------------------------------- marketing

    private function marketing(DemoImages $images, DemoGraphics $graphics): void
    {
        Banner::query()->delete();

        $banners = [
            ['Monsoon deep clean — flat 20% off', BannerPlacement::HomeHero, 'cleaning-deep'],
            ['Salon at home, on your schedule', BannerPlacement::HomeHero, 'salon-hair'],
            ['AC service before the summer rush', BannerPlacement::HomeStrip, 'technician'],
            ['Plumbing, fixed today', BannerPlacement::HomeStrip, 'plumbing'],
            ['Painting: free colour consultation', BannerPlacement::HomeStrip, 'painting'],
        ];

        foreach ($banners as $index => [$title, $placement, $photo]) {
            $banner = Banner::query()->create([
                'title' => $title,
                'link_url' => '/services',
                'placement' => $placement,
                'sort_order' => $index,
                'is_active' => true,
            ]);

            $images->attach($banner, $photo, 'image');
        }

        Testimonial::query()->delete();

        $testimonials = [
            ['Priya Nair', 'Product designer, Indiranagar', 'Booked a deep clean on Friday night and someone was at my door by nine on Saturday. The tracking map is genuinely useful — I stopped guessing and made coffee instead.', 5],
            ['Arjun Menon', 'Bengaluru', 'The AC technician showed me the gas pressure reading before and after. First time an appliance service has not felt like a negotiation.', 5],
            ['Sneha Reddy', 'Whitefield', 'Rebooked the same salon professional three times now. One tap from my last booking — that is the whole reason I stopped calling around.', 5],
            ['Vikram Shetty', 'HSR Layout', 'A pipe burst on a Sunday. Someone was here in forty minutes and the invoice matched the quote to the rupee.', 4],
        ];

        foreach ($testimonials as $index => [$name, $role, $quote, $rating]) {
            $testimonial = Testimonial::query()->create([
                'name' => $name,
                'role' => $role,
                'quote' => $quote,
                'rating' => $rating,
                'sort_order' => $index,
                'is_active' => true,
            ]);

            // A stock photo of a stranger, captioned as a named customer, is a
            // fabricated endorsement on a real person's face. Initials instead.
            $graphics->avatar($name);
            $testimonial->clearMediaCollection('avatar');
            app(AttachLibraryAsset::class)
                ->handle($testimonial, $graphics->avatar($name), 'avatar');
        }

        Sponsor::query()->delete();

        foreach (['Kotak', 'Zeta', 'Nimbus', 'Coral', 'Vayu'] as $index => $name) {
            $sponsor = Sponsor::query()->create([
                'name' => $name,
                'link_url' => 'https://example.com',
                'sort_order' => $index,
                'is_active' => true,
            ]);

            app(AttachLibraryAsset::class)
                ->handle($sponsor, $graphics->wordmark($name), 'logo');
        }
    }

    private function blog(DemoImages $images): void
    {
        $covers = ['cleaning-deep', 'technician', 'salon-hair', 'plumbing', 'painting'];

        foreach (BlogPost::query()->orderBy('id')->get() as $index => $post) {
            $images->attach($post, $covers[$index % count($covers)], BlogPost::COLLECTION);
        }
    }

    // ----------------------------------------------------------------- people

    /** @return list<User> */
    private function providers(): array
    {
        $roots = Category::query()->active()->root()->orderBy('sort_order')->pluck('id')->all();

        $people = [
            ['Suresh Iyer', 'suresh@demo.test', 'Deep-cleaning specialist. 11 years, 2,000+ homes.', 11],
            ['Meena Raghavan', 'meena@demo.test', 'Senior beautician. Trained at Lakmé, home salon since 2016.', 9],
            ['Imran Sheikh', 'imran@demo.test', 'HVAC technician. Split and window AC, all major brands.', 7],
            ['Lakshmi Devi', 'lakshmi@demo.test', 'Home cleaning and pest control. Punctual, thorough, quiet.', 6],
            ['Anil Prasad', 'anil@demo.test', 'Licensed electrician and plumber. Same-day emergency work.', 13],
            ['Farhan Ali', 'farhan@demo.test', 'Painting and carpentry. Small jobs done properly.', 8],
        ];

        $providers = [];

        foreach ($people as $index => [$name, $email, $bio, $years]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );
            $user->syncRoles([Role::Provider->value]);

            if (! $user->providerProfile()->exists()) {
                $profile = new ProviderProfile([
                    'bio' => $bio,
                    'experience_years' => $years,
                    // Scattered across Bengaluru so the dispatch map is not a single dot.
                    'base_lat' => 12.9716 + (($index - 2) * 0.012),
                    'base_lng' => 77.5946 + (($index - 3) * 0.014),
                    'service_radius_km' => 15,
                    'working_hours' => ProviderProfileFactory::defaultWorkingHours(),
                ]);
                $profile->user()->associate($user);
                $profile->approval_status = ProviderApprovalStatus::Approved;
                $profile->is_online = true;
                $profile->save();

                $profile->categories()->sync(array_slice($roots, $index % max(count($roots) - 1, 1), 2));
            }

            $providers[] = $user;
        }

        // The base seed already approved a provider or two of its own. They have
        // to trade as well, or the leaderboard carries a row reading 0.00 ★ and
        // 0 jobs — an approved professional who has never been booked reads as a
        // broken dispatcher, not as a new joiner.
        $existing = User::query()
            ->whereHas('providerProfile', fn ($query) => $query->where('approval_status', ProviderApprovalStatus::Approved->value))
            ->whereNotIn('id', array_map(fn (User $provider): int => $provider->id, $providers))
            ->get();

        foreach ($existing as $user) {
            $providers[] = $user;
        }

        return $providers;
    }

    /** @return list<User> */
    private function customers(DemoGraphics $graphics): array
    {
        $resolver = app(ZoneResolver::class);
        $points = $this->addressPoints();

        $people = [
            ['Ananya Kulkarni', 'ananya@demo.test'], ['Rahul Bhatt', 'rahul@demo.test'],
            ['Divya Menon', 'divya@demo.test'], ['Karthik Rao', 'karthik@demo.test'],
            ['Neha Gupta', 'neha@demo.test'], ['Siddharth Jain', 'sid@demo.test'],
            ['Fatima Khan', 'fatima@demo.test'], ['Rohan Desai', 'rohan@demo.test'],
            ['Ishita Sharma', 'ishita@demo.test'], ['Manish Verma', 'manish@demo.test'],
            ['Aditi Pillai', 'aditi@demo.test'], ['Gaurav Malhotra', 'gaurav@demo.test'],
        ];

        $customers = [];

        foreach ($people as $index => [$name, $email]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );
            $user->syncRoles([Role::Customer->value]);

            if (! $user->addresses()->exists()) {
                [$lat, $lng, $line1, $area, $pin] = $points[$index % count($points)];

                $user->addresses()->create([
                    'label' => $index % 3 === 0 ? 'work' : 'home',
                    'line1' => $line1,
                    'line2' => $area,
                    'city' => 'Bengaluru',
                    'postal_code' => $pin,
                    'lat' => $lat,
                    'lng' => $lng,
                    'zone_id' => $resolver->resolve($lat, $lng)?->id,
                    'is_default' => true,
                ]);
            }

            // Gives the wallet ledger something to show, and the referral card a number.
            $graphics->avatar($name);

            $customers[] = $user;
        }

        return $customers;
    }

    /**
     * Real Bengaluru addresses, jittered around the seeded zones so the pins land
     * inside a polygon rather than in a lake.
     *
     * @return list<array{0: float, 1: float, 2: string, 3: string, 4: string}>
     */
    private function addressPoints(): array
    {
        $zones = Zone::query()->whereHas('city', fn ($q) => $q->where('slug', 'bengaluru'))->get();
        $centres = [];

        foreach ($zones as $zone) {
            $ring = $zone->geojson['coordinates'][0] ?? [];

            if ($ring === []) {
                continue;
            }

            $lat = array_sum(array_column($ring, 1)) / count($ring);
            $lng = array_sum(array_column($ring, 0)) / count($ring);
            $centres[] = [$lat, $lng];
        }

        if ($centres === []) {
            $centres[] = [12.9758, 77.6096];
        }

        $streets = [
            ['12 Church Street', 'Ashok Nagar', '560001'],
            ['48 100 Feet Road', 'Indiranagar', '560038'],
            ['7 Residency Road', 'Shanthala Nagar', '560025'],
            ['91 Brigade Road', 'Shivaji Nagar', '560001'],
            ['22 Lavelle Road', 'Ashok Nagar', '560001'],
            ['5 Cunningham Road', 'Vasanth Nagar', '560052'],
        ];

        $points = [];

        foreach ($streets as $index => [$line1, $area, $pin]) {
            [$lat, $lng] = $centres[$index % count($centres)];
            // Small jitter: enough to spread the pins, small enough to stay in the zone.
            $points[] = [
                round($lat + (($index % 3) - 1) * 0.003, 6),
                round($lng + (($index % 2) - 0.5) * 0.004, 6),
                $line1,
                $area,
                $pin,
            ];
        }

        return $points;
    }

    // ------------------------------------------------------------------ trade

    /**
     * @param  list<User>  $providers
     * @param  list<User>  $customers
     */
    private function trade(array $providers, array $customers): void
    {
        $services = Service::query()->active()->get()->all();
        $machine = app(BookingStateMachine::class);
        $reviews = app(SubmitReview::class);

        $comments = [
            5 => [
                'On time, polite, and the place looked better than when I moved in.',
                'Brought his own equipment and left the bathroom spotless. Booking again.',
                'Explained what was wrong before touching anything. No upselling.',
                'Third time with the same professional. Consistently excellent.',
                'Arrived within the slot, finished early, cleaned up after himself.',
            ],
            4 => [
                'Good work. Ran about twenty minutes late but called ahead.',
                'Thorough job, though I had to ask twice about the balcony.',
                'Happy with the result. The app kept me updated the whole time.',
            ],
            3 => [
                'Job was done, but it took longer than the estimate.',
                'Fine overall. Would have liked a heads-up about the extra charge.',
            ],
        ];

        $today = CarbonImmutable::now()->startOfDay();

        // 90 days back. Weekends are busier — the dashboard chart should have a
        // shape, not a flat line, because that is what an operator recognises.
        for ($daysAgo = 90; $daysAgo >= 1; $daysAgo--) {
            $day = $today->subDays($daysAgo);
            $count = in_array($day->dayOfWeek, [0, 6], true) ? random_int(2, 3) : random_int(1, 2);

            for ($n = 0; $n < $count; $n++) {
                $customer = $customers[array_rand($customers)];
                $provider = $providers[array_rand($providers)];
                $service = $services[array_rand($services)];
                $address = $customer->addresses()->first();

                if ($address === null) {
                    continue;
                }

                $roll = random_int(1, 100);
                $method = match (true) {
                    $roll <= 45 => PaymentMethod::Cash,
                    $roll <= 80 => PaymentMethod::Gateway,
                    default => PaymentMethod::Wallet,
                };

                $booking = $this->makeBooking(
                    $customer,
                    $address,
                    $service,
                    $day->setTime(random_int(8, 18), [0, 30][random_int(0, 1)]),
                    $method,
                );

                // One in twelve is cancelled. A demo with no cancellations is a
                // demo of a business that has never had a bad day.
                $cancelled = random_int(1, 12) === 1;

                if ($method !== PaymentMethod::Cash) {
                    $this->settle($booking, $method);
                }

                $machine->initialize($booking, BookingActor::Customer, $customer);

                if ($cancelled) {
                    $machine->transition($booking, BookingStatus::CancelledCustomer, BookingActor::Customer, $customer, 'Plans changed.');

                    continue;
                }

                $booking->provider_id = $provider->id;

                foreach ([
                    BookingStatus::Searching, BookingStatus::Assigned, BookingStatus::Accepted,
                    BookingStatus::EnRoute, BookingStatus::Arrived, BookingStatus::InProgress,
                    BookingStatus::Completed,
                ] as $status) {
                    $machine->transition($booking, $status, BookingActor::System, null, 'Demo history.');
                }

                if ($method === PaymentMethod::Cash) {
                    $booking->update(['payment_status' => PaymentStatus::Paid]);
                }

                // The hold window would have elapsed on a job this old, and the
                // seeder cannot wait for `earnings:release` to run.
                if ($daysAgo > 7) {
                    $booking->earnings()->update([
                        'status' => EarningStatus::Available->value,
                        'available_at' => $day->addDay(),
                    ]);
                }

                // Two in three finished jobs get reviewed — the real-world rate is
                // nowhere near everyone, and a 5.0 average across the board looks
                // seeded because it is.
                if (random_int(1, 3) <= 2) {
                    $rating = match (true) {
                        random_int(1, 10) <= 7 => 5,
                        random_int(1, 10) <= 8 => 4,
                        default => 3,
                    };

                    $reviews->handle($booking, $rating, $comments[$rating][array_rand($comments[$rating])]);
                }

                $this->backdate($booking, $day);
            }
        }

        $this->upcoming($providers, $customers, $services, $machine);
    }

    /**
     * Move the whole paper trail of one booking back to the day it happened.
     *
     * Ninety days of trade were just created in ninety seconds, so every row's
     * `created_at` says *now*. That is not cosmetic: `DashboardMetrics` groups
     * bookings and the earnings ledger **by `created_at`**, so without this the
     * revenue chart is a single spike on today and ninety days of flat line —
     * which is exactly what a demo dataset looks like, and precisely what the
     * client must not see.
     *
     * The row's own clock is corrected here rather than by writing the timestamps
     * up front, because every one of these rows was written by the real machinery
     * (state machine, payment, earnings listener, review action) and that
     * machinery stamps `now()` — as it should.
     */
    private function backdate(Booking $booking, CarbonImmutable $placedAt): void
    {
        $finishedAt = $placedAt->addHours(random_int(2, 8));

        DB::table('bookings')->where('id', $booking->id)
            ->update(['created_at' => $placedAt, 'updated_at' => $finishedAt]);

        DB::table('booking_items')->where('booking_id', $booking->id)
            ->update(['created_at' => $placedAt, 'updated_at' => $placedAt]);

        // The history is the one place a client will look for the timeline, so
        // the steps are spread across the visit rather than stacked on one minute.
        $history = DB::table('booking_status_history')
            ->where('booking_id', $booking->id)->orderBy('id')->pluck('id')->all();

        foreach ($history as $step => $id) {
            DB::table('booking_status_history')->where('id', $id)
                ->update(['created_at' => $placedAt->addMinutes($step * random_int(8, 25))]);
        }

        DB::table('payments')->where('booking_id', $booking->id)
            ->update(['created_at' => $placedAt, 'updated_at' => $finishedAt]);

        DB::table('earnings')->where('booking_id', $booking->id)
            ->update(['created_at' => $finishedAt, 'updated_at' => $finishedAt]);

        DB::table('wallet_transactions')
            ->where('reference_type', Booking::class)
            ->where('reference_id', $booking->id)
            ->update(['created_at' => $placedAt]);

        // People review the day after, not the minute after.
        DB::table('reviews')->where('booking_id', $booking->id)
            ->update(['created_at' => $finishedAt->addDay(), 'updated_at' => $finishedAt->addDay()]);
    }

    /**
     * The live board: what an operator sees when they open the panel this morning.
     *
     * @param  list<User>  $providers
     * @param  list<User>  $customers
     * @param  list<Service>  $services
     */
    private function upcoming(array $providers, array $customers, array $services, BookingStateMachine $machine): void
    {
        $now = CarbonImmutable::now();

        foreach (range(1, 8) as $index) {
            $customer = $customers[array_rand($customers)];
            $address = $customer->addresses()->first();

            if ($address === null) {
                continue;
            }

            $booking = $this->makeBooking(
                $customer,
                $address,
                $services[array_rand($services)],
                $now->addDays(intdiv($index, 2) + 1)->setTime(random_int(9, 17), 0),
                PaymentMethod::Cash,
            );

            $machine->initialize($booking, BookingActor::Customer, $customer);

            // A spread of live states: placed, out for dispatch, and accepted.
            if ($index % 3 !== 0) {
                $machine->transition($booking, BookingStatus::Searching, BookingActor::System, null, 'Demo history.');
            }

            if ($index % 3 === 1) {
                $booking->provider_id = $providers[array_rand($providers)]->id;
                $machine->transition($booking, BookingStatus::Assigned, BookingActor::System, null, 'Demo history.');
                $machine->transition($booking, BookingStatus::Accepted, BookingActor::Provider, null, 'Demo history.');
            }
        }

        // And two that were never paid for, so the Payments screen has something
        // waiting and `bookings:expire-unpaid` has something to eat.
        foreach (range(1, 2) as $index) {
            $customer = $customers[array_rand($customers)];
            $address = $customer->addresses()->first();

            if ($address === null) {
                continue;
            }

            $booking = $this->makeBooking(
                $customer,
                $address,
                $services[array_rand($services)],
                $now->addDays(2)->setTime(12, 0),
                PaymentMethod::Gateway,
            );

            $booking->update(['status' => BookingStatus::PendingPayment]);
            $booking->payments()->create([
                'gateway' => PaymentProvider::Razorpay,
                'gateway_ref' => 'order_demo_'.$booking->id,
                'amount' => $booking->total,
                'currency' => 'INR',
                'status' => PaymentState::Initiated,
            ]);
        }
    }

    private function settle(Booking $booking, PaymentMethod $method): void
    {
        $booking->payments()->create([
            'gateway' => $method === PaymentMethod::Wallet ? PaymentProvider::Wallet : PaymentProvider::Razorpay,
            'gateway_ref' => $method === PaymentMethod::Wallet ? null : 'pay_demo_'.$booking->id,
            'amount' => $booking->total,
            'currency' => 'INR',
            'status' => PaymentState::Captured,
            'captured_at' => $booking->scheduled_at,
        ]);

        $booking->update(['payment_status' => PaymentStatus::Paid]);
    }

    private function makeBooking(
        User $customer,
        Address $address,
        Service $service,
        CarbonImmutable $scheduledAt,
        PaymentMethod $paymentMethod,
    ): Booking {
        $settings = app(SettingsRegistry::class);

        $subtotal = (float) $service->price;
        // One booking in six carries a coupon, so the discount column and the
        // reports are not a wall of zeroes.
        $discount = random_int(1, 6) === 1 ? round($subtotal * 0.1, 2) : 0.0;
        $base = $subtotal - $discount;
        $percent = $settings->decimal('payments.tax_percent', 18.0);
        $tax = round($base * $percent / 100, 2);
        $cgst = round($tax / 2, 2);

        $booking = Booking::query()->create([
            'code' => 'SEED-'.uniqid(),
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'address_snapshot' => [
                'label' => $address->label->value,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'postal_code' => $address->postal_code,
                'lat' => (float) $address->lat,
                'lng' => (float) $address->lng,
            ],
            'zone_id' => $address->zone_id,
            'scheduled_at' => $scheduledAt,
            'slot_end_at' => $scheduledAt->addMinutes($settings->integer('booking.slot_minutes', 60)),
            'status' => BookingStatus::Placed,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'addon_total' => '0.00',
            'discount' => number_format($discount, 2, '.', ''),
            'tax' => number_format($tax, 2, '.', ''),
            'tax_breakup' => [
                'label' => $settings->string('payments.tax_label', 'GST'),
                'percent' => $percent,
                'cgst' => $cgst,
                'sgst' => round($tax - $cgst, 2),
                'igst' => 0.0,
            ],
            'total' => number_format($base + $tax, 2, '.', ''),
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => $paymentMethod,
            'job_otp_code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
        ]);

        $booking->update([
            'code' => sprintf(
                '%s-%s-%06d',
                $settings->string('booking.code_prefix', 'BK') ?: 'BK',
                $scheduledAt->format('Y'),
                $booking->id,
            ),
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'name_snapshot' => $service->name,
            'price_snapshot' => $service->price,
            'qty' => 1,
            'addons_snapshot' => [],
        ]);

        return $booking;
    }

    // ---------------------------------------------------------------- payouts

    /** @param list<User> $providers */
    private function payouts(array $providers): void
    {
        $admin = User::query()->where('email', 'admin@demo.test')->first()
            ?? User::query()->whereHas('roles', fn ($query) => $query->where('name', Role::Admin->value))->firstOrFail();

        $requestPayout = app(RequestPayout::class);
        $processPayout = app(ProcessPayout::class);

        // Every provider gets somewhere to be paid; the first two actually claim.
        // One is settled and one is still waiting on the admin, so the payout
        // queue has both a decision to make and a history to show.
        foreach ($providers as $index => $provider) {
            $account = PayoutAccount::query()->create([
                'provider_id' => $provider->id,
                'type' => 'upi',
                'label' => __('UPI'),
                'upi_id' => strtolower(explode('@', (string) $provider->email)[0]).'@okhdfc',
                'is_default' => true,
                'is_verified' => true,
                'verified_at' => now()->subDays(20),
            ]);

            if ($index > 1) {
                continue;
            }

            // A provider whose released balance is zero (or negative, because the
            // cash they took at the door outweighs it) simply has nothing to
            // claim — RequestPayout says so, and that is not an error here.
            try {
                $payout = $requestPayout->handle($provider, $account);
            } catch (ValidationException) {
                continue;
            }

            if ($index === 0) {
                $processPayout->markPaid($payout, $admin, 'UTR'.random_int(100000000, 999999999));
            }
        }
    }

    private function subscribers(): void
    {
        foreach (range(1, 34) as $index) {
            $subscriber = Subscriber::query()->firstOrCreate(
                ['email' => "subscriber{$index}@example.com"],
                ['source' => $index % 3 === 0 ? 'footer' : 'popup'],
            );

            // The signup moment *is* `created_at`; a footer subscriber who joined
            // this morning and one who joined in week two should not share a date.
            $subscriber->forceFill(['created_at' => now()->subDays(random_int(1, 80))])->save();
        }
    }
}
