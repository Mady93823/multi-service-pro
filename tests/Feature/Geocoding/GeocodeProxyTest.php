<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('nominatim-upstream');
});

test('reverse geocoding proxies nominatim with an identifying user agent', function () {
    Http::fake([
        'nominatim.openstreetmap.org/reverse*' => Http::response([
            'lat' => '12.9758',
            'lon' => '77.6096',
            'name' => 'Some Building',
            'display_name' => '221, MG Road, Shivaji Nagar, Bengaluru, 560001, India',
            'address' => [
                'house_number' => '221',
                'road' => 'MG Road',
                'suburb' => 'Shivaji Nagar',
                'city' => 'Bengaluru',
                'postcode' => '560001',
            ],
        ]),
    ]);

    $this->actingAs(User::factory()->customer()->create())
        ->getJson(route('geocode.reverse', ['lat' => 12.9758, 'lng' => 77.6096]))
        ->assertOk()
        ->assertJsonPath('result.line1', '221 MG Road')
        ->assertJsonPath('result.line2', 'Shivaji Nagar')
        ->assertJsonPath('result.city', 'Bengaluru')
        ->assertJsonPath('result.postal_code', '560001');

    Http::assertSent(function ($request) {
        return str_contains($request->header('User-Agent')[0] ?? '', (string) config('app.name'));
    });
});

test('reverse results are cached so nominatim is hit once per coordinate', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'lat' => '12.9758',
            'lon' => '77.6096',
            'display_name' => 'Bengaluru',
            'address' => ['city' => 'Bengaluru', 'postcode' => '560001', 'road' => 'MG Road'],
        ]),
    ]);

    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->getJson(route('geocode.reverse', ['lat' => 12.9758, 'lng' => 77.6096]))->assertOk();
    $this->actingAs($customer)->getJson(route('geocode.reverse', ['lat' => 12.9758, 'lng' => 77.6096]))->assertOk();

    Http::assertSentCount(1);
});

test('search returns mapped results', function () {
    Http::fake([
        'nominatim.openstreetmap.org/search*' => Http::response([
            ['lat' => '12.9716', 'lon' => '77.5946', 'display_name' => 'Bengaluru, Karnataka, India'],
        ]),
    ]);

    $this->actingAs(User::factory()->customer()->create())
        ->getJson(route('geocode.search', ['q' => 'Bengaluru']))
        ->assertOk()
        ->assertJsonPath('results.0.display_name', 'Bengaluru, Karnataka, India')
        ->assertJsonPath('results.0.lat', 12.9716);
});

test('upstream failures degrade to a null result', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(null, 503)]);

    $this->actingAs(User::factory()->customer()->create())
        ->getJson(route('geocode.reverse', ['lat' => 1, 'lng' => 1]))
        ->assertOk()
        ->assertJsonPath('result', null);
});

test('coordinates are validated', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->getJson(route('geocode.reverse', ['lat' => 999, 'lng' => 1]))
        ->assertUnprocessable();
});

test('guests cannot use the geocoding proxy', function () {
    $this->getJson(route('geocode.reverse', ['lat' => 1, 'lng' => 1]))->assertUnauthorized();
});
