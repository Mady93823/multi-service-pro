<?php

namespace Database\Seeders;

use App\Domain\Catalog\Enums\PricingType;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Demo catalog: 6 root categories with sub-categories, services,
     * add-ons and cross-sell links. Idempotent (keyed by slug).
     */
    public function run(): void
    {
        foreach ($this->catalog() as $rootIndex => $rootData) {
            $root = Category::withTrashed()->updateOrCreate(
                ['slug' => Str::slug($rootData['name'])],
                [
                    'name' => $rootData['name'],
                    'type' => $rootData['type'] ?? 'service',
                    'sort_order' => $rootIndex,
                    'is_active' => true,
                    'deleted_at' => null,
                ],
            );

            foreach ($rootData['children'] ?? [] as $childIndex => $childData) {
                $child = Category::withTrashed()->updateOrCreate(
                    ['slug' => Str::slug($childData['name'])],
                    [
                        'name' => $childData['name'],
                        'parent_id' => $root->id,
                        'type' => $root->type->value,
                        'sort_order' => $childIndex,
                        'is_active' => true,
                        'deleted_at' => null,
                    ],
                );

                $this->seedServices($child, $childData['services'] ?? []);
            }

            $this->seedServices($root, $rootData['services'] ?? []);
        }

        $this->linkRelated();
    }

    /**
     * @param  list<array{name: string, price: int, pricing_type?: PricingType, duration?: int|null, featured?: bool, short?: string, description?: string, addons?: list<array{name: string, price: int}>}>  $services
     */
    protected function seedServices(Category $category, array $services): void
    {
        foreach ($services as $index => $data) {
            $service = Service::withTrashed()->updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'category_id' => $category->id,
                    'name' => $data['name'],
                    'short_description' => $data['short'] ?? null,
                    'description' => $data['description'] ?? null,
                    'pricing_type' => $data['pricing_type'] ?? PricingType::Fixed,
                    'price' => $data['price'],
                    'duration_minutes' => $data['duration'] ?? 60,
                    'is_featured' => $data['featured'] ?? false,
                    'is_active' => true,
                    'sort_order' => $index,
                    'deleted_at' => null,
                ],
            );

            foreach ($data['addons'] ?? [] as $addon) {
                $service->addons()->updateOrCreate(
                    ['name' => $addon['name']],
                    ['price' => $addon['price'], 'is_active' => true],
                );
            }
        }
    }

    protected function linkRelated(): void
    {
        $links = [
            'bathroom-deep-cleaning' => ['kitchen-deep-cleaning', 'full-home-deep-cleaning-2bhk'],
            'ac-deep-service-split' => ['ac-gas-refill'],
            'fan-installation' => ['switchboard-repair'],
            'haircut-at-home-men' => ['beard-styling'],
            'tap-and-mixer-repair' => ['bathroom-fitting-installation'],
        ];

        foreach ($links as $slug => $relatedSlugs) {
            $service = Service::query()->where('slug', $slug)->first();

            if ($service === null) {
                continue;
            }

            $relatedIds = Service::query()->whereIn('slug', $relatedSlugs)->pluck('id');
            $service->related()->syncWithoutDetaching($relatedIds);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function catalog(): array
    {
        return [
            [
                'name' => 'Home Cleaning',
                'children' => [
                    [
                        'name' => 'Bathroom Cleaning',
                        'services' => [
                            [
                                'name' => 'Bathroom Deep Cleaning',
                                'price' => 499,
                                'duration' => 60,
                                'featured' => true,
                                'short' => 'Scrubbing, descaling and sanitising of one full bathroom.',
                                'addons' => [
                                    ['name' => 'Extra bathroom', 'price' => 399],
                                    ['name' => 'Exhaust fan degrease', 'price' => 149],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Kitchen Cleaning',
                        'services' => [
                            [
                                'name' => 'Kitchen Deep Cleaning',
                                'price' => 999,
                                'duration' => 120,
                                'short' => 'Degreasing of counters, tiles, sink and exterior of appliances.',
                                'addons' => [
                                    ['name' => 'Chimney degrease', 'price' => 299],
                                    ['name' => 'Fridge interior cleaning', 'price' => 249],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Full Home Cleaning',
                        'services' => [
                            [
                                'name' => 'Full Home Deep Cleaning (2BHK)',
                                'price' => 2499,
                                'duration' => 240,
                                'featured' => true,
                                'short' => 'Complete deep clean of a 2-bedroom home including balconies.',
                                'addons' => [
                                    ['name' => 'Sofa shampooing (5 seats)', 'price' => 599],
                                    ['name' => 'Mattress cleaning', 'price' => 399],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Salon & Spa',
                'children' => [
                    [
                        'name' => 'Salon for Men',
                        'services' => [
                            [
                                'name' => 'Haircut at Home (Men)',
                                'price' => 249,
                                'duration' => 30,
                                'featured' => true,
                                'short' => 'Professional haircut at your doorstep with sanitised tools.',
                            ],
                            [
                                'name' => 'Beard Styling',
                                'price' => 149,
                                'duration' => 20,
                                'short' => 'Beard trim and shaping.',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Salon for Women',
                        'services' => [
                            [
                                'name' => 'Facial & Cleanup',
                                'price' => 799,
                                'duration' => 60,
                                'featured' => true,
                                'short' => 'Salon-grade facial with cleanup at home.',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Massage & Spa',
                        'services' => [
                            [
                                'name' => 'Full Body Massage',
                                'price' => 1299,
                                'duration' => 90,
                                'short' => 'Relaxing full-body massage by a trained therapist.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'AC & Appliance Repair',
                'children' => [
                    [
                        'name' => 'AC Service & Repair',
                        'services' => [
                            [
                                'name' => 'AC Deep Service (Split)',
                                'price' => 599,
                                'duration' => 60,
                                'featured' => true,
                                'short' => 'Jet-spray deep service for split AC indoor and outdoor units.',
                                'addons' => [
                                    ['name' => 'Additional AC unit', 'price' => 499],
                                ],
                            ],
                            [
                                'name' => 'AC Gas Refill',
                                'price' => 2499,
                                'pricing_type' => PricingType::Inspection,
                                'duration' => 90,
                                'short' => 'Gas top-up after leak inspection; final quote on site.',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Refrigerator Repair',
                        'services' => [
                            [
                                'name' => 'Refrigerator Check-up',
                                'price' => 299,
                                'pricing_type' => PricingType::Inspection,
                                'duration' => 45,
                                'short' => 'Diagnosis visit; repair quote after inspection.',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Washing Machine Repair',
                        'services' => [
                            [
                                'name' => 'Washing Machine Repair',
                                'price' => 349,
                                'pricing_type' => PricingType::Inspection,
                                'duration' => 45,
                                'short' => 'Diagnosis visit; repair quote after inspection.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Plumbing',
                'services' => [
                    [
                        'name' => 'Tap and Mixer Repair',
                        'price' => 199,
                        'pricing_type' => PricingType::Inspection,
                        'duration' => 30,
                        'short' => 'Leaky tap or mixer repair; parts charged after inspection.',
                    ],
                    [
                        'name' => 'Bathroom Fitting Installation',
                        'price' => 299,
                        'pricing_type' => PricingType::Hourly,
                        'duration' => 60,
                        'short' => 'Installation of showers, taps and accessories.',
                    ],
                    [
                        'name' => 'Water Tank Cleaning',
                        'price' => 999,
                        'duration' => 90,
                        'featured' => true,
                        'short' => 'Mechanised cleaning and disinfection of overhead tanks.',
                    ],
                ],
            ],
            [
                'name' => 'Electrician',
                'services' => [
                    [
                        'name' => 'Fan Installation',
                        'price' => 149,
                        'duration' => 30,
                        'short' => 'Ceiling or wall fan installation.',
                    ],
                    [
                        'name' => 'Switchboard Repair',
                        'price' => 199,
                        'pricing_type' => PricingType::Inspection,
                        'duration' => 30,
                        'short' => 'Switchboard and socket repair after diagnosis.',
                    ],
                    [
                        'name' => 'House Wiring Check',
                        'price' => 499,
                        'pricing_type' => PricingType::Hourly,
                        'duration' => 120,
                        'short' => 'Full-house electrical safety inspection.',
                    ],
                ],
            ],
            [
                // The dedicated Event Management surface (/events) — same
                // booking machinery, listed on its own page.
                'name' => 'Event Management',
                'type' => 'event',
                'children' => [
                    [
                        'name' => 'Wedding & Marriage',
                        'services' => [
                            [
                                'name' => 'Wedding Decoration Package',
                                'price' => 14999,
                                'duration' => 480,
                                'featured' => true,
                                'short' => 'Stage, mandap and entrance decoration for the big day.',
                                'addons' => [
                                    ['name' => 'Fresh flower upgrade', 'price' => 4999],
                                    ['name' => 'LED name board', 'price' => 1499],
                                ],
                            ],
                            [
                                'name' => 'Wedding Catering (per 100 guests)',
                                'price' => 24999,
                                'pricing_type' => PricingType::Inspection,
                                'duration' => 480,
                                'short' => 'Menu tasting and final quote after a planning visit.',
                            ],
                        ],
                    ],
                    [
                        'name' => 'Birthday Parties',
                        'services' => [
                            [
                                'name' => 'Birthday Party Setup',
                                'price' => 2999,
                                'duration' => 180,
                                'featured' => true,
                                'short' => 'Balloon decor, cake table and party props at home.',
                                'addons' => [
                                    ['name' => 'Theme decor upgrade', 'price' => 1999],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Kitty Parties & Get-togethers',
                        'services' => [
                            [
                                'name' => 'Kitty Party Hosting',
                                'price' => 4999,
                                'duration' => 240,
                                'short' => 'Decor, games host and high-tea service for up to 20 guests.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Painting',
                'children' => [
                    [
                        'name' => 'Interior Painting',
                        'services' => [
                            [
                                'name' => 'One Room Painting',
                                'price' => 4999,
                                'duration' => 480,
                                'featured' => true,
                                'short' => 'Two coats of premium emulsion for one standard room.',
                                'addons' => [
                                    ['name' => 'Putty and primer prep', 'price' => 1499],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Waterproofing',
                        'services' => [
                            [
                                'name' => 'Waterproofing Inspection',
                                'price' => 299,
                                'pricing_type' => PricingType::Inspection,
                                'duration' => 45,
                                'short' => 'Leakage assessment with treatment quote.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
