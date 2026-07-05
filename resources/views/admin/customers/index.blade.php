@extends('layouts.app')

@section('page_title', 'Customers')

@section('content')
@php($canChooseBranch = auth()->user()->isSuperAdmin() || auth()->user()->role === 'admin')
<div x-data="{ createOpen: false, editOpen: null }" class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-900">
                <span data-lucide="customers" class="h-3.5 w-3.5"></span>
                Customer records
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Customers</h1>
            <p class="text-sm text-muted">Manage regular and PO customers.</p>
        </div>

        <button type="button" @click="createOpen = true" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white shadow-sm transition hover:opacity-90">
            <span data-lucide="plus" class="h-4 w-4"></span>
            Add Customer
        </button>
    </div>

    <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_11rem_11rem_9rem_auto]">
            <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                <input name="search" value="{{ request('search') }}" type="search" placeholder="Search customers..." class="w-full bg-transparent text-sm outline-none">
            </div>

            @if($canChooseBranch)
                <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
            @endif

            <select name="billing_type" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All billing</option>
                @foreach(['regular', 'po'] as $billingType)
                    <option value="{{ $billingType }}" @selected(request('billing_type') === $billingType)>{{ \App\Support\StatusBadge::label($billingType) }}</option>
                @endforeach
            </select>

            <select name="status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>

            <button type="submit" title="Filter" aria-label="Filter customers" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4"></span>
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Billing</th>
                        <th class="px-4 py-3">Unpaid Limit</th>
                        <th class="px-4 py-3">Laundry Visits</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($customers as $customer)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $customer->name }}</p>
                                <p class="text-xs text-muted">{{ $customer->phone ?: 'No phone' }} - {{ $customer->email ?: 'No email' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $customer->branch?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3"><span class="{{ \App\Support\StatusBadge::classes($customer->billing_type) }}">{{ \App\Support\StatusBadge::label($customer->billing_type) }}</span></td>
                            <td class="px-4 py-3">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $customer->unpaid_limit, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="font-semibold">{{ number_format($customer->job_orders_count) }}</span>
                                @if($customer->job_orders_count >= 10)
                                    <span class="ml-1 inline-flex rounded-md border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-violet-700 dark:border-violet-900/60 dark:bg-violet-500/10 dark:text-violet-300">Loyal</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="{{ \App\Support\StatusBadge::classes($customer->is_active ? 'active' : 'inactive') }}">
                                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="editOpen = {{ $customer->id }}" title="Edit" aria-label="Edit customer" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="settings" class="h-4 w-4"></span>
                                </button>

                                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" class="inline" x-data>
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete customer"
                                        x-on:click.prevent="Swal.fire({ title: 'Delete customer?', text: 'Customers with job orders cannot be deleted.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Delete' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50"
                                    >
                                        <span data-lucide="trash" class="h-4 w-4"></span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-muted">No customers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-border px-4 py-3 dark:border-gray-800">
            {{ $customers->links() }}
        </div>
    </div>

    <div x-cloak x-show="createOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.outside="createOpen = false" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="customers" class="h-4 w-4 text-primary"></span>Add Customer</h2>
                <button type="button" @click="createOpen = false" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
            </div>

            @include('admin.customers.partials.form', [
                'action' => route('admin.customers.store'),
                'method' => 'POST',
                'customer' => new \App\Models\Customer(['billing_type' => 'regular', 'is_active' => true, 'unpaid_limit' => 0]),
            ])
        </div>
    </div>

    @foreach($customers as $customer)
        <div x-cloak x-show="editOpen === {{ $customer->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="editOpen = null" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="settings" class="h-4 w-4 text-primary"></span>Edit Customer</h2>
                    <button type="button" @click="editOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>

                @include('admin.customers.partials.form', [
                    'action' => route('admin.customers.update', $customer),
                    'method' => 'PUT',
                    'customer' => $customer,
                ])
            </div>
        </div>
    @endforeach
</div>
@endsection

