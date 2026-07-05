<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Support\Menu;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $viewer = $request->user();
        $canChooseBranch = $viewer->canManageAllBranches();

        $users = User::with('branch')
            ->visibleTo($viewer)
            ->when($canChooseBranch && $request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->branch_id))
            ->when(in_array($request->role, $this->availableRoles($viewer), true), fn ($query) => $query->where('role', $request->role))
            ->when(in_array($request->status, ['active', 'inactive', 'suspended'], true), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('branch', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();
        $branches = Branch::where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($viewer->branch_id))
            ->orderBy('name')
            ->get();
        $menuItems = Menu::items();
        $roles = $this->availableRoles();
        $roleAccessPresets = $this->roleAccessPresets($viewer);

        return view('admin.users.index', compact('users', 'branches', 'menuItems', 'roles', 'roleAccessPresets', 'canChooseBranch'));
    }

    public function create()
    {
        $branches = Branch::where('is_active', true)->get();

        return view('admin.users.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $viewer = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in($this->availableRoles($viewer))],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'access' => ['nullable', 'array'],
            'access.*' => ['string', Rule::in(Menu::keys())],
        ]);

        if ($viewer->role === 'branch_manager') {
            $validated['branch_id'] = $viewer->branch_id;
        }

        $this->ensureBranchRoleHasBranch($validated['role'], $validated['branch_id'] ?? null);
        $validated['access'] = $this->normalizedAccess($validated['role'], $validated['access'] ?? []);

        User::create($validated);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account created successfully.');
    }

    public function show(User $user)
    {
        $this->authorizeUserAccess($user);

        $user->load('branch');

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $this->authorizeUserAccess($user);

        $branches = Branch::where('is_active', true)->get();

        return view('admin.users.edit', compact('user', 'branches'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeUserAccess($user);
        $viewer = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in($this->availableRoles($viewer))],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'access' => ['nullable', 'array'],
            'access.*' => ['string', Rule::in(Menu::keys())],
        ]);

        if ($viewer->role === 'branch_manager') {
            abort_unless((int) $user->branch_id === (int) $viewer->branch_id, 403);
            $validated['branch_id'] = $viewer->branch_id;
        }

        $this->ensureBranchRoleHasBranch($validated['role'], $validated['branch_id'] ?? null);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $validated['access'] = $this->normalizedAccess($validated['role'], $validated['access'] ?? []);

        $user->update($validated);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account updated successfully.');
    }

    public function destroy(User $user)
    {
        $this->authorizeUserAccess($user);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super admin accounts cannot be deleted here.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account deleted successfully.');
    }

    private function availableRoles(?User $viewer = null): array
    {
        $viewer ??= auth()->user();

        if ($viewer?->isSuperAdmin()) {
            return ['super_admin', 'admin', 'branch_manager', 'cashier', 'staff'];
        }

        if ($viewer?->role === 'admin') {
            return ['admin', 'branch_manager', 'cashier', 'staff'];
        }

        return ['cashier', 'staff'];
    }

    private function normalizedAccess(string $role, array $access): array
    {
        if ($role === 'super_admin') {
            return Menu::keys();
        }

        if ($access === []) {
            return $this->roleAccessPresets()[$role] ?? [];
        }

        return array_values(array_intersect($access, Menu::assignableKeysForRole($role)));
    }

    private function roleAccessPresets(?User $viewer = null): array
    {
        $viewer ??= auth()->user();
        $assignable = fn (array $keys, string $role): array => array_values(array_intersect($keys, Menu::assignableKeysForRole($role)));

        $presets = [
            'super_admin' => Menu::keys(),
            'admin' => $assignable(Menu::keys(), 'admin'),
            'branch_manager' => $assignable([
                'dashboard',
                'job_orders',
                'cycles',
                'customers',
                'services',
                'service_categories',
                'inventory',
                'payments',
                'receivables',
                'po_transactions',
                'expenses',
                'accounts_payable',
                'petty_cash',
                'z_readings',
                'employees',
                'attendance',
                'daily_tasks',
                'reports',
                'branches',
                'users',
                'sms_logs',
                'settings',
            ], 'branch_manager'),
            'cashier' => $assignable([
                'dashboard',
                'job_orders',
                'cycles',
                'customers',
                'payments',
                'receivables',
                'po_transactions',
                'reports',
            ], 'cashier'),
            'staff' => $assignable([
                'dashboard',
                'cycles',
                'daily_tasks',
                'attendance',
            ], 'staff'),
        ];

        return array_intersect_key($presets, array_flip($this->availableRoles($viewer)));
    }

    private function ensureBranchRoleHasBranch(string $role, mixed $branchId): void
    {
        if (in_array($role, ['branch_manager', 'cashier', 'staff'], true) && empty($branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Please choose a branch for this role.',
            ]);
        }
    }

    private function authorizeUserAccess(User $user): void
    {
        if (! auth()->user()->isSuperAdmin() && $user->isSuperAdmin()) {
            abort(403);
        }

        if (auth()->user()->role === 'branch_manager' && (int) $user->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }
    }
}
