<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Referrals\Actions\RegisterReferral;
use App\Domain\Settings\SettingsRegistry;
use App\Domain\Users\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\RecaptchaToken;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
            // ?as=provider (the "Become a provider" pitch links here) preselects
            // the "offer services" role. Anything else is a customer signup.
            'role_intent' => $request->query('as') === Role::Provider->value
                ? Role::Provider->value
                : Role::Customer->value,
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
            // Customer or provider only. Admin is assigned by the installer and
            // seeders, never picked at a public form — so it is not a valid value.
            'role' => ['nullable', Rule::in([Role::Customer->value, Role::Provider->value])],
            // Nullable until an admin configures reCaptcha and ticks this form;
            // required — and verified — the moment they do (M24).
            'recaptcha_token' => RecaptchaToken::rules('register'),
            ...($referralsEnabled ? [
                'referral_code' => 'nullable|string|max:12|exists:users,referral_code',
            ] : []),
        ]);

        $role = $request->input('role') === Role::Provider->value
            ? Role::Provider
            : Role::Customer;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($role->value);

        // Referral is a customer growth loop — a provider signup does not carry one.
        $code = $request->input('referral_code');

        if ($role === Role::Customer && $referralsEnabled && is_string($code) && $code !== '') {
            $referral->handle($user, $code);
        }

        event(new Registered($user));

        Auth::login($user);

        // A fresh provider goes straight to KYC onboarding and stays there until
        // an admin approves them (M05); a customer lands on their dashboard.
        return redirect()->route($role === Role::Provider ? 'provider.onboarding' : 'dashboard');
    }
}
