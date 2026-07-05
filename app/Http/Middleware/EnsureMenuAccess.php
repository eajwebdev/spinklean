<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasMenuAccess($key)) {
            abort(403);
        }

        return $next($request);
    }
}
