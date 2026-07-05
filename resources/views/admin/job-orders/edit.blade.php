@extends('layouts.app')

@section('page_title', 'Edit Job Order')
@section('hide_footer', true)

@section('content')
<div
    x-data="editJobOrder(@js($services), @js($customers), @js($initialItems), @js((float) ($appSettings?->vat_rate ?? 0)), @js((bool) ($appSettings?->vat_enabled ?? false)))"
>
    <form
        method="POST"
        action="{{ route('admin.job-orders.update', $jobOrder) }}"
        class="grid gap-4 md:grid-cols-[minmax(0,1fr)_22rem]"
    >
        @csrf
        @method('PUT')

        <section class="space-y-4">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                            <span data-lucide="jobOrders" class="h-3.5 w-3.5"></span>
                            Previous job order edit
                        </div>
                        <h1 class="text-xl font-semibold">{{ $jobOrder->job_order_number }}</h1>
                        <p class="text-sm text-muted">{{ $jobOrder->branch?->name }} - created {{ $jobOrder->created_at?->format('M d, Y h:i A') }}</p>
                    </div>
                    <a href="{{ route('admin.job-orders.index') }}" class="inline-flex h-9 items-center justify-center rounded-md border border-border px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                        Back to Orders
                    </a>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-medium">Status
                        <select name="status" class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $jobOrder->status) === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="text-sm font-medium">Customer
                        <input type="hidden" name="customer_id" :value="selectedCustomerId">
                        <div class="relative mt-1.5" @click.outside="customerOpen = false">
                            <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                                <span data-lucide="search" class="h-4 w-4 shrink-0 text-muted"></span>
                                <input type="search" x-model="customerSearch" @focus="customerOpen = true" @input="selectedCustomerId = ''; customerOpen = true" class="min-w-0 flex-1 bg-transparent text-sm outline-none" autocomplete="off">
                            </div>

                            <div x-cloak x-show="customerOpen" x-transition class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-border bg-white p-1 shadow-lg dark:border-gray-800 dark:bg-gray-950">
                                <template x-for="customer in filteredCustomers" :key="customer.id">
                                    <button type="button" @click="selectCustomer(customer)" class="flex w-full items-center justify-between gap-3 rounded-sm px-3 py-2 text-left text-sm hover:bg-smoke dark:hover:bg-gray-900">
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium" x-text="customer.name"></span>
                                            <span class="block truncate text-xs text-muted" x-text="`${customer.phone || 'No phone'} - ${formatBilling(customer.billing_type)}`"></span>
                                        </span>
                                        <span x-show="String(selectedCustomerId) === String(customer.id)" data-lucide="check" class="h-4 w-4 shrink-0 text-primary"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </label>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-1 rounded-md bg-smoke p-1 dark:bg-gray-950">
                    <label class="flex h-8 cursor-pointer items-center justify-center rounded-sm text-xs font-medium has-[:checked]:bg-white has-[:checked]:text-primary has-[:checked]:shadow-sm dark:has-[:checked]:bg-gray-900">
                        <input type="radio" name="transaction_type" value="walk_in" @checked(old('transaction_type', $jobOrder->transaction_type) !== 'delivery') class="sr-only">
                        Walk-in / Drop Off
                    </label>
                    <label class="flex h-8 cursor-pointer items-center justify-center rounded-sm text-xs font-medium has-[:checked]:bg-orange-100 has-[:checked]:text-orange-700 has-[:checked]:shadow-sm dark:has-[:checked]:bg-orange-500/10 dark:has-[:checked]:text-orange-300">
                        <input type="radio" name="transaction_type" value="delivery" @checked(old('transaction_type', $jobOrder->transaction_type) === 'delivery') class="sr-only">
                        Delivery / Pick-up
                    </label>
                </div>

                <label class="mt-3 flex h-10 cursor-pointer items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 text-sm font-medium text-amber-800 dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-300">
                    <input type="checkbox" name="is_rush" value="1" @checked(old('is_rush', $jobOrder->is_rush)) class="rounded border-amber-300 text-amber-600">
                    Rush order
                </label>

                <label class="mt-3 block text-sm font-medium">Receiving Production Branch
                    <select name="processing_branch_id" class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950" required>
                        @foreach($processingBranches as $processingBranch)
                            <option value="{{ $processingBranch->id }}" @selected((int) old('processing_branch_id', $jobOrder->processing_branch_id ?: $jobOrder->branch_id) === (int) $processingBranch->id)>
                                {{ $processingBranch->name }} - receives by QR scan
                            </option>
                        @endforeach
                    </select>
                    @if(($jobOrder->branch?->branch_type ?? 'full_service') === 'pickup_dropoff')
                        <span class="mt-1 block text-xs font-normal text-muted">
                            Pickup/drop-off orders are assigned here first, then added to this branch cycle only after QR scan acceptance.
                        </span>
                    @endif
                </label>

                <textarea name="notes" rows="3" placeholder="Notes / instructions" class="mt-3 w-full rounded-md border border-border bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-950">{{ old('notes', $jobOrder->notes) }}</textarea>
            </div>

            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold">Service Catalog</h2>
                        <p class="text-sm text-muted">Add services to update the job order items.</p>
                    </div>
                    <div class="flex h-9 items-center gap-2 rounded-md border border-border px-3 dark:border-gray-800 sm:w-72">
                        <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                        <input type="search" x-model.debounce.200ms="serviceSearch" placeholder="Search services..." class="w-full bg-transparent text-sm outline-none">
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <template x-for="service in filteredServices" :key="service.id">
                        <button type="button" @click="add(service)" class="rounded-md border border-border p-3 text-left transition hover:border-primary hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium" x-text="service.name"></p>
                                    <p class="mt-1 text-xs capitalize text-muted" x-text="service.pricing_type"></p>
                                </div>
                                <span class="shrink-0 rounded-md bg-smoke px-2 py-1 text-xs font-medium text-primary dark:bg-gray-950">
                                    {{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(service.price)"></span>
                                </span>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </section>

        <aside class="space-y-4">
            <div class="rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-border p-4 dark:border-gray-800">
                    <h2 class="font-semibold">Items</h2>
                    <p class="text-sm text-muted"><span x-text="items.length"></span> item<span x-show="items.length !== 1">s</span></p>
                </div>

                <div class="max-h-[26rem] space-y-2 overflow-y-auto p-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="rounded-md border border-border bg-white p-2 dark:border-gray-800 dark:bg-gray-950">
                            <input type="hidden" :name="`items[${index}][laundry_service_id]`" :value="item.id">
                            <input type="hidden" :name="`items[${index}][description]`" :value="item.name">

                            <div class="mb-2 flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-medium" x-text="item.name"></p>
                            </div>

                            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-1.5">
                                <div class="grid grid-cols-[5.5rem_1fr] gap-1.5">
                                    <input type="number" step="0.01" min="0.01" :name="`items[${index}][quantity]`" x-model.number="item.quantity" aria-label="Quantity" class="h-8 rounded-md border border-border bg-transparent px-2 text-center text-xs outline-none dark:border-gray-800">
                                    <div class="flex h-8 items-center rounded-md border border-border px-2 dark:border-gray-800">
                                        <span class="mr-1 text-[11px] text-muted">{{ $appSettings?->currency ?? 'PHP' }}</span>
                                        <input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`" x-model.number="item.price" aria-label="Unit price" class="w-full bg-transparent text-right text-xs outline-none">
                                    </div>
                                </div>
                                <button type="button" @click="items.splice(index, 1)" title="Remove item" aria-label="Remove item" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>

                    <div x-show="items.length === 0" class="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted dark:border-gray-800">
                        Add at least one service.
                    </div>
                </div>

                <div class="space-y-2 border-t border-border p-4 text-sm dark:border-gray-800">
                    <div class="flex justify-between"><span class="text-muted">Subtotal</span><span>{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(subtotal)"></span></span></div>
                    <div class="flex h-9 items-center justify-between gap-3">
                        <span class="text-muted">Discount</span>
                        <input name="discount" x-model.number="discount" type="number" min="0" step="0.01" class="h-9 w-28 rounded-md border border-border px-2 text-right text-sm dark:border-gray-800 dark:bg-gray-950">
                    </div>
                    <div class="flex justify-between"><span class="text-muted">VAT</span><span>{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(tax)"></span></span></div>
                    <div class="h-px bg-border dark:bg-gray-800"></div>
                    <div class="flex justify-between text-base font-semibold"><span>Total</span><span>{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(total)"></span></span></div>
                    <div class="flex justify-between text-xs"><span class="text-muted">Existing payments</span><span>{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $jobOrder->payments->sum('amount'), 2) }}</span></div>
                    <div class="flex justify-between text-sm font-medium"><span>New balance</span><span>{{ $appSettings?->currency ?? 'PHP' }} <span x-text="money(balance)"></span></span></div>
                </div>
            </div>

            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid grid-cols-2 gap-2">
                <a href="{{ route('admin.job-orders.show', $jobOrder) }}" class="inline-flex h-10 items-center justify-center rounded-md border border-border text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">Cancel</a>
                <button type="submit" class="h-10 rounded-md bg-primary text-sm font-medium text-white hover:opacity-90">Save Changes</button>
            </div>
        </aside>
    </form>
</div>

<script>
function editJobOrder(services, customers, initialItems, vatRate, vatEnabled) {
    return {
        services,
        customers,
        items: initialItems,
        discount: @js((float) old('discount', $jobOrder->discount)),
        paid: @js((float) $jobOrder->payments->sum('amount')),
        customerOpen: false,
        customerSearch: '',
        selectedCustomerId: @js((string) old('customer_id', $selectedCustomerId)),
        serviceSearch: '',
        init() {
            this.syncSelectedCustomer();
        },
        get filteredCustomers() {
            const term = this.customerSearch.toLowerCase().trim();
            return this.customers.filter(customer => !term
                || customer.name.toLowerCase().includes(term)
                || String(customer.phone || '').toLowerCase().includes(term)
                || this.formatBilling(customer.billing_type).toLowerCase().includes(term)
            ).slice(0, 30);
        },
        get filteredServices() {
            const term = this.serviceSearch.toLowerCase().trim();
            return this.services.filter(service => !term || service.name.toLowerCase().includes(term));
        },
        selectCustomer(customer) {
            this.selectedCustomerId = customer.id;
            this.customerSearch = `${customer.name} - ${this.formatBilling(customer.billing_type)}`;
            this.customerOpen = false;
            this.$nextTick(() => window.renderLucideIcons());
        },
        syncSelectedCustomer() {
            const selected = this.customers.find(customer => String(customer.id) === String(this.selectedCustomerId));
            if (selected) {
                this.selectCustomer(selected);
            }
        },
        formatBilling(value) {
            if (value === 'monthly_billing') return 'Legacy Billing';

            return String(value || 'regular').replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
        },
        add(service) {
            const existing = this.items.find(item => Number(item.id) === Number(service.id));
            if (existing) {
                existing.quantity = Number(existing.quantity || 0) + 1;
            } else {
                this.items.push({ id: service.id, name: service.name, quantity: 1, price: Number(service.price) });
            }
            this.$nextTick(() => window.renderLucideIcons());
        },
        get subtotal() { return this.items.reduce((sum, item) => sum + (Number(item.quantity || 0) * Number(item.price || 0)), 0); },
        get tax() { return vatEnabled ? Math.max(this.subtotal - Number(this.discount || 0), 0) * (Number(vatRate) / 100) : 0; },
        get total() { return Math.max(this.subtotal - Number(this.discount || 0), 0) + this.tax; },
        get balance() { return Math.max(this.total - Number(this.paid || 0), 0); },
        money(value) { return Number(value || 0).toFixed(2); },
    }
}
</script>
@endsection
