<?php

namespace App\Http\Controllers;

use App\Support\PublicUpload;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicUploadController extends Controller
{
    private const ALLOWED_PREFIXES = [
        'attendance-proofs/',
        'daily-tasks/',
        'settings/',
    ];

    public function show(Request $request, string $path): StreamedResponse
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        abort_if($path === '' || str_contains($path, '..'), 404);
        abort_unless(collect(self::ALLOWED_PREFIXES)->contains(fn (string $prefix) => str_starts_with($path, $prefix)), 404);
        abort_unless(PublicUpload::exists($path), 404);

        return PublicUpload::response($path, [
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
