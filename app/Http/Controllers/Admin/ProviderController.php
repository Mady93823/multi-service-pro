<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Providers\Actions\ReviewProvider;
use App\Domain\Providers\Actions\ReviewProviderDocument;
use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Domain\Providers\Enums\ProviderDocumentStatus;
use App\Domain\Users\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewProviderDocumentRequest;
use App\Http\Requests\Admin\ReviewProviderRequest;
use App\Http\Resources\ProviderProfileResource;
use App\Models\ProviderDocument;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $providers = User::query()
            ->role(Role::Provider->value)
            ->with('providerProfile')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($status !== '' && $status !== 'none', fn ($query) => $query->whereHas(
                'providerProfile',
                fn ($query) => $query->where('approval_status', $status),
            ))
            ->when($status === 'none', fn ($query) => $query->whereDoesntHave('providerProfile'))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/providers/index', [
            'providers' => $providers->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'approval_status' => $user->providerProfile?->approval_status->value,
                'is_online' => $user->providerProfile->is_online ?? false,
                'is_complete' => $user->providerProfile?->isComplete() ?? false,
                'joined_at' => $user->created_at?->format('j M Y'),
            ]),
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function show(User $provider): Response
    {
        abort_unless($provider->hasRole(Role::Provider->value), 404);

        $profile = $provider->providerProfile()
            ->with(['categories', 'documents', 'blackouts'])
            ->first();

        return Inertia::render('admin/providers/show', [
            'provider' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'email' => $provider->email,
                'phone' => $provider->phone,
                'joined_at' => $provider->created_at?->format('j M Y'),
            ],
            'profile' => $profile === null ? null : new ProviderProfileResource($profile),
        ]);
    }

    public function review(ReviewProviderRequest $request, User $provider, ReviewProvider $action): RedirectResponse
    {
        abort_unless($provider->hasRole(Role::Provider->value), 404);

        /** @var ProviderProfile|null $profile */
        $profile = $provider->providerProfile()->first();

        abort_if($profile === null, 404);

        $action->handle(
            $profile,
            ProviderApprovalStatus::from((string) $request->validated('status')),
            $request->validated('note'),
        );

        return back()->with('success', __('Provider review saved.'));
    }

    public function reviewDocument(ReviewProviderDocumentRequest $request, ProviderDocument $document, ReviewProviderDocument $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $action->handle(
            $document,
            ProviderDocumentStatus::from((string) $request->validated('status')),
            $admin,
            $request->validated('reject_reason'),
        );

        return back()->with('success', __('Document review saved.'));
    }
}
