<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\AttendanceEmployee;
use App\Models\Branch;
use App\Models\User;
use App\Support\Activity;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', [
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function showAttendanceLogin()
    {
        return view('auth.attendance-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($field, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()
                ->withErrors(['login' => 'Invalid username/email or password.'])
                ->onlyInput('login', 'branch_id');
        }

        if ($user->status !== 'active') {
            return back()
                ->withErrors(['login' => 'Your account is inactive. Please contact administrator.']);
        }

        // Admins work across every branch, so their branch is never reassigned here.
        // Branch staff may be working out of another branch today, so the branch they
        // pick at login becomes their branch and every module scopes to it from there.
        $previousBranchId = $user->branch_id;
        $branchId = $previousBranchId;

        if (! $user->isAdmin() && $request->filled('branch_id')) {
            $branch = Branch::where('is_active', true)->find($request->integer('branch_id'));

            if (! $branch) {
                return back()
                    ->withErrors(['branch_id' => 'That branch is unavailable. Please pick another.'])
                    ->onlyInput('login', 'branch_id');
            }

            $branchId = $branch->id;
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
            'branch_id' => $branchId,
        ]);

        if ((int) $branchId !== (int) $previousBranchId) {
            Activity::log($request, 'user_branch_switched', $user, [
                'from_branch_id' => $previousBranchId,
                'to_branch_id' => $branchId,
            ], $branchId);
        }

        return match ($user->role) {
            'super_admin' => redirect()->route('dashboard'),
            'admin' => redirect()->route('dashboard'),
            'branch_manager' => redirect()->route('dashboard'),
            'cashier' => redirect()->route('dashboard'),
            default => redirect()->route('dashboard'),
        };
    }

    public function attendanceLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('attendance.login')
                ->withErrors($validator)
                ->withInput($request->only('login'));
        }

        $employee = AttendanceEmployee::query()
            ->where('username', $request->login)
            ->where('status', 'active')
            ->first();

        if (! $employee || ! Hash::check($request->password, $employee->password)) {
            return redirect()
                ->route('attendance.login')
                ->withErrors(['login' => 'Invalid employee username or password.'])
                ->onlyInput('login');
        }

        $request->session()->regenerate();
        $request->session()->put('attendance_employee_id', $employee->id);
        $employee->update(['last_login_at' => now()]);

        return redirect()->route('attendance.kiosk');
    }

    public function attendanceLogout(Request $request)
    {
        $request->session()->forget('attendance_employee_id');
        $request->session()->regenerateToken();

        return redirect()->route('attendance.login');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

}
