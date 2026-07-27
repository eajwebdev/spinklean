<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemNotUnderMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Let people log out even during maintenance.
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        // Super admins are never blocked — they perform the maintenance.
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        if (! Schema::hasTable('system_settings')) {
            return $next($request);
        }

        $settings = SystemSetting::current();

        if (! $settings->isUnderMaintenance()) {
            return $next($request);
        }

        return response(view('maintenance', [
            'message' => $settings->maintenance_message
                ?: 'The system is temporarily unavailable while we perform scheduled maintenance. Please check back shortly.',
            'since' => $settings->maintenance_started_at,
        ]), Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
