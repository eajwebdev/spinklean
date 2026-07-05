<?php

namespace App\Http\Middleware;

use App\Models\AttendanceEmployee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendanceEmployeeAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = AttendanceEmployee::query()
            ->whereKey($request->session()->get('attendance_employee_id'))
            ->where('status', 'active')
            ->first();

        if ($employee) {
            return $next($request);
        }

        $request->session()->forget('attendance_employee_id');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your employee session expired. Please login again.',
                'redirect' => route('attendance.login'),
            ], 401);
        }

        return redirect()
            ->route('attendance.login')
            ->withErrors(['login' => 'Please login as an employee first.']);
    }
}
