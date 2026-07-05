<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemSettingsCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->role === 'super_admin') {
            return $next($request);
        }

        if ($request->routeIs('admin.settings.*') || $request->routeIs('logout')) {
            return $next($request);
        }

        $settings = SystemSetting::current();

        if (!$settings->is_completed) {
            return redirect()
                ->route('admin.settings.edit')
                ->with('error', 'Please complete your business settings first before using the system.');
        }

        return $next($request);
    }
}