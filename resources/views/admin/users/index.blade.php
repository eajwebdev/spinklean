@extends('layouts.app')

@section('page_title', 'Users')

@section('content')
<div
    x-data="{ createOpen: @js($errors->any() && ! old('_method')), editOpen: null }"
    class="space-y-4"
>
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-900">
                <span data-lucide="shieldCheck" class="h-3.5 w-3.5"></span>
                Role-aware access
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Users</h1>
            <p class="text-sm text-muted">Manage accounts, roles, branch assignment, and menu visibility.</p>
        </div>

        <button type="button" @click="createOpen = true" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white shadow-sm transition hover:opacity-90">
            <span data-lucide="plus" class="h-4 w-4"></span>
            Add User
        </button>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-3 border-b border-border p-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <form method="GET" class="grid w-full grid-cols-1 gap-2 sm:grid-cols-[minmax(12rem,1fr)_12rem_10rem_10rem_auto]">
                <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                    <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                    <input name="search" value="{{ request('search') }}" type="search" placeholder="Search users..." class="w-full bg-transparent text-sm outline-none">
                </div>
                @if($canChooseBranch)
                    <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <option value="">All branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="role" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" @selected(request('role') === $role)>{{ \App\Support\StatusBadge::label($role) }}</option>
                    @endforeach
                </select>
                <select name="status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All status</option>
                    @foreach(['active', 'inactive', 'suspended'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                    @endforeach
                </select>
                <button type="submit" title="Filter" aria-label="Filter users" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                    <span data-lucide="search" class="h-4 w-4"></span>
                </button>
            </form>
            <p class="text-sm text-muted">{{ $users->total() }} account{{ $users->total() === 1 ? '' : 's' }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($users as $user)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $user->name }}</p>
                                <p class="text-xs text-muted">{{ $user->username }} · {{ $user->email ?: 'No email' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ \App\Support\StatusBadge::label($user->role) }}</td>
                            <td class="px-4 py-3">{{ $user->branch?->name ?? 'All branches' }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ \App\Support\StatusBadge::classes($user->status) }}">
                                    {{ \App\Support\StatusBadge::label($user->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="editOpen = {{ $user->id }}" title="Edit" aria-label="Edit user" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="settings" class="h-4 w-4"></span>
                                </button>

                                @if(auth()->id() !== $user->id && ! $user->isSuperAdmin())
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" x-data>
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            x-on:click.prevent="Swal.fire({ title: 'Delete user?', text: 'This action can be reversed only from the database backup.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Delete' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })"
                                            title="Delete"
                                            aria-label="Delete user"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50"
                                        >
                                            <span data-lucide="trash" class="h-4 w-4"></span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-muted">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-border px-4 py-3 dark:border-gray-800">
            {{ $users->links() }}
        </div>
    </div>

    <div x-cloak x-show="createOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.outside="createOpen = false" class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="user" class="h-4 w-4 text-primary"></span>Add User</h2>
                <button type="button" @click="createOpen = false" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
            </div>

            @include('admin.users.partials.form', [
                'action' => route('admin.users.store'),
                'method' => 'POST',
                'user' => new \App\Models\User(['role' => 'cashier', 'status' => 'active', 'access' => []]),
            ])
        </div>
    </div>

    @foreach($users as $user)
        <div x-cloak x-show="editOpen === {{ $user->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="editOpen = null" class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="settings" class="h-4 w-4 text-primary"></span>Edit User</h2>
                    <button type="button" @click="editOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>

                @include('admin.users.partials.form', [
                    'action' => route('admin.users.update', $user),
                    'method' => 'PUT',
                    'user' => $user,
                ])
            </div>
        </div>
    @endforeach
</div>
@endsection

