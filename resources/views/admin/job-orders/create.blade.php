@extends('layouts.app')

@php
    $isEditing = isset($jobOrder);
    $pageTitle = $isEditing ? 'Edit Job Order' : (in_array(auth()->user()->role, ['branch_manager', 'cashier'], true) ? 'Cashier POS' : 'New Job Order');
    $initialState = [
        'isEditing' => $isEditing,
        'initialItems' => $isEditing ? $initialItems : [],
        'selectedCustomerId' => (string) old('customer_id', $selectedCustomerId ?? ''),
        'processingBranchId' => (string) old('processing_branch_id', $isEditing ? ($jobOrder->processing_branch_id ?: $jobOrder->branch_id) : ''),
        'discount' => (float) old('discount', $isEditing ? $jobOrder->discount : 0),
        'paid' => (float) ($isEditing ? $jobOrder->payments->sum('amount') : old('paid_amount', 0)),
        'paymentType' => old('payment_type', $isEditing ? 'unpaid' : 'unpaid'),
    ];
@endphp

@section('page_title', $pageTitle)
@section('hide_footer', true)

@section('content')
<div
    x-data="posPage(@js($branches), @js($processingBranches), @js($services), @js($customers), @js($serviceCategories), @js($servicePresets), @js((float) ($appSettings?->vat_rate ?? 0)), @js((bool) ($appSettings?->vat_enabled ?? false)), @js($initialState))"
    x-init="init()"
>
    <form
        method="POST"
        action="{{ $isEditing ? route('admin.job-orders.update', $jobOrder) : route('admin.job-orders.store') }}"
        class="grid gap-4 md:h-[calc(100dvh-6.5rem)] md:grid-cols-[minmax(0,1fr)_18rem] md:overflow-hidden lg:h-[calc(100dvh-7.5rem)] lg:grid-cols-[minmax(0,1fr)_20rem] 2xl:grid-cols-[minmax(0,1fr)_22rem]"
    >
        @csrf
        @if($isEditing)
            @method('PUT')
        @endif

        <!-- LEFT SIDE -->
        <section class="min-h-0 min-w-0">
            <!-- MODERN TOP BAR - Clean & Simple -->
            <div class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <!-- Left: Title & Orders Link -->
                <div class="flex items-center gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-dark dark:text-white">{{ $isEditing ? $jobOrder->job_order_number : 'New Job Order' }}</h2>
                        <p class="text-[10px] text-muted">{{ $isEditing ? 'Edit customer order' : 'Create customer order' }}</p>
                    </div>
                    <a href="{{ route('admin.job-orders.index') }}" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary/10 px-3 text-xs font-medium text-primary hover:bg-primary/20 dark:bg-primary/20 dark:hover:bg-primary/30">
                        <span data-lucide="list-ordered" class="h-3.5 w-3.5"></span>
                        Orders
                    </a>
                </div>

                <!-- Right: Branch Selection - Clean Badge Style -->
                <div class="ml-auto flex items-center gap-3">
                    @if($isEditing)
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <div class="flex items-center gap-2 rounded-md bg-primary/10 px-3 py-1">
                            <span data-lucide="store" class="h-3.5 w-3.5 text-primary"></span>
                            <span class="text-xs font-medium text-primary">{{ $branches->firstWhere('id', (int) $branchId)?->name }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span data-lucide="activity" class="h-3.5 w-3.5 text-muted"></span>
                            <select name="status" class="h-8 rounded-md border-0 bg-smoke px-2 text-xs font-medium dark:bg-gray-800" required>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status', $jobOrder->status) === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif(in_array(auth()->user()->role, ['super_admin', 'admin'], true))
                        <div class="flex items-center gap-2">
                            <span data-lucide="store" class="h-3.5 w-3.5 text-muted"></span>
                            <select name="branch_id" x-model="branchId" class="h-8 rounded-md border-0 bg-smoke px-2 text-xs font-medium dark:bg-gray-800" required>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <div class="flex items-center gap-2 rounded-md bg-primary/10 px-3 py-1">
                            <span data-lucide="store" class="h-3.5 w-3.5 text-primary"></span>
                            <span class="text-xs font-medium text-primary">{{ $branches->firstWhere('id', (int) $branchId)?->name }}</span>
                        </div>
                    @endif

                    @php
                        $selectedBranch = $branches->firstWhere('id', (int) $branchId);
                        $canSelectProcessingBranch = auth()->user()->isAdmin() || $selectedBranch?->isPickupDropoff();
                    @endphp

                    @if($canSelectProcessingBranch)
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-medium text-muted">Receiving Production Branch</span>
                            <div class="flex items-center gap-2">
                                <span data-lucide="git-branch" class="h-3.5 w-3.5 text-muted"></span>
                                <select name="processing_branch_id" x-model="processingBranchId" class="h-8 rounded-md border-0 bg-smoke px-2 text-xs font-medium dark:bg-gray-800" required>
                                    <template x-for="branch in processingBranches" :key="branch.id">
                                        <option :value="branch.id" x-text="branch.name"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="processing_branch_id" value="{{ $branchId }}">
                        <div class="flex items-center gap-1.5">
                            <span data-lucide="git-branch" class="h-3.5 w-3.5 text-muted"></span>
                            <span class="text-xs text-muted">{{ $branches->firstWhere('id', (int) $branchId)?->name }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- CUSTOMER & ORDER OPTIONS - Clean Grid -->
            <div class="mb-3 grid grid-cols-1 gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-3">
                <!-- Customer -->
                <div class="relative" @click.outside="customerOpen = false">
                    <label class="mb-1 block text-[10px] font-medium text-muted">Customer</label>
                    <input type="hidden" name="customer_id" :value="selectedCustomerId">
                    <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                        <span data-lucide="user" class="h-4 w-4 shrink-0 text-muted"></span>
                        <input
                            type="search"
                            x-model="customerSearch"
                            @focus="customerOpen = true"
                            @input="selectedCustomerId = ''; customerOpen = true"
                            placeholder="Search or select customer..."
                            class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                            autocomplete="off"
                        >
                        <button type="button" x-show="!isEditing" @click="quickCustomerOpen = true; refreshIcons()" title="Add new customer" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-primary hover:bg-primary/10">
                            <span data-lucide="plus" class="h-4 w-4"></span>
                        </button>
                        <button type="button" x-show="selectedCustomerId" @click="clearCustomer()" title="Clear customer" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md hover:bg-smoke dark:hover:bg-gray-900">
                            <span data-lucide="x" class="h-4 w-4"></span>
                        </button>
                    </div>
                    <div x-cloak x-show="customerOpen" x-transition class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-border bg-white p-1 shadow-lg dark:border-gray-800 dark:bg-gray-950">
                        <template x-for="customer in filteredCustomers" :key="customer.id">
                            <button type="button" @click="selectCustomer(customer)" class="flex w-full items-center justify-between gap-2 rounded-sm px-3 py-2 text-left text-sm hover:bg-smoke dark:hover:bg-gray-900">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium" x-text="customer.name"></span>
                                    <span class="block truncate text-xs text-muted" x-text="`${customer.phone || 'No phone'}`"></span>
                                </span>
                                <span x-show="String(selectedCustomerId) === String(customer.id)" data-lucide="check" class="h-4 w-4 shrink-0 text-primary"></span>
                            </button>
                        </template>
                        <div x-show="filteredCustomers.length === 0" class="px-3 py-6 text-center text-sm text-muted">No customers found</div>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="mb-1 block text-[10px] font-medium text-muted">Notes / Instructions</label>
                    <textarea name="notes" rows="1" placeholder="Add notes..." class="h-9 w-full rounded-md border border-border bg-white px-3 py-1.5 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-950">{{ old('notes', $isEditing ? $jobOrder->notes : '') }}</textarea>
                </div>

                <!-- Options -->
                <div class="flex items-center gap-3">
                    <label class="flex h-9 cursor-pointer items-center gap-2 mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 text-sm font-medium text-amber-800 dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-300">
                        <input type="checkbox" name="is_rush" value="1" @checked(old('is_rush', $isEditing ? $jobOrder->is_rush : false)) class="rounded border-amber-300 text-amber-600">
                        <span data-lucide="zap" class="h-4 w-4"></span>
                        Rush
                    </label>
                    <div class="flex h-9 rounded-md bg-smoke p-0.5 dark:bg-gray-950 mt-3">
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-sm px-3 text-xs font-medium has-[:checked]:bg-white has-[:checked]:text-primary has-[:checked]:shadow-sm dark:has-[:checked]:bg-gray-900">
                            <input type="radio" name="transaction_type" value="walk_in" @checked(old('transaction_type', $isEditing ? $jobOrder->transaction_type : 'walk_in') !== 'delivery') class="sr-only">
                            <span data-lucide="user" class="h-3.5 w-3.5"></span>
                            Walk-in
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-sm px-3 text-xs font-medium has-[:checked]:bg-orange-100 has-[:checked]:text-orange-700 has-[:checked]:shadow-sm dark:has-[:checked]:bg-orange-500/10 dark:has-[:checked]:text-orange-300">
                            <input type="radio" name="transaction_type" value="delivery" @checked(old('transaction_type', $isEditing ? $jobOrder->transaction_type : 'walk_in') === 'delivery') class="sr-only">
                            <span data-lucide="truck" class="h-3.5 w-3.5"></span>
                            Delivery
                        </label>
                    </div>
                </div>
            </div>

            <!-- SERVICE CATALOG -->
            <div class="flex h-[calc(100%-13.5rem)] flex-col rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <!-- Catalog Header -->
                <div class="mb-3 flex shrink-0 items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span data-lucide="grid" class="h-4 w-4 text-muted"></span>
                        <h3 class="text-sm font-semibold">Service Catalog</h3>
                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary" x-text="filteredCatalogCount"></span>
                    </div>
                    <div class="flex h-9 w-48 items-center gap-2 rounded-md border border-border px-3 dark:border-gray-800">
                        <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                        <input type="search" x-model.debounce.200ms="serviceSearch" placeholder="Search services..." class="w-full bg-transparent text-sm outline-none">
                    </div>
                </div>

                <!-- Category Filters -->
                <div class="mb-3 flex shrink-0 gap-0.5 overflow-x-auto rounded-md bg-smoke p-0.5 dark:bg-gray-950">
                    <template x-for="type in serviceTypes" :key="type.value">
                        <button type="button" @click="typeFilter = type.value; refreshIcons()" class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-sm px-3 text-xs font-medium whitespace-nowrap" :class="typeFilter === type.value ? 'bg-white text-dark shadow-sm dark:bg-gray-900 dark:text-white' : 'text-muted hover:text-dark dark:hover:text-white'">
                            <span :data-lucide="type.icon" class="h-3.5 w-3.5"></span>
                            <span x-text="type.label"></span>
                        </button>
                    </template>
                </div>

                <!-- Service Grid -->
                <div class="grid min-h-0 flex-1 content-start gap-2 overflow-y-auto pr-1 sm:grid-cols-2 lg:grid-cols-3" x-effect="[...filteredPresets.map(preset => `p${preset.id}`), ...filteredServices.map(service => `s${service.id}`)].join(','); refreshIcons()">
                    <template x-for="preset in filteredPresets" :key="`preset-${preset.id}`">
                        <button type="button" @click="addPreset(preset)" class="group rounded-lg border border-primary/30 bg-primary/5 p-3 text-left transition-all hover:border-primary hover:bg-primary/10 hover:shadow-sm dark:border-primary/40 dark:bg-primary/10">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex min-w-0 items-start gap-2.5">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-white transition group-hover:scale-105">
                                        <span data-lucide="tag" class="h-5 w-5"></span>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium" x-text="preset.name"></p>
                                        <p class="mt-0.5 truncate text-[10px] text-muted" x-text="presetSummary(preset)"></p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-md bg-primary px-2 py-1 text-xs font-bold text-white">
                                    {{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(presetTotal(preset))"></span>
                                </span>
                            </div>
                        </button>
                    </template>
                    <template x-for="service in filteredServices" :key="service.id">
                        <button type="button" @click="add(service)" class="group rounded-lg border border-border p-3 text-left transition-all hover:border-primary hover:bg-primary/5 hover:shadow-sm dark:border-gray-800 dark:hover:bg-gray-950">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex min-w-0 items-start gap-2.5">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition group-hover:scale-105">
                                        <span :data-lucide="serviceIcon(service)" class="h-5 w-5"></span>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium" x-text="service.name"></p>
                                        <p class="mt-0.5 text-[10px] capitalize text-muted" x-text="service.pricing_type"></p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-md bg-primary/10 px-2 py-1 text-xs font-bold text-primary">
                                    {{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(service.price)"></span>
                                </span>
                            </div>
                        </button>
                    </template>
                    <div x-show="filteredCatalogCount === 0" class="col-span-full rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted dark:border-gray-800">
                        <span data-lucide="package" class="mx-auto mb-2 block h-8 w-8"></span>
                        No services match your filter.
                    </div>
                </div>
            </div>
        </section>

        <!-- RIGHT SIDE - CART -->
        <aside class="min-h-0 w-full md:self-stretch lg:w-80 xl:w-[22rem] xl:justify-self-end">
            <div class="flex h-full w-full min-w-0 flex-col overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <!-- Cart Header -->
                <div class="shrink-0 border-b border-border p-4 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span data-lucide="shopping-cart" class="h-5 w-5 text-muted"></span>
                            <div>
                                <h2 class="text-sm font-semibold">Cart</h2>
                                <p class="text-xs text-muted"><span x-text="items.length"></span> item<span x-show="items.length !== 1">s</span></p>
                            </div>
                        </div>
                        <button type="button" x-show="items.length" @click="items = []" title="Clear cart" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                            <span data-lucide="trash" class="h-4 w-4"></span>
                        </button>
                    </div>
                </div>

                <!-- Cart Items -->
                <div class="min-h-[6rem] flex-1 space-y-2 overflow-y-auto p-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="rounded-lg border border-border bg-white p-3 transition hover:border-primary/30 dark:border-gray-800 dark:bg-gray-950">
                            <input type="hidden" :name="item.type === 'preset' ? `items[${index}][service_preset_id]` : `items[${index}][laundry_service_id]`" :value="item.id">
                            <input type="hidden" :name="`items[${index}][description]`" :value="item.name">

                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="truncate text-sm font-medium" x-text="item.name"></p>
                                    </div>
                                    <p x-show="item.type === 'preset'" class="mt-0.5 truncate text-[10px] text-muted" x-text="item.summary"></p>
                                    <div class="mt-1 flex items-center gap-3">
                                        <div class="flex h-8 overflow-hidden rounded-md border border-border dark:border-gray-800">
                                            <button type="button" @click="item.quantity = Math.max(Number(item.quantity || 0) - 1, 0.01)" class="flex w-8 items-center justify-center hover:bg-smoke dark:hover:bg-gray-900 text-sm">−</button>
                                            <input type="number" step="0.01" min="0.01" :name="`items[${index}][quantity]`" x-model.number="item.quantity" aria-label="Quantity" class="w-12 border-x border-border bg-transparent text-center text-sm outline-none dark:border-gray-800">
                                            <button type="button" @click="item.quantity = Number(item.quantity || 0) + 1" class="flex w-8 items-center justify-center hover:bg-smoke dark:hover:bg-gray-900 text-sm">+</button>
                                        </div>
                                        <span class="text-xs text-muted">×</span>
                                        <div class="flex items-center rounded-md border border-border px-2 dark:border-gray-800">
                                            <span class="mr-1 text-[10px] text-muted">{{ $appSettings?->currency ?? 'PHP' }}</span>
                                            <input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`" x-model.number="item.price" aria-label="Unit price" class="w-16 bg-transparent py-1 text-right text-sm outline-none">
                                        </div>
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-2 text-right">
                                    <p class="text-sm font-semibold text-primary">{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(item.quantity * item.price)"></span></p>
                                    <button type="button" @click="items.splice(index, 1)" title="Remove" aria-label="Remove item" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-red-500 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-500/10">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div x-show="items.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border p-8 text-center dark:border-gray-800">
                        <span data-lucide="shopping-bag" class="mb-3 h-12 w-12 text-muted/40"></span>
                        <p class="text-sm text-muted">Your cart is empty</p>
                        <p class="text-xs text-muted/60">Tap services to add them</p>
                    </div>
                </div>

                <!-- Cart Footer - Totals & Action -->
                <div class="shrink-0 border-t border-border bg-smoke p-4 dark:border-gray-800 dark:bg-gray-950">
                    <!-- Totals -->
                    <div class="space-y-1.5">
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">Subtotal</span>
                            <span>{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(subtotal)" class="font-medium"></span></span>
                        </div>
                        <div class="border-t border-border pt-2 dark:border-gray-800">
                            <div class="flex justify-between text-base font-bold">
                                <span>Total</span>
                                <span class="text-primary">{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(total)"></span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <button type="button" @click="showPaymentPanel = true; refreshIcons()" :disabled="items.length === 0 || !selectedCustomerId" class="mt-3 h-10 w-full rounded-lg bg-primary text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="items.length === 0">Add items to continue</span>
                        <span x-show="items.length > 0 && !selectedCustomerId">Select a customer</span>
                        <span x-show="items.length > 0 && selectedCustomerId" class="flex items-center justify-center gap-2">
                            <span :data-lucide="isEditing ? 'save' : 'credit-card'" class="h-4 w-4"></span>
                            <span x-text="isEditing ? 'Review Changes' : 'Proceed to Payment'"></span>
                        </span>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Payment Modal -->
        <div x-cloak x-show="showPaymentPanel" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div @click.outside="showPaymentPanel = false" class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-semibold">
                        <span :data-lucide="isEditing ? 'save' : 'credit-card'" class="h-5 w-5 text-primary"></span>
                        <span x-text="isEditing ? 'Order Summary' : 'Payment'"></span>
                    </h2>
                    <button type="button" @click="showPaymentPanel = false" class="rounded-lg p-2 hover:bg-smoke dark:hover:bg-gray-800">
                        <span data-lucide="x" class="h-5 w-5"></span>
                    </button>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted">Subtotal</span>
                        <span>{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(subtotal)"></span></span>
                    </div>
                    
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">Discount</span>
                        <input name="discount" x-model.number="discount" type="number" min="0" step="0.01" class="h-9 w-28 rounded-lg border border-border px-3 text-right dark:border-gray-800 dark:bg-gray-950">
                    </div>
                    
                    <div class="border-t border-border py-2 dark:border-gray-800"></div>
                    
                    <div class="flex justify-between text-base font-bold">
                        <span>Total</span>
                        <span class="text-primary">{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(total)"></span></span>
                    </div>
                    
                    <div x-show="!isEditing" class="flex items-center justify-between gap-3">
                        <span class="text-muted">Paid</span>
                        <input name="paid_amount" x-model.number="paid" type="number" min="0" step="0.01" class="h-9 w-28 rounded-lg border border-border px-3 text-right dark:border-gray-800 dark:bg-gray-950">
                    </div>
                    <div x-show="isEditing" class="flex justify-between">
                        <span class="text-muted">Existing payments</span>
                        <span>{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(paid)"></span></span>
                    </div>
                    
                    <div class="flex justify-between font-semibold">
                        <span>Balance</span>
                        <span>{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(balance)"></span></span>
                    </div>
                    
                    <!-- Payment Type - Radio Card Style -->
                    <div x-show="!isEditing" class="space-y-2 pt-2">
                        <label class="text-xs font-medium text-muted">Payment Method</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-2.5 transition hover:border-primary/50 dark:border-gray-800" :class="paymentType === 'unpaid' ? 'border-primary bg-primary/5' : ''">
                                <input type="radio" name="payment_type" value="unpaid" x-model="paymentType" class="sr-only">
                                <div class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-muted" :class="paymentType === 'unpaid' ? 'border-primary bg-primary' : ''">
                                    <span x-show="paymentType === 'unpaid'" class="h-2 w-2 rounded-full bg-white"></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">Unpaid</span>
                                    <span class="text-[10px] text-muted">Charge to account</span>
                                </div>
                            </label>

                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-2.5 transition hover:border-primary/50 dark:border-gray-800" :class="paymentType === 'cash' ? 'border-primary bg-primary/5' : ''">
                                <input type="radio" name="payment_type" value="cash" x-model="paymentType" class="sr-only">
                                <div class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-muted" :class="paymentType === 'cash' ? 'border-primary bg-primary' : ''">
                                    <span x-show="paymentType === 'cash'" class="h-2 w-2 rounded-full bg-white"></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">Cash</span>
                                    <span class="text-[10px] text-muted">Pay with cash</span>
                                </div>
                            </label>

                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-2.5 transition hover:border-primary/50 dark:border-gray-800" :class="paymentType === 'gcash' ? 'border-primary bg-primary/5' : ''">
                                <input type="radio" name="payment_type" value="gcash" x-model="paymentType" class="sr-only">
                                <div class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-muted" :class="paymentType === 'gcash' ? 'border-primary bg-primary' : ''">
                                    <span x-show="paymentType === 'gcash'" class="h-2 w-2 rounded-full bg-white"></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">GCash</span>
                                    <span class="text-[10px] text-muted">Digital payment</span>
                                </div>
                            </label>

                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-2.5 transition hover:border-primary/50 dark:border-gray-800" :class="paymentType === 'po' ? 'border-primary bg-primary/5' : ''">
                                <input type="radio" name="payment_type" value="po" x-model="paymentType" class="sr-only">
                                <div class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-muted" :class="paymentType === 'po' ? 'border-primary bg-primary' : ''">
                                    <span x-show="paymentType === 'po'" class="h-2 w-2 rounded-full bg-white"></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">PO</span>
                                    <span class="text-[10px] text-muted">Purchase order</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Reference Number Field (shown for GCash and PO) -->
                    <div x-show="!isEditing && (paymentType === 'gcash' || paymentType === 'po')" x-transition.duration.200ms>
                        <input name="payment_reference_no" placeholder="Enter reference number..." class="h-9 w-full rounded-lg border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                    </div>
                    
                    <!-- SMS Notification -->
                    <label x-show="!isEditing" class="flex items-start gap-2 rounded-lg border border-border p-3 dark:border-gray-800" :class="canSendSms ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'">
                        <input type="checkbox" name="send_sms" value="1" :disabled="!canSendSms" class="mt-0.5 rounded border-border text-primary">
                        <span>
                            <span class="block text-sm font-medium">Send order received SMS</span>
                            <span class="block text-xs text-muted" x-text="smsAvailabilityMessage"></span>
                        </span>
                    </label>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button type="button" @click="showPaymentPanel = false" class="h-10 rounded-lg border border-border font-medium hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">Cancel</button>
                    <button type="submit" class="h-10 rounded-lg bg-primary font-semibold text-white hover:opacity-90" x-text="isEditing ? 'Save Changes' : 'Confirm Order'"></button>
                </div>
            </div>
        </div>
    </form>

    <!-- Quick Add Customer Modal -->
    <div x-cloak x-show="quickCustomerOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div @click.outside="quickCustomerOpen = false" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="inline-flex items-center gap-2 text-lg font-semibold">
                    <span data-lucide="user-plus" class="h-5 w-5 text-primary"></span>
                    Add Customer
                </h2>
                <button type="button" @click="quickCustomerOpen = false" class="rounded-lg p-2 hover:bg-smoke dark:hover:bg-gray-800">
                    <span data-lucide="x" class="h-5 w-5"></span>
                </button>
            </div>

            @include('admin.customers.partials.form', [
                'action' => route('admin.job-orders.customers.store'),
                'method' => 'POST',
                'customer' => new \App\Models\Customer(['branch_id' => $branchId, 'billing_type' => 'regular', 'is_active' => true, 'unpaid_limit' => 0]),
                'redirectTo' => 'pos',
                'branchModel' => 'branchId',
            ])
        </div>
    </div>
</div>

<script>
function posPage(branches, processingBranches, services, customers, serviceCategories, servicePresets, vatRate, vatEnabled, initialState = {}) {
    return {
        isEditing: Boolean(initialState.isEditing),
        branchId: @js((string) $branchId),
        branches: branches || [],
        processingBranches: processingBranches || [],
        processingBranchId: initialState.processingBranchId || '',
        services: services || [],
        servicePresets: servicePresets || [],
        customers: customers || [],
        serviceCategories: serviceCategories || [],
        items: initialState.initialItems || [],
        paymentType: initialState.paymentType || 'unpaid',
        discount: Number(initialState.discount || 0),
        paid: Number(initialState.paid || 0),
        showPaymentPanel: false,
        quickCustomerOpen: @js($errors->any() && old('redirect_to') === 'pos'),
        customerOpen: false,
        customerSearch: '',
        selectedCustomerId: initialState.selectedCustomerId || '',
        serviceSearch: '',
        typeFilter: 'all',
        vatRate: vatRate || 0,
        vatEnabled: vatEnabled || false,
        get serviceTypes() {
            const all = { value: 'all', label: 'All', icon: 'grid' };
            const cats = this.serviceCategories
                .filter(c => c.visibility === 'all' || String(c.branch_id) === String(this.branchId))
                .map(c => ({ value: c.id, label: c.name, icon: 'tag' }));
            return [all, ...cats];
        },
        init() {
            if (!this.processingBranchId) {
                this.setDefaultProcessingBranch();
            }
            this.syncSelectedCustomer();
            
            // Watch for branch changes
            this.$watch('branchId', () => {
                if (this.isEditing) {
                    return;
                }

                this.items = [];
                this.discount = 0;
                this.paid = 0;
                this.serviceSearch = '';
                this.setDefaultProcessingBranch();

                if (!this.selectedCustomerId) {
                    this.$nextTick(() => this.refreshIcons());
                    return;
                }

                const selected = this.customers.find(customer => String(customer.id) === String(this.selectedCustomerId));
                if (!selected || String(selected.branch_id) !== String(this.branchId)) {
                    this.clearCustomer();
                }

                this.$nextTick(() => this.refreshIcons());
            });
            
            // Watch for payment type changes
            this.$watch('paymentType', (value) => {
                if (value === 'unpaid' || value === 'po') {
                    this.paid = 0;
                    return;
                }

                if (['cash', 'gcash', 'bank'].includes(value) && Number(this.paid) <= 0) {
                    this.paid = Number(this.total);
                }
            });

            // Initial icon render
            this.$nextTick(() => this.refreshIcons());
        },
        get selectedBranch() {
            return this.branches.find(branch => String(branch.id) === String(this.branchId));
        },
        setDefaultProcessingBranch() {
            const branch = this.selectedBranch;
            const fullService = this.processingBranches.find(option => String(option.id) === String(this.branchId));

            if (branch && branch.branch_type !== 'pickup_dropoff' && fullService) {
                this.processingBranchId = fullService.id;
                return;
            }

            this.processingBranchId = this.processingBranches[0]?.id || '';
        },
        get availableCustomers() {
            return this.customers.filter(customer => String(customer.branch_id) === String(this.branchId));
        },
        get selectedCustomer() {
            return this.customers.find(customer => String(customer.id) === String(this.selectedCustomerId));
        },
        get canSendSms() {
            return Boolean(this.selectedCustomer?.phone) && this.selectedCustomer?.billing_type !== 'po';
        },
        get smsAvailabilityMessage() {
            if (this.selectedCustomer?.billing_type === 'po') {
                return 'PO customers do not receive SMS notifications.';
            }

            if (!this.selectedCustomer?.phone) {
                return 'Add a customer phone number to enable SMS.';
            }

            return 'Notify the customer that their laundry was received and added to the job order queue.';
        },
        get filteredCustomers() {
            const term = this.customerSearch.toLowerCase().trim();

            return this.availableCustomers.filter(customer => {
                const billing = this.formatBilling(customer.billing_type).toLowerCase();

                return !term
                    || customer.name.toLowerCase().includes(term)
                    || String(customer.phone || '').toLowerCase().includes(term)
                    || billing.includes(term);
            }).slice(0, 30);
        },
        selectCustomer(customer) {
            this.selectedCustomerId = customer.id;
            this.customerSearch = customer.name;
            this.customerOpen = false;
            this.$nextTick(() => this.refreshIcons());
        },
        clearCustomer() {
            this.selectedCustomerId = '';
            this.customerSearch = '';
            this.customerOpen = false;
            this.$nextTick(() => this.refreshIcons());
        },
        syncSelectedCustomer() {
            if (!this.selectedCustomerId) {
                return;
            }

            const selected = this.customers.find(customer => String(customer.id) === String(this.selectedCustomerId));
            if (selected) {
                this.selectCustomer(selected);
            }
        },
        formatBilling(value) {
            if (value === 'monthly_billing') return 'Legacy Billing';
            return String(value || 'regular').replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
        },
        serviceIcon(service) {
            const name = String(service.name || '').toLowerCase();

            if (name.includes('full service'))                           return 'star';
            if (name.includes('delivery'))                               return 'truck';
            if (name.includes('dry extension'))                          return 'timer';
            if (name.includes('dry clean'))                              return 'sparkles';
            if (name.includes('dry') || name.includes('dryer'))         return 'wind';
            if (name.includes('steam') || name.includes('iron'))        return 'zap';
            if (name.includes('fold'))                                   return 'shirt';
            if (name.includes('wash') || name.includes('handwash'))     return 'droplets';
            if (name.includes('bleach') || name.includes('stain'))      return 'flask-conical';
            if (name.includes('carpet'))                                 return 'layout-grid';
            if (name.includes('shoe'))                                   return 'footprints';
            if (name.includes('spin'))                                   return 'refresh-cw';
            if (name.includes('detergent') || name.includes('fabcon'))  return 'package';
            return 'sparkles';
        },
        refreshIcons() {
            this.$nextTick(() => {
                if (typeof window.renderLucideIcons === 'function') {
                    window.renderLucideIcons();
                }
            });
        },
        get availableServices() {
            return this.services.filter(service => String(service.branch_id) === String(this.branchId));
        },
        get availablePresets() {
            return this.servicePresets.filter(preset => String(preset.branch_id) === String(this.branchId) && preset.items.length > 0);
        },
        get filteredPresets() {
            const term = this.serviceSearch.toLowerCase().trim();
            return this.availablePresets.filter(preset => {
                const matchesType = this.typeFilter === 'all' || String(preset.service_category_id) === String(this.typeFilter);
                const matchesSearch = !term
                    || preset.name.toLowerCase().includes(term)
                    || preset.items.some(item => item.name.toLowerCase().includes(term));
                return matchesType && matchesSearch;
            });
        },
        get filteredServices() {
            const term = this.serviceSearch.toLowerCase().trim();
            return this.availableServices.filter(service => {
                const matchesType = this.typeFilter === 'all' || String(service.service_category_id) === String(this.typeFilter);
                const matchesSearch = !term || service.name.toLowerCase().includes(term);
                return matchesType && matchesSearch;
            });
        },
        get filteredCatalogCount() {
            return this.filteredPresets.length + this.filteredServices.length;
        },
        add(service) {
            const existing = this.items.find(item => item.type !== 'preset' && String(item.id) === String(service.id));
            if (existing) {
                existing.quantity = Number(existing.quantity) + 1;
            } else {
                this.items.push({ type: 'service', id: service.id, name: service.name, quantity: 1, price: Number(service.price) });
            }
            this.$nextTick(() => this.refreshIcons());
        },
        addPreset(preset) {
            const existing = this.items.find(item => item.type === 'preset' && String(item.id) === String(preset.id));
            if (existing) {
                existing.quantity = Number(existing.quantity || 0) + 1;
            } else {
                this.items.push({
                    type: 'preset',
                    id: preset.id,
                    name: preset.name,
                    summary: this.presetSummary(preset),
                    quantity: 1,
                    price: this.presetTotal(preset),
                });
            }
            this.$nextTick(() => this.refreshIcons());
        },
        presetTotal(preset) {
            return preset.items.reduce((sum, item) => sum + (Number(item.quantity || 0) * Number(item.price || 0)), 0);
        },
        presetSummary(preset) {
            return preset.items.map(item => `${Number(item.quantity || 0).toFixed(2).replace(/\.?0+$/, '')}x ${item.name}`).join(', ');
        },
        get subtotal() { 
            return this.items.reduce((sum, item) => sum + (Number(item.quantity || 0) * Number(item.price || 0)), 0); 
        },
        get tax() { 
            return this.vatEnabled ? Math.max(this.subtotal - Number(this.discount || 0), 0) * (Number(this.vatRate) / 100) : 0; 
        },
        get total() { 
            return Math.max(this.subtotal - Number(this.discount || 0), 0) + this.tax; 
        },
        get balance() { 
            return Math.max(this.total - Number(this.paid || 0), 0); 
        },
        money(value) { 
            return Number(value || 0).toFixed(2); 
        }
    }
}
</script>
@endsection
