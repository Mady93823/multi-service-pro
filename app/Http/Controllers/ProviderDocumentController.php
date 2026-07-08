<?php

namespace App\Http\Controllers;

use App\Domain\Users\Enums\Role;
use App\Models\ProviderDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves KYC files from the private disk — only the owning provider
 * and admins may view them.
 */
class ProviderDocumentController extends Controller
{
    public function show(Request $request, ProviderDocument $document): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $user->hasRole(Role::Admin->value) || $document->profile()->value('user_id') === $user->id,
            403,
        );

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path);
    }
}
