<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Referrals\Actions\RegisterReferral;
use App\Domain\Settings\SettingsRegistry;
use App\Domain\Users\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(Request $request, SettingsRegistry $settings): Response
    {
        return Inertia::render('auth/register', [
            'referrals_enabled' => $settings->boolean('referrals.enabled', true),
            // ?ref=CODE from a share link pre-fills the referral field.
            'referral_code' => strtoupper(trim((string) $request->query('ref', ''))),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, SettingsRegistry $settings, RegisterReferral $referral): RedirectResponse
    {
        $referralsEnabled = $settings->boolean('referrals.enabled', true);

        // Codes are stored uppercase — normalize before the exists check.
        if ($referralsEnabled && is_string($request->input('referral_code'))) {
            $request->merge(['referral_code' => strtoupper(trim($request->input('referral_code')))]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ...($referralsEnabled ? [
                'referral_code' => 'nullable|string|max:12|exists:users,referral_code',
            ] : []),
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole(Role::Customer->value);

        $code = $request->input('referral_code');

        if ($referralsEnabled && is_string($code) && $code !== '') {
            $referral->handle($user, $code);
        }

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
