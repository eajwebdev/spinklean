@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('content')
@php
    $tabs = [
        'overview' => ['Overview', 'dashboard'],
        'sales' => ['Sales', 'payments'],
        'expenses' => ['Expenses', 'expense'],
        'po' => ['PO / Corporate', 'file-text'],
        'operations' => ['Operations', 'activity'],
        'customers' => ['Customers', 'customers'],
    ];
    $canOpenPo = auth()->user()->isAdmin() || auth()->user()->hasMenuAccess('po_transactions');
@endphp
<div
    x-data="dashboardPage(@js(route('dashboard.data', request()->query())), @js($dashboardData), @js($dateRangeValue))"
    class="space-y-4"
>
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="dashboard" class="h-3.5 w-3.5"></span>
                {{ $canChooseBranch ? 'Executive overview' : 'Branch command center' }}
            </div>
            <h1 class="text-xl font-semibold tracking-normal">
                {{ $canChooseBranch ? 'Business Dashboard' : auth()->user()->branch?->name.' Dashboard' }}
            </h1>
            <p class="text-sm text-muted">
                Sales include PO payments on the day the customer pays.
                <span class="ml-1" x-text="`Updated ${data.generated_at}`"></span>
            </p>
        </div>

        <form method="GET" action="{{ route('dashboard') }}" class="grid grid-cols-1 gap-2 sm:grid-cols-[12rem_16rem_auto]">
            @if($canChooseBranch)
                <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
            @endif

            <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="calendar" class="h-4 w-4 text-muted"></span>
                <input x-ref="dateRange" x-model="dateRange" name="date_range" type="text" class="w-full bg-transparent text-sm outline-none" autocomplete="off">
            </div>

            <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
                <span data-lucide="search" class="h-4 w-4"></span>
                Apply
            </button>
        </form>
    </div>

    <div class="flex gap-1 overflow-x-auto rounded-lg border border-border bg-white p-1 shadow-sm dark:border-gray-800 dark:bg-gray-900" role="tablist">
        @foreach($tabs as $key => [$label, $icon])
            <button
                type="button"
                role="tab"
                @click="showTab('{{ $key }}')"
                :aria-selected="tab === '{{ $key }}'"
                class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md px-3 text-sm font-medium transition"
                :class="tab === '{{ $key }}' ? 'bg-primary text-white' : 'text-muted hover:bg-smoke dark:hover:bg-gray-950'"
            >
                <span data-lucide="{{ $icon }}" class="h-4 w-4"></span>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <template x-for="card in statCards[tab]" :key="tab + card.key">
            <div class="rounded-lg border bg-white p-4 shadow-sm dark:bg-gray-900" :class="card.tone === 'warn' ? 'border-amber-200 dark:border-amber-900/60' : 'border-border dark:border-gray-800'">
                <p class="text-xs font-medium text-muted" x-text="card.label"></p>
                <p class="mt-1 text-lg font-semibold" :class="card.tone === 'warn' ? 'text-amber-700 dark:text-amber-300' : ''" x-text="data.stats[card.key]"></p>
                <p x-show="card.hint" class="mt-0.5 text-xs text-muted" x-text="card.hint"></p>
            </div>
        </template>
    </div>

    {{-- Overview --}}
    <div x-show="tab === 'overview'" class="space-y-4">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.8fr)]">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Sales Trend</h2>
                <p class="mb-4 text-sm text-muted">Walk-in and POS payments, and PO customer payments, per day.</p>
                <div class="h-72"><canvas x-ref="salesChart"></canvas></div>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Financial Snapshot</h2>
                <p class="mb-4 text-sm text-muted">Collections, expenses and what is owed.</p>
                <div class="h-72"><canvas x-ref="financialChart"></canvas></div>
            </div>
        </div>
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-base font-semibold">Branch Sales</h2>
            <p class="mb-4 text-sm text-muted">Sales owned by each branch in the selected range, PO payments included.</p>
            <div class="h-64"><canvas x-ref="branchSalesChart"></canvas></div>
        </div>
    </div>

    {{-- Sales --}}
    <div x-show="tab === 'sales'" x-cloak class="space-y-4">
        <div class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Payment Mix</h2>
                <p class="mb-4 text-sm text-muted">Money received by method. PO cheques are shown on their own.</p>
                <div class="h-64"><canvas x-ref="paymentMixChart"></canvas></div>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">POS Type Mix</h2>
                <p class="mb-4 text-sm text-muted">Walk-in/drop-off versus delivery orders.</p>
                <div class="h-64"><canvas x-ref="transactionTypeChart"></canvas></div>
            </div>
        </div>
        <div class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Top Selling Services</h2>
                <p class="mb-4 text-sm text-muted">Ranked by service sales amount in the selected range.</p>
                <div class="h-72"><canvas x-ref="topServicesChart"></canvas></div>
                <div class="mt-3 divide-y divide-border text-sm dark:divide-gray-800">
                    <template x-for="service in data.top_services" :key="service.label">
                        <div class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate font-medium" x-text="service.label"></span>
                            <span class="shrink-0 text-xs text-muted" x-text="`${service.quantity} qty · ${service.amount}`"></span>
                        </div>
                    </template>
                    <p x-show="data.top_services.length === 0" class="py-6 text-center text-sm text-muted">No service sales in this date range.</p>
                </div>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Top Selling Presets</h2>
                <p class="mb-4 text-sm text-muted">Preset sales from POS preset cart selections.</p>
                <div class="h-72"><canvas x-ref="topPresetsChart"></canvas></div>
                <div class="mt-3 divide-y divide-border text-sm dark:divide-gray-800">
                    <template x-for="preset in data.top_presets" :key="preset.label">
                        <div class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate font-medium" x-text="preset.label"></span>
                            <span class="shrink-0 text-xs text-muted" x-text="`${preset.orders_count} order(s) · ${preset.amount}`"></span>
                        </div>
                    </template>
                    <p x-show="data.top_presets.length === 0" class="py-6 text-center text-sm text-muted">Preset sales will appear for new orders saved from preset cart selections.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Expenses --}}
    <div x-show="tab === 'expenses'" x-cloak class="space-y-4">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.8fr)]">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Sales vs Expenses</h2>
                <p class="mb-4 text-sm text-muted">Everything received (PO included) against recorded expenses, per day.</p>
                <div class="h-72"><canvas x-ref="salesExpenseChart"></canvas></div>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Expenses by Category</h2>
                <p class="mb-4 text-sm text-muted">Where the money went.</p>
                <div class="h-72"><canvas x-ref="expenseCategoryChart"></canvas></div>
            </div>
        </div>
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.8fr)]">
            <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-border px-4 py-3 dark:border-gray-800">
                    <h2 class="text-base font-semibold">Latest Expenses</h2>
                    @if(auth()->user()->isAdmin() || auth()->user()->hasMenuAccess('expenses'))
                        <a href="{{ route('admin.expenses.index') }}" class="inline-flex h-8 items-center rounded-md border border-border px-3 text-sm hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">View all</a>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                            <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Expense</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Paid From</th><th class="px-4 py-3 text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border dark:divide-gray-800">
                            <template x-for="expense in data.recent_expenses" :key="expense.id">
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3" x-text="expense.date"></td>
                                    <td class="px-4 py-3 font-medium" x-text="expense.title"></td>
                                    <td class="px-4 py-3" x-text="expense.category"></td>
                                    <td class="px-4 py-3" x-text="expense.branch"></td>
                                    <td class="px-4 py-3" x-text="expense.paid_from"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-medium" x-text="expense.amount"></td>
                                </tr>
                            </template>
                            <tr x-show="data.recent_expenses.length === 0"><td colspan="6" class="px-4 py-10 text-center text-muted">No expenses recorded in this date range.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-base font-semibold">Category Totals</h2>
                <div class="divide-y divide-border text-sm dark:divide-gray-800">
                    <template x-for="category in data.expense_categories" :key="category.label">
                        <div class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate font-medium" x-text="category.label"></span>
                            <span class="shrink-0 text-xs text-muted" x-text="`${category.entries} entr${category.entries === '1' ? 'y' : 'ies'} · ${category.amount}`"></span>
                        </div>
                    </template>
                    <p x-show="data.expense_categories.length === 0" class="py-6 text-center text-sm text-muted">No expenses in this date range.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- PO / Corporate --}}
    <div x-show="tab === 'po'" x-cloak class="space-y-4">
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Delivered vs Paid</h2>
                    <p class="text-sm text-muted">PO laundry billed on delivery receipts, and PO payments received, per day.</p>
                </div>
                @if($canOpenPo)
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.po-deliveries.create') }}" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-sm hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950"><span data-lucide="truck" class="h-3.5 w-3.5"></span>New DR</a>
                        <a href="{{ route('admin.po-transactions.statement-of-account') }}" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90"><span data-lucide="file-text" class="h-3.5 w-3.5"></span>Statement of Account</a>
                    </div>
                @endif
            </div>
            <div class="h-64"><canvas x-ref="poTrendChart"></canvas></div>
        </div>
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(20rem,0.7fr)]">
            <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-border px-4 py-3 dark:border-gray-800">
                    <h2 class="text-base font-semibold">PO Customers with a Balance</h2>
                    <p class="text-sm text-muted">Owed = delivered but not yet paid. Not yet delivered is billed when it goes out on a DR.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                            <tr><th class="px-4 py-3">Customer</th><th class="px-4 py-3 text-center">Open Orders</th><th class="px-4 py-3 text-right">Owed</th><th class="px-4 py-3 text-right">Not Yet Delivered</th><th class="px-4 py-3">Last Payment</th><th class="px-4 py-3"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-border dark:divide-gray-800">
                            <template x-for="customer in data.po_customers" :key="customer.id">
                                <tr>
                                    <td class="px-4 py-3 font-medium" x-text="customer.name"></td>
                                    <td class="px-4 py-3 text-center" x-text="customer.open_orders"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400" x-text="customer.owed"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-amber-700 dark:text-amber-300" x-text="customer.not_delivered"></td>
                                    <td class="whitespace-nowrap px-4 py-3" x-text="customer.last_paid"></td>
                                    <td class="px-4 py-3 text-right">
                                        @if($canOpenPo)
                                            <a :href="customer.soa_url" class="whitespace-nowrap text-sm font-medium text-primary hover:underline">Statement</a>
                                        @endif
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="data.po_customers.length === 0"><td colspan="6" class="px-4 py-10 text-center text-muted">No PO customer has a balance.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-base font-semibold">Latest Delivery Receipts</h2>
                <div class="divide-y divide-border text-sm dark:divide-gray-800">
                    <template x-for="delivery in data.recent_deliveries" :key="delivery.id">
                        <a :href="delivery.url" target="_blank" class="flex items-center justify-between gap-3 py-2 hover:text-primary">
                            <span class="min-w-0">
                                <span class="block truncate font-medium" x-text="`DR #${delivery.dr_number} · ${delivery.customer}`"></span>
                                <span class="block text-xs text-muted" x-text="delivery.date"></span>
                            </span>
                            <span class="shrink-0 font-medium" x-text="delivery.amount"></span>
                        </a>
                    </template>
                    <p x-show="data.recent_deliveries.length === 0" class="py-6 text-center text-sm text-muted">No delivery receipts yet.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Operations --}}
    <div x-show="tab === 'operations'" x-cloak class="space-y-4">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Workflow Status</h2>
                <p class="mb-4 text-sm text-muted">Job orders created in the selected range, by status.</p>
                <div class="h-72"><canvas x-ref="statusChart"></canvas></div>
            </div>
            <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-border px-4 py-3 dark:border-gray-800">
                    <div>
                        <h2 class="text-base font-semibold">Recent Job Orders</h2>
                        <p class="text-sm text-muted">Live latest transactions.</p>
                    </div>
                    <a href="{{ route('admin.job-orders.index') }}" class="inline-flex h-8 items-center rounded-md border border-border px-3 text-sm hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                            <tr><th class="px-4 py-3">JO #</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Total</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border dark:divide-gray-800">
                            <template x-for="order in data.recent_orders" :key="order.id">
                                <tr>
                                    <td class="px-4 py-3"><a :href="order.url" class="font-medium hover:text-primary" x-text="order.number"></a></td>
                                    <td class="px-4 py-3" x-text="order.customer"></td>
                                    <td class="px-4 py-3" x-text="order.branch"></td>
                                    <td class="px-4 py-3"><span :class="order.status_badge" x-text="order.status"></span></td>
                                    <td class="px-4 py-3 text-right font-medium" x-text="order.total"></td>
                                </tr>
                            </template>
                            <tr x-show="data.recent_orders.length === 0"><td colspan="5" class="px-4 py-10 text-center text-muted">No recent job orders.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Customers --}}
    <div x-show="tab === 'customers'" x-cloak class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-2 border-b border-border px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold">Customers Who Trust {{ $appBusinessName }}</h2>
                <p class="text-sm text-muted">Customers with 10 or more laundry orders, ranked by total orders entrusted to the store.</p>
            </div>
            @if(auth()->user()->hasMenuAccess('customers'))
                <a href="{{ route('admin.customers.index') }}" class="inline-flex h-8 items-center justify-center rounded-md border border-border px-3 text-sm hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">View customers</a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3 text-center">Laundry Orders</th><th class="px-4 py-3">Latest Service</th><th class="px-4 py-3">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    <template x-for="customer in data.trusted_customers" :key="customer.id">
                        <tr>
                            <td class="px-4 py-3 font-medium" x-text="customer.name"></td>
                            <td class="px-4 py-3 text-muted" x-text="customer.phone"></td>
                            <td class="px-4 py-3" x-text="customer.branch"></td>
                            <td class="px-4 py-3 text-center font-semibold" x-text="customer.orders_count"></td>
                            <td class="px-4 py-3" x-text="customer.latest_order"></td>
                            <td class="px-4 py-3"><span :class="customer.status_badge" x-text="customer.status"></span></td>
                        </tr>
                    </template>
                    <tr x-show="data.trusted_customers.length === 0"><td colspan="6" class="px-4 py-10 text-center text-muted">Customer laundry history will appear here after the first job order.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function dashboardPage(fetchUrl, initialData, initialDateRange) {
    const TAB_KEY = 'spinklean:dashboard-tab';
    const TABS = ['overview', 'sales', 'expenses', 'po', 'operations', 'customers'];

    return {
        data: initialData,
        dateRange: initialDateRange,
        tab: 'overview',
        charts: {},
        statCards: {
            overview: [
                { key: 'sales', label: 'Total Sales', hint: 'All money received, PO included' },
                { key: 'expenses', label: 'Recorded Expenses' },
                { key: 'net', label: 'Sales less Expenses' },
                { key: 'collections', label: 'Physical Collections', hint: 'Cash + GCash + bank' },
                { key: 'cash_drawer', label: 'Expected Cash Drawer' },
                { key: 'receivables', label: 'Unpaid Walk-in Balance', tone: 'warn' },
                { key: 'po_receivables', label: 'PO Owed (delivered, unpaid)', tone: 'warn' },
                { key: 'open_orders', label: 'Open Orders' },
            ],
            sales: [
                { key: 'sales', label: 'Total Sales' },
                { key: 'regular_sales', label: 'Walk-in / POS Payments' },
                { key: 'po_payments', label: 'PO Payments', hint: 'Counted on the day paid' },
                { key: 'average_order', label: 'Average Order Value' },
                { key: 'cash_collections', label: 'Cash Received' },
                { key: 'gcash_collections', label: 'GCash Received' },
                { key: 'bank_collections', label: 'Bank & Cheque Received' },
                { key: 'orders', label: 'Orders in Period' },
            ],
            expenses: [
                { key: 'expenses', label: 'Recorded Expenses' },
                { key: 'store_expenses', label: 'Paid by Store' },
                { key: 'owner_expenses', label: 'Paid by Owner' },
                { key: 'accounts_payable', label: 'Accounts Payable', tone: 'warn' },
                { key: 'net', label: 'Sales less Expenses' },
                { key: 'cash_drawer', label: 'Expected Cash Drawer' },
                { key: 'gcash', label: 'Expected GCash' },
                { key: 'over_short', label: 'Z Reading Over / Short' },
            ],
            po: [
                { key: 'po_payments', label: 'PO Payments Received', hint: 'In the selected range' },
                { key: 'po_delivered', label: 'Delivered (billed)', hint: 'On DRs in the selected range' },
                { key: 'po_receivables', label: 'Owed Now', hint: 'Delivered, not yet paid', tone: 'warn' },
                { key: 'po_not_yet_delivered', label: 'Not Yet Delivered', hint: 'Billed once delivered' },
                { key: 'po_orders', label: 'PO Orders in Period' },
            ],
            operations: [
                { key: 'orders', label: 'Orders in Period' },
                { key: 'open_orders', label: 'Open Orders' },
                { key: 'ready_for_pickup', label: 'Ready for Pickup' },
                { key: 'ready_for_delivery', label: 'Ready for Delivery' },
                { key: 'low_stock', label: 'Low Stock Items', tone: 'warn' },
            ],
            customers: [
                { key: 'receivables', label: 'Unpaid Walk-in Balance', tone: 'warn' },
                { key: 'po_receivables', label: 'PO Owed', tone: 'warn' },
                { key: 'orders', label: 'Orders in Period' },
                { key: 'average_order', label: 'Average Order Value' },
            ],
        },
        init() {
            try {
                const saved = localStorage.getItem(TAB_KEY);
                if (TABS.includes(saved)) this.tab = saved;
            } catch (e) {}

            this.$nextTick(() => {
                this.initDateRange();
                this.drawTab(this.tab);
                window.renderLucideIcons();
            });

            window.setInterval(() => this.refresh(), 30000);
        },
        showTab(key) {
            this.tab = key;
            try { localStorage.setItem(TAB_KEY, key); } catch (e) {}
            // Charts are drawn when their tab is first shown, so they get a real size.
            this.$nextTick(() => this.drawTab(key));
        },
        initDateRange() {
            if (!window.flatpickr) return;

            window.flatpickr(this.$refs.dateRange, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                defaultDate: this.dateRange ? this.dateRange.split(' to ') : null,
                onClose: (dates, value) => this.dateRange = value,
            });
        },
        refresh() {
            fetch(fetchUrl, { headers: { 'Accept': 'application/json' } })
                .then(response => response.json())
                .then(payload => {
                    this.data = payload;
                    this.updateCharts();
                    this.$nextTick(() => window.renderLucideIcons());
                });
        },
        colors() {
            const primary = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#2E7D32';
            return {
                primary,
                grid: document.documentElement.classList.contains('dark') ? '#1f2937' : '#e2e8f0',
                palette: [primary, '#0ea5e9', '#f59e0b', '#10b981', '#6366f1', '#ef4444', '#14b8a6', '#8b5cf6'],
            };
        },
        chartDefinitions() {
            const c = this.colors();
            const sales = this.data.charts.sales;
            const poTrend = this.data.charts.po_trend;
            const series = (label, values, color, extra = {}) => ({ label, data: values, borderColor: color, backgroundColor: color + (extra.fill ? '22' : ''), borderRadius: 6, tension: 0.35, ...extra });

            return {
                overview: {
                    salesChart: () => ({ type: 'line', data: { labels: sales.labels, datasets: [series('Walk-in / POS', sales.values, c.primary, { fill: true }), series('PO payments', sales.po_values, '#6366f1')] }, options: this.chartOptions(c.grid, true) }),
                    financialChart: () => ({ type: 'bar', data: this.dataset('financial_snapshot', c.palette), options: this.chartOptions(c.grid) }),
                    branchSalesChart: () => ({ type: 'bar', data: this.dataset('branch_sales', c.palette), options: this.horizontalOptions(c.grid) }),
                },
                sales: {
                    paymentMixChart: () => ({ type: 'doughnut', data: this.dataset('payment_mix', c.palette), options: this.pieOptions() }),
                    transactionTypeChart: () => ({ type: 'pie', data: this.dataset('transaction_types', c.palette), options: this.pieOptions() }),
                    topServicesChart: () => ({ type: 'bar', data: this.dataset('top_services', c.palette), options: this.horizontalOptions(c.grid) }),
                    topPresetsChart: () => ({ type: 'bar', data: this.dataset('top_presets', c.palette), options: this.horizontalOptions(c.grid) }),
                },
                expenses: {
                    salesExpenseChart: () => ({ type: 'bar', data: { labels: sales.labels, datasets: [series('Sales', sales.values.map((value, i) => value + (sales.po_values[i] || 0)), c.primary), series('Expenses', sales.expense_values, '#ef4444')] }, options: this.chartOptions(c.grid, true) }),
                    expenseCategoryChart: () => ({ type: 'doughnut', data: this.dataset('expense_categories', c.palette), options: this.pieOptions() }),
                },
                po: {
                    poTrendChart: () => ({ type: 'bar', data: { labels: poTrend.labels, datasets: [series('Delivered (billed)', poTrend.delivered, '#f59e0b'), series('Paid', poTrend.paid, c.primary)] }, options: this.chartOptions(c.grid, true) }),
                },
                operations: {
                    statusChart: () => ({ type: 'bar', data: { labels: this.data.charts.status.labels, datasets: [series('Orders', this.data.charts.status.values, c.primary, { backgroundColor: c.primary })] }, options: this.chartOptions(c.grid) }),
                },
                customers: {},
            };
        },
        drawTab(key) {
            const definitions = this.chartDefinitions()[key] || {};
            Object.entries(definitions).forEach(([ref, build]) => {
                if (!this.$refs[ref]) return;
                if (this.charts[ref]) {
                    this.charts[ref].resize();
                    return;
                }
                this.charts[ref] = new window.Chart(this.$refs[ref], build());
            });
        },
        updateCharts() {
            // Redraw the charts already shown with the refreshed numbers.
            const all = this.chartDefinitions();
            Object.values(all).forEach(definitions => Object.entries(definitions).forEach(([ref, build]) => {
                const chart = this.charts[ref];
                if (!chart) return;
                const fresh = build();
                chart.data.labels = fresh.data.labels;
                fresh.data.datasets.forEach((dataset, index) => {
                    if (chart.data.datasets[index]) chart.data.datasets[index].data = dataset.data;
                });
                chart.update();
            }));
        },
        dataset(key, colors) {
            return {
                labels: this.data.charts[key].labels,
                datasets: [{
                    label: 'Amount',
                    data: this.data.charts[key].values,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderRadius: 6,
                }]
            };
        },
        chartOptions(grid, legend = false) {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: legend, position: 'bottom' } },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: '#64748B' } },
                    y: { beginAtZero: true, grid: { color: grid }, ticks: { color: '#64748B', precision: 0 } },
                }
            };
        },
        horizontalOptions(grid) {
            const options = this.chartOptions(grid);
            options.indexAxis = 'y';
            return options;
        },
        pieOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            };
        }
    }
}
</script>
@endsection
