<?php

namespace App\Http\Controllers;

use App\Domain\Settings\SettingsRegistry;
use App\Domain\Support\Actions\OpenContactTicket;
use App\Http\Requests\ContactRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public contact page (M19). A submission opens a support ticket (M16) — the
 * helpdesk is the inbox. A second inbox is a second place to forget to look.
 */
class ContactController extends Controller
{
    public function show(Request $request, SettingsRegistry $settings): Response
    {
        $user = $request->user();

        return Inertia::render('contact', [
            'contact' => [
                'email' => $settings->string('appearance.contact_email') ?: null,
                'phone' => $settings->string('appearance.contact_phone') ?: null,
                'address' => $settings->string('appearance.contact_address') ?: null,
            ],
            'prefill' => [
                'name' => $user === null ? '' : $user->name,
                'email' => $user === null ? '' : $user->email,
            ],
        ]);
    }

    public function store(ContactRequest $request, OpenContactTicket $action): RedirectResponse
    {
        /** @var array{name: string, email: string, subject: string, message: string} $data */
        $data = $request->safe()->except('website');

        /** @var User|null $user */
        $user = $request->user();

        $ticket = $action->handle($data, $user);

        // A signed-in visitor can follow the thread in their own Help section;
        // a guest cannot, so they only get the reference number.
        return back()->with('success', $user === null
            ? __('Thanks — your message is with our team. Reference: :code', ['code' => $ticket->code])
            : __('Thanks — we opened ticket :code. You can follow it under Help.', ['code' => $ticket->code]));
    }
}
