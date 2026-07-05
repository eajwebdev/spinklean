<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEmployee;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $employees = AttendanceEmployee::query()
            ->with('branch')
            ->when(! $user->isAdmin(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->when($request->filled('branch_id') && $user->isAdmin(), fn ($query) => $query->where('branch_id', $request->branch_id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(fn ($query) => $query
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->orderBy('first_name')
            ->paginate(12)
            ->withQueryString();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $user->isAdmin(), fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        return view('admin.employees.index', compact('branches', 'employees'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request));
        $validated = $this->normalizeBranch($request, $validated);
        $validated['password'] = Hash::make($validated['password'] ?? 'password123');
        $validated['status'] = $request->boolean('is_active', true) ? 'active' : 'inactive';

        AttendanceEmployee::create($validated);

        return back()->with('success', 'Employee added successfully.');
    }

    public function update(Request $request, AttendanceEmployee $employee)
    {
        $this->authorizeEmployee($request, $employee);

        $validated = $request->validate($this->rules($request, $employee));
        $validated = $this->normalizeBranch($request, $validated);
        $validated['status'] = $request->boolean('is_active') ? 'active' : 'inactive';

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $employee->update($validated);

        return back()->with('success', 'Employee updated successfully.');
    }

    public function destroy(Request $request, AttendanceEmployee $employee)
    {
        $this->authorizeEmployee($request, $employee);
        $employee->delete();

        return back()->with('success', 'Employee removed successfully.');
    }

    private function rules(Request $request, ?AttendanceEmployee $employee = null): array
    {
        return [
            'branch_id' => [$request->user()->isAdmin() ? 'required' : 'nullable', 'exists:branches,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:100', Rule::unique('attendance_employees', 'username')->ignore($employee?->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function normalizeBranch(Request $request, array $validated): array
    {
        if (! $request->user()->isAdmin()) {
            $validated['branch_id'] = $request->user()->branch_id;
        }

        return $validated;
    }

    private function authorizeEmployee(Request $request, AttendanceEmployee $employee): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless((int) $request->user()->branch_id === (int) $employee->branch_id, 403);
    }
}
