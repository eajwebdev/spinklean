@extends('layouts.app')

@section('page_title', 'Inventory')

@section('content')
@php($activeBranch = $branches->firstWhere('id', $selectedBranchId))
<div x-data="{ createOpen: false, supplierOpen: false, editOpen: null, movementOpen: null }" class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="inventory" class="h-3.5 w-3.5"></span>
                Stock control
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Inventory</h1>
            <p class="text-sm text-muted">{{ $activeBranch ? $activeBranch->name.' supplies and stock movements.' : 'Create a branch before adding stock items.' }}</p>
            <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                <span class="rounded-md bg-smoke px-2 py-1 text-muted dark:bg-gray-950">{{ number_format((int) ($summary->items_count ?? 0)) }} items</span>
                <span class="{{ \App\Support\StatusBadge::classes('low') }}">{{ number_format($lowStockCount) }} low stock</span>
                <span class="rounded-md bg-smoke px-2 py-1 text-muted dark:bg-gray-950">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) ($summary->inventory_value ?? 0), 2) }} value</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="supplierOpen = true" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900">
                <span data-lucide="plus" class="h-4 w-4"></span>
                Supplier
            </button>
            <button type="button" @click="createOpen = true" @disabled(! $activeBranch) class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white shadow-sm hover:opacity-90 disabled:opacity-50">
                <span data-lucide="plus" class="h-4 w-4"></span>
                Add Item
            </button>
        </div>
    </div>

    <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" action="{{ route('admin.inventory.index') }}" class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_10rem_10rem_9rem_8rem_2.25rem]">
            <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                <input name="search" value="{{ request('search') }}" type="search" placeholder="Search item, SKU, supplier..." class="w-full bg-transparent text-sm outline-none">
            </div>

            @if($canChooseBranch)
                <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
            @endif

            <select name="supplier_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All suppliers</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>

            <select name="stock_status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All stock</option>
                <option value="low" @selected(request('stock_status') === 'low')>Low stock</option>
                <option value="ok" @selected(request('stock_status') === 'ok')>In stock</option>
            </select>

            <select name="status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>

            <button type="submit" title="Filter" aria-label="Filter inventory" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4"></span>
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Stock</th>
                        <th class="px-4 py-3">Reorder</th>
                        <th class="px-4 py-3">Cost</th>
                        <th class="px-4 py-3">Value</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($items as $item)
                        @php($isLow = (float) $item->quantity <= (float) $item->reorder_level)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $item->name }}</p>
                                <p class="text-xs text-muted">{{ $item->sku ?: 'No SKU' }} - {{ $item->branch?->name }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $item->supplier?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3">
                                <span class="font-medium {{ $isLow ? 'text-amber-700 dark:text-amber-300' : '' }}">{{ number_format((float) $item->quantity, 2) }}</span>
                                <span class="text-muted">{{ $item->unit }}</span>
                            </td>
                            <td class="px-4 py-3">{{ number_format((float) $item->reorder_level, 2) }} {{ $item->unit }}</td>
                            <td class="px-4 py-3">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $item->unit_cost, 2) }}</td>
                            <td class="px-4 py-3 font-medium">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $item->quantity * (float) $item->unit_cost, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ \App\Support\StatusBadge::classes($isLow ? 'low' : ($item->is_active ? 'active' : 'inactive')) }}">
                                    {{ $isLow ? 'Low' : ($item->is_active ? 'Active' : 'Inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="movementOpen = {{ $item->id }}" title="Stock movement" aria-label="Stock movement" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="activity" class="h-4 w-4"></span>
                                </button>
                                <button type="button" @click="editOpen = {{ $item->id }}" title="Edit" aria-label="Edit inventory item" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="settings" class="h-4 w-4"></span>
                                </button>
                                <form method="POST" action="{{ route('admin.inventory.destroy', $item) }}" class="inline" x-data>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete" aria-label="Delete inventory item" x-on:click.prevent="Swal.fire({ title: 'Delete item?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Delete' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50">
                                        <span data-lucide="trash" class="h-4 w-4"></span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-muted">No inventory items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-border px-4 py-3 dark:border-gray-800">{{ $items->links() }}</div>
    </div>

    <div x-cloak x-show="createOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.outside="createOpen = false" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="inventory" class="h-4 w-4 text-primary"></span>Add Inventory Item</h2>
                <button type="button" @click="createOpen = false" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
            </div>
            @include('admin.inventory.partials.form', ['action' => route('admin.inventory.store'), 'method' => 'POST', 'item' => new \App\Models\Inventory(['branch_id' => $selectedBranchId, 'unit' => 'pcs', 'quantity' => 0, 'reorder_level' => 0, 'unit_cost' => 0, 'is_active' => true])])
        </div>
    </div>

    <div x-cloak x-show="supplierOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.outside="supplierOpen = false" class="w-full max-w-lg rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="plus" class="h-4 w-4 text-primary"></span>Add Supplier</h2>
                <button type="button" @click="supplierOpen = false" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
            </div>
            <form method="POST" action="{{ route('admin.inventory.suppliers.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="text-sm font-medium">Name<input name="name" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"></label>
                    <label class="text-sm font-medium">Contact<input name="contact_number" class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"></label>
                    <label class="text-sm font-medium md:col-span-2">Email<input type="email" name="email" class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"></label>
                    <label class="text-sm font-medium md:col-span-2">Address<textarea name="address" rows="3" class="mt-1.5 w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"></textarea></label>
                    <label class="inline-flex h-9 items-center gap-2 text-sm text-muted md:col-span-2"><input type="checkbox" name="is_active" value="1" checked class="rounded border-border text-primary"> Active supplier</label>
                </div>
                <div class="flex justify-end">
                    <button class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>

    @foreach($items as $item)
        <div x-cloak x-show="editOpen === {{ $item->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="editOpen = null" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="settings" class="h-4 w-4 text-primary"></span>Edit Inventory Item</h2>
                    <button type="button" @click="editOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>
                @include('admin.inventory.partials.form', ['action' => route('admin.inventory.update', $item), 'method' => 'PUT', 'item' => $item])
            </div>
        </div>

        <div x-cloak x-show="movementOpen === {{ $item->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="movementOpen = null" class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="activity" class="h-4 w-4 text-primary"></span>Stock Movement</h2>
                        <p class="text-sm text-muted">{{ $item->name }} - {{ number_format((float) $item->quantity, 2) }} {{ $item->unit }} on hand</p>
                    </div>
                    <button type="button" @click="movementOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>
                <form method="POST" action="{{ route('admin.inventory.movements.store', $item) }}" class="space-y-4">
                    @csrf
                    <label class="block text-sm font-medium">Movement Type
                        <select name="movement_type" class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                            <option value="in">Stock In</option>
                            <option value="out">Stock Out</option>
                            <option value="adjustment">Physical Count Adjustment</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium">Quantity
                        <input type="number" step="0.01" min="0.01" name="quantity" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    </label>
                    <label class="block text-sm font-medium">Remarks
                        <textarea name="remarks" rows="3" class="mt-1.5 w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Purchase, usage, correction, damaged stock..."></textarea>
                    </label>
                    <div class="flex justify-end">
                        <button class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Save Movement</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
