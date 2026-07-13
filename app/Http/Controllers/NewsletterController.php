<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\Actions\Subscribe;
use App\Domain\Settings\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request, Subscribe $action, SettingsRegistry $settings): RedirectResponse
    {
        abort_unless($settings->boolean('appearance.newsletter_enabled', true), 404);

        /** @var array{email: string, website?: string|null} $data */
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            // Honeypot — see ContactRequest.
            'website' => ['prohibited'],
        ]);

        $action->handle($data['email']);

        // Idempotent by address: signing up twice says the same thing back.
        return back()->with('success', __('Thanks — you are on the list.'));
    }
}
