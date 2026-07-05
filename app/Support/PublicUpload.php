<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicUpload
{
    public const DISK = 'uploads';

    public static function exists(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        return Storage::disk(self::DISK)->exists($path)
            || self::migrateLegacyFile($path);
    }

    public static function url(?string $path): ?string
    {
        if (! self::exists($path)) {
            return null;
        }

        try {
            $timestamp = Storage::disk(self::DISK)->lastModified($path);
        } catch (\Throwable $e) {
            $timestamp = time();
        }

        if (Route::has('uploads.show')) {
            return route('uploads.show', ['path' => $path, 'v' => $timestamp]);
        }

        return Storage::disk(self::DISK)->url($path).'?v='.$timestamp;
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    public static function put(string $path, string $contents): bool
    {
        return Storage::disk(self::DISK)->put($path, $contents);
    }

    public static function response(string $path, array $headers = []): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($path, basename($path), $headers);
    }

    public static function delete(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
        Storage::disk('public')->delete($path);
    }

    private static function migrateLegacyFile(string $path): bool
    {
        $legacyDisk = Storage::disk('public');

        if (! $legacyDisk->exists($path)) {
            return false;
        }

        return Storage::disk(self::DISK)->put($path, $legacyDisk->get($path));
    }
}
