<?php

namespace App\Http\Controllers\Provider;

use App\Domain\Providers\Actions\UploadProviderDocument;
use App\Domain\Providers\Enums\ProviderDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\UploadProviderDocumentRequest;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

class DocumentController extends Controller
{
    public function store(UploadProviderDocumentRequest $request, UploadProviderDocument $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Documents can be uploaded before the profile form is saved —
        // create the empty profile shell on first contact.
        /** @var ProviderProfile $profile */
        $profile = $user->providerProfile()->firstOrCreate([]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $action->handle(
            $profile,
            ProviderDocumentType::from((string) $request->validated('type')),
            $file,
        );

        return back()->with('success', __('Document uploaded. It will be reviewed shortly.'));
    }
}
