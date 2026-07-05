@extends('layouts.app')

@section('page_title', 'Employees')

@section('content')
<div x-data="{ createOpen: false, editOpen: null }" class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="employees" class="h-3.5 w-3.5"></span>
                Kiosk employee accounts
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Employees</h1>
            <p class="text-sm text-muted">Add employees who can login at /attendance-login for attendance and task proof uploads.</p>
        </div>

        <button type="button" @click="createOpen = true" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white">
            <span data-lucide="plus" class="h-4 w-4"></span>
            Add Employee
        </button>
    </div>

    <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" x-data="{ timer: null }" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                @if(auth()->user()->isAdmin())
                    <select name="branch_id" @change="$el.form.submit()" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950 sm:w-48">
                        <option value="">All branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="flex h-9 w-full items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950 sm:ml-auto sm:max-w-xs">
                <span data-lucide="search" class="h-4 w-4 shrink-0 text-muted"></span>
                <input
                    name="search"
                    value="{{ request('search') }}"
                    type="search"
                    placeholder="Search employees..."
                    autocomplete="off"
                    @input="clearTimeout(timer); timer = setTimeout(() => $el.form.submit(), 350)"
                    class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                >
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($employees as $employee)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $employee->name }}</p>
                                <p class="text-xs text-muted">Last login: {{ $employee->last_login_at?->format('M d, Y h:i A') ?? 'Never' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $employee->username }}</td>
                            <td class="px-4 py-3">{{ $employee->phone ?: 'N/A' }}</td>
                            <td class="px-4 py-3">{{ $employee->branch?->name }}</td>
                            <td class="px-4 py-3"><span class="{{ \App\Support\StatusBadge::classes($employee->status) }}">{{ \App\Support\StatusBadge::label($employee->status) }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="editOpen = {{ $employee->id }}" title="Edit" aria-label="Edit employee" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="settings" class="h-4 w-4"></span>
                                </button>
                                <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}" class="inline" x-data>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete" aria-label="Delete employee" x-on:click.prevent="Swal.fire({ title: 'Delete employee?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Delete' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50">
                                        <span data-lucide="trash" class="h-4 w-4"></span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No employees found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-3 dark:border-gray-800">{{ $employees->links() }}</div>
    </div>

    <div x-cloak x-show="createOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.outside="createOpen = false" class="w-full max-w-2xl rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="employees" class="h-4 w-4 text-primary"></span>Add Employee</h2>
                <button type="button" @click="createOpen = false" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
            </div>
            @include('admin.employees.partials.form', ['action' => route('admin.employees.store'), 'method' => 'POST', 'employee' => new \App\Models\AttendanceEmployee(['status' => 'active', 'branch_id' => auth()->user()->branch_id])])
        </div>
    </div>

    @foreach($employees as $employee)
        <div x-cloak x-show="editOpen === {{ $employee->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="editOpen = null" class="w-full max-w-2xl rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="settings" class="h-4 w-4 text-primary"></span>Edit Employee</h2>
                    <button type="button" @click="editOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>
                @include('admin.employees.partials.form', ['action' => route('admin.employees.update', $employee), 'method' => 'PUT', 'employee' => $employee])
            </div>
        </div>
    @endforeach
</div>
@endsection
