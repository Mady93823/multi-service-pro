<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    /**
     * Serves a finished CSV export. The filename pattern is strict (it is the
     * only thing standing between this route and a path traversal), and the
     * route sits inside the admin middleware group.
     */
    public function download(string $file): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-z]+-\d{8}-\d{6}-[a-z0-9]{6}\.csv$/', $file) === 1, 404);

        $path = storage_path('app'.DIRECTORY_SEPARATOR.'exports'.DIRECTORY_SEPARATOR.$file);

        abort_unless(is_file($path), 404);

        return response()->download($path);
    }
}
