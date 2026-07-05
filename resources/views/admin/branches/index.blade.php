@extends('layouts.app')

@section('page_title', 'Branches')

@section('content')
<div x-data="{ createOpen: false, editOpen: null }" class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-900">
                <span data-lucide="branches" class="h-3.5 w-3.5"></span>
                Multi-branch control
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Branches</h1>
            <p class="text-sm text-muted">Manage branch profiles and operational access.</p>
        </div>

        @if($canCreateBranch)
            <button type="button" @click="createOpen = true" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white shadow-sm transition hover:opacity-90">
                <span data-lucide="plus" class="h-4 w-4"></span>
                Add Branch
            </button>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-3 border-b border-border p-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <form method="GET" class="grid w-full grid-cols-1 gap-2 sm:grid-cols-[minmax(12rem,1fr)_10rem_auto]">
                <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                    <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                    <input name="search" value="{{ request('search') }}" type="search" placeholder="Search branch, code, contact..." class="w-full bg-transparent text-sm outline-none">
                </div>
                <select name="status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
                <button type="submit" title="Filter" aria-label="Filter branches" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                    <span data-lucide="search" class="h-4 w-4"></span>
                </button>
            </form>
            <p class="text-sm text-muted">{{ $branches->total() }} branch{{ $branches->total() === 1 ? '' : 'es' }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Machines</th>
                        <th class="px-4 py-3">Users</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($branches as $branch)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $branch->name }}</p>
                                <p class="text-xs text-muted">{{ $branch->code }} - {{ $branch->address ?: 'No address' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $branch->contact_number ?: 'N/A' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-smoke px-2 py-1 text-xs font-medium text-muted dark:bg-gray-950">
                                    <span data-lucide="{{ ($branch->branch_type ?? 'full_service') === 'pickup_dropoff' ? 'map-pin' : 'laundry' }}" class="h-3.5 w-3.5"></span>
                                    {{ ($branch->branch_type ?? 'full_service') === 'pickup_dropoff' ? 'Pickup & Drop-off' : 'Full Service' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-smoke px-2 py-1 text-xs font-medium text-muted dark:bg-gray-950">
                                    <span data-lucide="laundry" class="h-3.5 w-3.5"></span>
                                    {{ number_format((int) ($branch->machine_count ?? 0)) }}
                                </span>
                                <p class="mt-1 text-xs text-muted">{{ $branch->dailyTasks->count() }} daily task{{ $branch->dailyTasks->count() === 1 ? '' : 's' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $branch->users_count }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ \App\Support\StatusBadge::classes($branch->is_active ? 'active' : 'inactive') }}">
                                    {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="editOpen = {{ $branch->id }}" title="Edit" aria-label="Edit branch" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="settings" class="h-4 w-4"></span>
                                </button>

                                @if(auth()->user()->isSuperAdmin())
                                    <form method="POST" action="{{ route('admin.branches.destroy', $branch) }}" class="inline" x-data>
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            title="Delete"
                                            aria-label="Delete branch"
                                            x-on:click.prevent="Swal.fire({ title: 'Delete branch?', text: 'Only empty branches can be deleted.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Delete' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })"
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
                            <td colspan="7" class="px-4 py-10 text-center text-muted">No branches found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-border px-4 py-3 dark:border-gray-800">
            {{ $branches->links() }}
        </div>
    </div>

    @if($canCreateBranch)
        <div x-cloak x-show="createOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="createOpen = false" class="w-full max-w-xl rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="branches" class="h-4 w-4 text-primary"></span>Add Branch</h2>
                    <button type="button" @click="createOpen = false" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>

                @include('admin.branches.partials.form', [
                    'action' => route('admin.branches.store'),
                    'method' => 'POST',
                    'branch' => new \App\Models\Branch(['is_active' => true, 'branch_type' => 'full_service']),
                ])
            </div>
        </div>
    @endif

    @foreach($branches as $branch)
        <div x-cloak x-show="editOpen === {{ $branch->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="editOpen = null" class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="settings" class="h-4 w-4 text-primary"></span>Edit Branch</h2>
                    <button type="button" @click="editOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    @include('admin.branches.partials.form', [
                        'action' => route('admin.branches.update', $branch),
                        'method' => 'PUT',
                        'branch' => $branch,
                    ])

                    <section class="rounded-lg border border-border p-4 dark:border-gray-800">
                        <div class="mb-3">
                            <h3 class="text-base font-semibold">End-of-Day Tasks</h3>
                            <p class="text-sm text-muted">Set the tasks this branch must comply with every day.</p>
                        </div>

                        <form method="POST" action="{{ route('admin.branches.daily-tasks.store', $branch) }}" class="space-y-2">
                            @csrf
                            <input name="name" placeholder="Task name" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label class="inline-flex items-center gap-2 text-sm text-muted">
                                    <input type="checkbox" name="requires_photo" value="1" checked class="rounded border-border text-primary">
                                    Requires photo
                                </label>
                                <input type="hidden" name="is_active" value="1">
                                <button class="inline-flex h-8 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white hover:opacity-90">
                                    <span data-lucide="plus" class="h-3.5 w-3.5"></span>
                                    Add Task
                                </button>
                            </div>
                        </form>

                        <div class="mt-4 space-y-2">
                            @forelse($branch->dailyTasks as $task)
                                <div class="rounded-md border border-border p-3 dark:border-gray-800">
                                    <form method="POST" action="{{ route('admin.branches.daily-tasks.update', [$branch, $task]) }}" class="space-y-2">
                                        @csrf
                                        @method('PUT')
                                        <input name="name" value="{{ $task->name }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <label class="inline-flex items-center gap-2 text-sm text-muted">
                                                <input type="checkbox" name="requires_photo" value="1" @checked($task->requires_photo) class="rounded border-border text-primary">
                                                Requires photo
                                            </label>
                                            <input type="hidden" name="is_active" value="1">
                                            <button class="inline-flex h-8 items-center gap-2 rounded-md border border-border px-3 text-xs font-medium hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                                                <span data-lucide="check" class="h-3.5 w-3.5"></span>
                                                Save
                                            </button>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('admin.branches.daily-tasks.destroy', [$branch, $task]) }}" class="mt-2">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex h-8 items-center gap-2 rounded-md border border-red-200 px-3 text-xs font-medium text-red-600 hover:bg-red-50">
                                            <span data-lucide="trash" class="h-3.5 w-3.5"></span>
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <div class="rounded-md border border-dashed border-border p-4 text-center text-sm text-muted dark:border-gray-800">
                                    No daily tasks set for this branch yet.
                                </div>
                            @endforelse
                        </div>
                    </section>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

