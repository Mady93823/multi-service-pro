<?php

namespace App\Http\Controllers;

use App\Domain\Users\Enums\Role;
use App\Http\Concerns\ServesPrivateFiles;
use App\Models\ProviderDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves KYC files from the private disk — only the owning provider
 * and admins may view them.
 */
class ProviderDocumentController extends Controller
{
    use ServesPrivateFiles;

    public function show(Request $request, ProviderDocument $document): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $user->hasRole(Role::Admin->value) || $document->profile()->value('user_id') === $user->id,
            403,
        );

        $disk = Storage::disk('local');

        abort_unless($disk->exists($document->file_path), 404);

        return $this->privateFile(
            $disk->path($document->file_path),
            $disk->mimeType($document->file_path) ?: null,
            basename($document->file_path),
        );
    }
}
