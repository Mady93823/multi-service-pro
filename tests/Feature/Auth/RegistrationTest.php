<?php

namespace Tests\Feature\Auth;

use App\Domain\Users\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::Customer->value));
        $this->assertFalse($user->hasRole(Role::Provider->value));
    }

    public function test_a_new_provider_can_register_and_lands_on_onboarding()
    {
        $response = $this->post('/register', [
            'name' => 'Pat Provider',
            'email' => 'pat@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'provider',
        ]);

        $this->assertAuthenticated();
        // A fresh provider is sent straight into KYC, not the customer dashboard.
        $response->assertRedirect(route('provider.onboarding', absolute: false));

        $user = User::where('email', 'pat@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::Provider->value));
        $this->assertFalse($user->hasRole(Role::Customer->value));
    }

    public function test_role_must_be_customer_or_provider(): void
    {
        // Admin is never selectable at a public form.
        $response = $this->from('/register')->post('/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_the_provider_pitch_page_renders_for_a_guest(): void
    {
        $this->get(route('provider.join'))->assertStatus(200);
    }

    public function test_the_register_form_preselects_the_provider_role_from_the_pitch(): void
    {
        $this->get(route('register', ['as' => 'provider']))
            ->assertInertia(fn ($page) => $page
                ->component('auth/register')
                ->where('role_intent', 'provider'));
    }
}
