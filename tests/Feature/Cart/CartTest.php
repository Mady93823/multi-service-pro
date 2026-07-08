<?php

use App\Models\Service;
use App\Models\ServiceAddon;

test('a guest can add a service to the cart and see it', function () {
    $service = Service::factory()->create(['price' => 499]);

    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 2])
        ->assertRedirect();

    $response = $this->get(route('cart.show'));

    $response->assertInertia(fn ($page) => $page
        ->component('cart')
        ->count('lines', 1)
        ->where('lines.0.qty', 2)
        ->where('lines.0.service.name', $service->name)
        ->where('lines.0.line_total', '998.00'));
});

test('adding the same service and add-ons again merges quantities', function () {
    $service = Service::factory()->create();
    $addon = ServiceAddon::factory()->for($service)->create();

    $payload = ['service_id' => $service->id, 'qty' => 1, 'addon_ids' => [$addon->id]];
    $this->post(route('cart.add'), $payload);
    $this->post(route('cart.add'), $payload);

    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->count('lines', 1)
        ->where('lines.0.qty', 2));
});

test('add-on prices flow into the line total', function () {
    $service = Service::factory()->create(['price' => 500]);
    $addon = ServiceAddon::factory()->for($service)->create(['price' => 150]);

    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1, 'addon_ids' => [$addon->id]]);

    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->where('lines.0.unit_total', '650.00')
        ->where('lines.0.line_total', '650.00'));
});

test('an add-on belonging to another service is rejected', function () {
    $service = Service::factory()->create();
    $foreignAddon = ServiceAddon::factory()->create();

    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1, 'addon_ids' => [$foreignAddon->id]])
        ->assertSessionHasErrors('addon_ids.0');
});

test('quantity can be changed and zero removes the line', function () {
    $service = Service::factory()->create();
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $key = $this->app['session.store']->get('cart.items');
    $key = array_key_first($key);

    $this->patch(route('cart.update', $key), ['qty' => 5]);
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->where('lines.0.qty', 5));

    $this->patch(route('cart.update', $key), ['qty' => 0]);
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->count('lines', 0));
});

test('removing a line empties the cart', function () {
    $service = Service::factory()->create();
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $lines = $this->app['session.store']->get('cart.items');

    $this->delete(route('cart.remove', array_key_first($lines)));

    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->count('lines', 0));
});

test('services deactivated after being added are dropped from the cart', function () {
    $service = Service::factory()->create();
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $service->update(['is_active' => false]);

    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->count('lines', 0));
});

test('an inactive service cannot be added at all', function () {
    $service = Service::factory()->inactive()->create();

    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1])
        ->assertSessionHasErrors('service_id');
});
