<?php

namespace App\Http\Concerns;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * How a private-disk file leaves the server.
 *
 * The policy check upstream decides *who* may read the file. This decides *what
 * the browser is allowed to do with it*, and the two are not the same question:
 * a file served inline from our own origin executes in our own origin. KYC docs,
 * bank receipts and ticket attachments accept PDFs (`UploadRules::document()`),
 * and a PDF viewer is a script host — so only an image renders inline here, and
 * everything else is a download. `nosniff` closes the other half: without it a
 * browser may decide our `application/pdf` was really HTML and run it.
 *
 * Uploads are mime-validated on the way in. This is the second lock, because the
 * first one is a claim made by the uploader's own browser.
 */
trait ServesPrivateFiles
{
    protected function privateFile(string $path, ?string $mime, string $filename): BinaryFileResponse
    {
        $mime = $mime !== null && $mime !== '' ? $mime : 'application/octet-stream';
        $inline = str_starts_with($mime, 'image/');

        return response()->file($path, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename,
                // ASCII fallback for a browser that cannot read the UTF-8 name.
                'file',
            ),
        ]);
    }
}
