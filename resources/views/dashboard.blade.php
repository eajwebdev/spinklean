@extends('layouts.app')

@section('page_title', 'Dashboard')

@php
    $user = auth()->user();
    $panel = 'rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900';
    $tableWrap = 'overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900';
    $thead = 'border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950';
    $linkBtn = 'inline-flex h-8 items-center justify-center rounded-md border border-border px-3 text-sm hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950';

    $tabs = [
        'overview' => ['label' => 'Overview', 'icon' => 'dashboard'],
        'sales' => ['label' => 'Sales', 'icon' => 'payments'],
        'deductions' => ['label' => 'Deductions', 'icon' => 'expense'],
        'balances' => ['label' => 'Cash & Balances', 'icon' => 'wallet'],
        'operations' => ['label' => 'Operations', 'icon' => 'activity'],
    ];
    if ($user->hasMenuAccess('baggage')) {
        $tabs['baggage'] = ['label' => 'Baggage', 'icon' => 'package'];
    }
    if ($canSeeAudit) {
        $tabs['audit'] = ['label' => 'Audit & Staff', 'icon' => 'shieldCheck'];
    }

    $toneClasses = "{
        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900': row.tone === 'ok',
        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900': row.tone === 'warn',
        'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900': row.tone === 'bad',
        'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900': row.tone === 'info',
    }";
    $initialTab = array_key_exists(request('tab'), $tabs) ? request('tab') : 'overview';

    // Each card: [label, Alpine expression for the value, icon, optional hint]
    $overviewCards = [
        ['Physical Collections', 'data.stats.collections', 'receipt', 'Cash + GCash received'],
        ['Total Deductions', 'data.deductions.total', 'expense', 'Expenses, withdrawals, repayments'],
        ['Net After Expenses', 'data.sales.net_after_expenses', 'scale', 'Sales owned minus expenses'],
        ['Job Orders', 'data.stats.orders', 'jobOrders', 'Created in this period'],
    ];
    $salesCards = [
        ['Sales Owned', 'data.sales.sales_owned', 'payments', 'Payments credited to branch'],
        ['Gross Sales', 'data.sales.gross_sales', 'shopping-bag', 'Order subtotals before discount'],
        ['Discounts Given', 'data.sales.discounts', 'tag', 'Reduces gross sales'],
        ['Total Billed', 'data.sales.billed', 'receipt', 'Order totals after discount'],
        ['Average Order', 'data.sales.average_order', 'scale', 'Billed ÷ job orders'],
        ['Payments Received', 'data.sales.payments_count', 'jobOrders', 'Number of payments'],
        ['Cash Collected', 'data.sales.cash', 'wallet', 'Physically collected here'],
        ['GCash Collected', 'data.sales.gcash', 'payments', 'Physically collected here'],
        ['PO Payments', 'data.sales.po', 'receivables', 'Purchase-order settlements'],
        ['Unpaid From Period', 'data.sales.unpaid_from_period', 'alertTriangle', 'Balance left on these orders'],
    ];
    $deductionCards = [
        ['Total Deductions', 'data.deductions.total', 'expense', 'Everything that left the store'],
        ['Recorded Expenses', 'data.deductions.expenses', 'receipt', 'All expense entries'],
        ['Withdrawals / Remittances', 'data.deductions.withdrawals', 'wallet', 'Includes cash AP repayments'],
        ['Cashless AP Repayments', 'data.deductions.cashless_repayments', 'receivables', 'GCash / bank repayments'],
        ['Discounts Given', 'data.deductions.discounts', 'tag', 'Not included in total'],
    ];
    $expenseSources = [
        ['Store Cash', 'data.deductions.store_cash'],
        ['Store GCash', 'data.deductions.store_gcash'],
        ['Store Bank', 'data.deductions.store_bank'],
        ['Owner-Paid (legacy)', 'data.deductions.owner_paid'],
    ];
    $operationsCards = [
        ['Open Orders', 'data.stats.open_orders', 'activity', 'Not completed or cancelled'],
        ['Ready for Pickup', 'data.stats.ready_for_pickup', 'laundry', 'Waiting for customer'],
        ['Ready for Delivery', 'data.stats.ready_for_delivery', 'truck', 'Waiting for rider'],
        ['Low Stock Items', 'data.stats.low_stock', 'inventory', 'At or below reorder level'],
    ];
@endphp

@section('content')
<style>
    /* Scoped dashboard styles. Kept inline so the page renders correctly even when
       the compiled Tailwind bundle (public/build) is older than this view. */
    .dash-hero { background:#fff; border:1px solid #e2e8f0; border-radius:.75rem; box-shadow:0 1px 2px rgb(15 23 42 / .05); overflow:hidden; }
    .dash-hero-top { display:flex; flex-direction:column; gap:1rem; padding:1.25rem; }
    .dash-hero-title h1 { margin:.5rem 0 0; font-size:1.375rem; line-height:1.3; font-weight:700; color:#0f172a; letter-spacing:-.01em; }
    .dash-hero-title p { margin:.125rem 0 0; font-size:.875rem; color:#64748b; }
    .dash-eyebrow { display:inline-flex; align-items:center; gap:.375rem; padding:.25rem .625rem; border-radius:999px; font-size:.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:var(--color-primary); background:color-mix(in srgb, var(--color-primary) 10%, transparent); }
    .dash-icon-xs { width:.875rem; height:.875rem; }
    .dash-icon-sm { width:1rem; height:1rem; flex-shrink:0; }
    .dash-inline { display:inline-flex; }

    .dash-toolbar { display:grid; grid-template-columns:1fr; gap:.5rem; }
    .dash-field { display:flex; align-items:center; gap:.5rem; height:2.5rem; padding:0 .75rem; border:1px solid #e2e8f0; border-radius:.5rem; background:#fff; color:#64748b; transition:border-color .15s, box-shadow .15s; }
    .dash-field:focus-within { border-color:var(--color-primary); box-shadow:0 0 0 3px color-mix(in srgb, var(--color-primary) 18%, transparent); }
    .dash-field select, .dash-field input { flex:1; min-width:0; height:100%; border:0; outline:0; background:transparent; font-size:.875rem; color:#0f172a; cursor:pointer; }
    .dash-btn { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; height:2.5rem; padding:0 1rem; border-radius:.5rem; font-size:.875rem; font-weight:600; border:1px solid transparent; transition:opacity .15s, background .15s; cursor:pointer; }
    .dash-btn-primary { background:var(--color-primary); color:#fff; }
    .dash-btn-primary:hover { opacity:.9; }
    .dash-btn-icon { width:2.5rem; padding:0; border-color:#e2e8f0; background:#fff; color:#475569; }
    .dash-btn-icon:hover { background:#f8fafc; }
    .dash-btn:disabled { opacity:.6; cursor:wait; }
    .dash-spin { animation:dash-spin 1s linear infinite; }
    @keyframes dash-spin { to { transform:rotate(360deg); } }

    .dash-hero-bottom { display:flex; flex-direction:column; gap:.75rem; padding:.75rem 1.25rem; border-top:1px solid #f1f5f9; background:#f8fafc; }
    .dash-presets { display:flex; flex-wrap:wrap; gap:.375rem; }
    .dash-preset { height:2rem; padding:0 .875rem; border-radius:999px; border:1px solid #e2e8f0; background:#fff; font-size:.8125rem; font-weight:500; color:#475569; cursor:pointer; transition:all .15s; }
    .dash-preset:hover { border-color:#cbd5e1; color:#0f172a; }
    .dash-preset.is-active { background:var(--color-primary); border-color:var(--color-primary); color:#fff; box-shadow:0 1px 2px rgb(15 23 42 / .12); }
    .dash-status { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem 1rem; font-size:.8125rem; color:#64748b; }
    .dash-period { display:inline-flex; align-items:baseline; gap:.375rem; }
    .dash-period-label { font-weight:700; color:#0f172a; }
    .dash-live { display:inline-flex; align-items:center; gap:.375rem; }
    .dash-dot { width:.5rem; height:.5rem; border-radius:999px; background:#10b981; box-shadow:0 0 0 3px rgb(16 185 129 / .18); }
    .dash-live.is-loading .dash-dot { background:#f59e0b; box-shadow:0 0 0 3px rgb(245 158 11 / .2); }
    .dash-live.is-error { color:#dc2626; }
    .dash-live.is-error .dash-dot { background:#ef4444; box-shadow:0 0 0 3px rgb(239 68 68 / .2); }

    .dash-tabs { display:flex; gap:.25rem; overflow-x:auto; padding:.25rem; border:1px solid #e2e8f0; border-radius:.75rem; background:#fff; box-shadow:0 1px 2px rgb(15 23 42 / .05); scrollbar-width:none; }
    .dash-tabs::-webkit-scrollbar { display:none; }
    .dash-tab { flex:1 0 auto; display:inline-flex; align-items:center; justify-content:center; gap:.5rem; padding:.625rem 1rem; border-radius:.5rem; font-size:.875rem; font-weight:500; color:#64748b; white-space:nowrap; cursor:pointer; transition:all .15s; }
    .dash-tab:hover { background:#f8fafc; color:#0f172a; }
    .dash-tab.is-active { background:var(--color-primary); color:#fff; box-shadow:0 1px 3px rgb(15 23 42 / .15); }

    .dash-sales-card { background:linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 9%, #fff), #fff 60%); }

    @media (min-width:40rem) {
        .dash-toolbar { grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto auto; }
        .dash-toolbar:not(:has(.dash-field-branch)) { grid-template-columns:minmax(0,1fr) auto auto; }
        .dash-hero-bottom { flex-direction:row; align-items:center; justify-content:space-between; }
    }
    @media (min-width:64rem) {
        .dash-hero-top { flex-direction:row; align-items:center; justify-content:space-between; }
        .dash-toolbar { grid-template-columns:13rem 15rem auto auto; }
        .dash-toolbar:not(:has(.dash-field-branch)) { grid-template-columns:15rem auto auto; }
    }

    .dark .dash-hero, .dark .dash-tabs { background:#111827; border-color:#1f2937; }
    .dark .dash-hero-title h1, .dark .dash-period-label { color:#f3f4f6; }
    .dark .dash-hero-bottom { background:#0b1120; border-color:#1f2937; }
    .dark .dash-field, .dark .dash-btn-icon, .dark .dash-preset { background:#030712; border-color:#1f2937; color:#9ca3af; }
    .dark .dash-field select, .dark .dash-field input { color:#f3f4f6; }
    .dark .dash-preset:hover, .dark .dash-tab:hover, .dark .dash-btn-icon:hover { background:#030712; color:#f3f4f6; }
    .dark .dash-preset.is-active, .dark .dash-tab.is-active { background:var(--color-primary); border-color:var(--color-primary); color:#fff; }
    .dark .dash-sales-card { background:linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 14%, #111827), #111827 60%); }

    /* Fallbacks for utilities this page uses that may be missing from an older build. */
    .h-48 { height:12rem; } .h-52 { height:13rem; } .h-56 { height:14rem; }
    .text-3xl { font-size:1.875rem; line-height:2.25rem; }
    .dark .dark\:bg-emerald-950\/40 { background-color:rgb(2 44 34 / .4); }
    .dark .dark\:bg-sky-950\/40 { background-color:rgb(8 47 73 / .4); }
    @media (min-width:64rem) {
        .lg\:grid-cols-\[minmax\(0\,1\.1fr\)_minmax\(0\,2fr\)\] { grid-template-columns:minmax(0,1.1fr) minmax(0,2fr); }
    }
    @media (min-width:80rem) {
        .xl\:col-span-2 { grid-column:span 2 / span 2; }
        .xl\:grid-cols-\[minmax\(0\,1\.4fr\)_minmax\(20rem\,0\.9fr\)\] { grid-template-columns:minmax(0,1.4fr) minmax(20rem,.9fr); }
        .xl\:grid-cols-\[minmax\(0\,1\.6fr\)_minmax\(18rem\,0\.9fr\)\] { grid-template-columns:minmax(0,1.6fr) minmax(18rem,.9fr); }
        .xl\:grid-cols-\[minmax\(0\,1fr\)_minmax\(0\,1\.3fr\)\] { grid-template-columns:minmax(0,1fr) minmax(0,1.3fr); }
    }
</style>
<div
    x-data="dashboardPage({
        fetchUrl: @js(route('dashboard.data', request()->except('tab'))),
        initialData: @js($dashboardData),
        initialDateRange: @js($dateRangeValue),
        hasExplicitRange: @js(request()->filled('date_range')),
        initialTab: @js($initialTab),
        tabs: @js(array_keys($tabs)),
    })"
    class="space-y-4"
>
    {{-- Header + filters (styled by the scoped dash-* rules at the bottom, so it never depends on the compiled CSS) --}}
    <header class="dash-hero">
        <div class="dash-hero-top">
            <div class="dash-hero-title">
                <span class="dash-eyebrow">
                    <span data-lucide="dashboard" class="dash-icon-xs"></span>
                    {{ $canChooseBranch ? 'Executive overview' : 'Branch command center' }}
                </span>
                <h1>{{ $canChooseBranch ? 'Business Dashboard' : $user->branch?->name.' Dashboard' }}</h1>
                <p>Sales, deductions, cash and operations in one place.</p>
            </div>

            <form x-ref="filterForm" method="GET" action="{{ route('dashboard') }}" class="dash-toolbar">
                <input type="hidden" name="tab" :value="tab">
                @if($canChooseBranch)
                    <label class="dash-field dash-field-branch">
                        <span data-lucide="branches" class="dash-icon-sm"></span>
                        <select name="branch_id" @change="$refs.filterForm.submit()" aria-label="Branch">
                            <option value="">All branches</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <input type="hidden" name="branch_id" value="{{ $user->branch_id }}">
                @endif

                <label class="dash-field dash-field-date">
                    <span data-lucide="calendar" class="dash-icon-sm"></span>
                    <input x-ref="dateRange" x-model="dateRange" name="date_range" type="text" autocomplete="off" aria-label="Date range" placeholder="Pick dates">
                </label>

                <button type="submit" class="dash-btn dash-btn-primary">
                    <span data-lucide="search" class="dash-icon-sm"></span>
                    Apply
                </button>

                <button type="button" @click="refresh(true)" :disabled="status === 'loading'" class="dash-btn dash-btn-icon" title="Refresh now" aria-label="Refresh now">
                    <span :class="status === 'loading' ? 'dash-spin' : ''" class="dash-inline"><span data-lucide="refresh-cw" class="dash-icon-sm"></span></span>
                </button>
            </form>
        </div>

        <div class="dash-hero-bottom">
            <div class="dash-presets" role="group" aria-label="Quick date ranges">
                <template x-for="preset in presets" :key="preset.key">
                    <button type="button" @click="applyPreset(preset)" class="dash-preset" :class="activePreset === preset.key ? 'is-active' : ''" x-text="preset.label"></button>
                </template>
            </div>

            <div class="dash-status">
                <span class="dash-period">
                    <span class="dash-period-label" x-text="data.period.label"></span>
                    <span class="dash-period-dates" x-show="data.period.dates !== data.period.label" x-text="data.period.dates"></span>
                </span>
                <span class="dash-live" :class="'is-' + status">
                    <span class="dash-dot"></span>
                    <span x-show="status !== 'error'" x-text="status === 'loading' ? 'Refreshing…' : `Updated ${data.generated_at}`"></span>
                    <span x-show="status === 'error'" x-cloak>Couldn't refresh. Retrying…</span>
                </span>
            </div>
        </div>
    </header>

    {{-- Tabs --}}
    <nav class="dash-tabs" role="tablist" aria-label="Dashboard sections">
        @foreach($tabs as $key => $tabItem)
            <button type="button" role="tab" :aria-selected="tab === '{{ $key }}'" @click="setTab('{{ $key }}')" class="dash-tab" :class="tab === '{{ $key }}' ? 'is-active' : ''">
                <span data-lucide="{{ $tabItem['icon'] }}" class="dash-icon-sm"></span>
                <span>{{ $tabItem['label'] }}</span>
            </button>
        @endforeach
    </nav>

    {{-- ============ OVERVIEW ============ --}}
    <section x-show="tab === 'overview'" class="space-y-4" role="tabpanel">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,2fr)]">
            <div class="{{ $panel }} dash-sales-card flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-muted" x-text="`Sales ${data.period.label}`"></p>
                    <span data-lucide="payments" class="h-5 w-5 text-primary"></span>
                </div>
                <p class="mt-2 text-3xl font-bold tracking-tight" x-text="data.sales.sales_owned"></p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 font-semibold"
                        :class="{
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300': data.sales.change_direction === 'up',
                            'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300': data.sales.change_direction === 'down',
                            'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300': data.sales.change_direction === 'flat',
                        }"
                        x-text="changeLabel()"
                    ></span>
                    <span class="text-muted" x-text="`vs ${data.sales.previous_sales} on ${data.period.previous_label}`"></span>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($overviewCards as [$label, $expr, $icon, $hint])
                    <div class="{{ $panel }}">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-medium text-muted">{{ $label }}</p>
                            <span data-lucide="{{ $icon }}" class="h-4 w-4 text-primary"></span>
                        </div>
                        <p class="mt-1 text-lg font-semibold" x-text="{{ $expr }}"
                           @if($expr === 'data.sales.net_after_expenses') :class="data.sales.net_is_negative ? 'text-red-600 dark:text-red-400' : ''" @endif></p>
                        <p class="text-xs text-muted">{{ $hint }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Health ratios --}}
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="{{ $panel }}">
                <p class="text-xs font-medium text-muted">Collection Rate</p>
                <p class="mt-1 text-lg font-semibold" x-text="pct(data.analytics.ratios.collection_rate)"></p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-smoke dark:bg-gray-800"><div class="h-full rounded-full bg-primary transition-all" :style="`width: ${bar(data.analytics.ratios.collection_rate)}%`"></div></div>
                <p class="mt-1.5 text-xs text-muted" x-text="data.analytics.ratios.collection_hint"></p>
            </div>
            <div class="{{ $panel }}">
                <p class="text-xs font-medium text-muted">Expense Ratio</p>
                <p class="mt-1 text-lg font-semibold" :class="(data.analytics.ratios.expense_ratio ?? 0) > 60 ? 'text-red-600 dark:text-red-400' : ''" x-text="pct(data.analytics.ratios.expense_ratio)"></p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-smoke dark:bg-gray-800"><div class="h-full rounded-full bg-amber-500 transition-all" :style="`width: ${bar(data.analytics.ratios.expense_ratio)}%`"></div></div>
                <p class="mt-1.5 text-xs text-muted" x-text="data.analytics.ratios.expense_hint"></p>
            </div>
            <div class="{{ $panel }}">
                <p class="text-xs font-medium text-muted">Net Margin</p>
                <p class="mt-1 text-lg font-semibold" :class="(data.analytics.ratios.net_margin ?? 0) < 0 ? 'text-red-600 dark:text-red-400' : ''" x-text="pct(data.analytics.ratios.net_margin)"></p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-smoke dark:bg-gray-800"><div class="h-full rounded-full bg-emerald-500 transition-all" :style="`width: ${bar(data.analytics.ratios.net_margin)}%`"></div></div>
                <p class="mt-1.5 text-xs text-muted">Share of sales left after expenses</p>
            </div>
            <div class="{{ $panel }}">
                <p class="text-xs font-medium text-muted">Customers Served</p>
                <p class="mt-1 text-lg font-semibold" x-text="data.analytics.customers.served"></p>
                <p class="mt-2 text-xs text-muted" x-text="`${data.analytics.customers.new} new · ${data.analytics.customers.returning} returning (${pct(data.analytics.customers.returning_rate)})`"></p>
                <p class="text-xs text-muted" x-text="`Peak hour: ${data.analytics.peak.hour}`"></p>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(18rem,0.9fr)]">
            <div class="{{ $panel }}">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Sales Trend</h2>
                        <p class="text-sm text-muted" x-text="data.period.is_single_day ? 'Sales owned per hour.' : 'Sales owned per day.'"></p>
                    </div>
                    <button type="button" @click="setTab('sales')" class="text-sm font-medium text-primary hover:underline">Sales details →</button>
                </div>
                <div class="h-72"><canvas x-ref="overviewSalesChart"></canvas></div>
            </div>

            <div class="{{ $panel }}">
                <h2 class="mb-3 text-base font-semibold">At a Glance</h2>
                <dl class="divide-y divide-border text-sm dark:divide-gray-800">
                    @foreach([
                        ['Expected Cash Drawer', 'data.stats.cash_drawer', 'balances'],
                        ['Expected GCash', 'data.stats.gcash', 'balances'],
                        ['Recorded Expenses', 'data.stats.expenses', 'deductions'],
                        ['Unpaid Customer Balance', 'data.stats.receivables', 'balances'],
                        ['Open Orders', 'data.stats.open_orders', 'operations'],
                        ['Ready for Pickup / Delivery', "`\${data.stats.ready_for_pickup} / \${data.stats.ready_for_delivery}`", 'operations'],
                        ['Low Stock Items', 'data.stats.low_stock', 'operations'],
                    ] as [$label, $expr, $target])
                        <button type="button" @click="setTab('{{ $target }}')" class="flex w-full items-center justify-between gap-3 py-2.5 text-left hover:text-primary">
                            <dt class="text-muted">{{ $label }}</dt>
                            <dd class="font-semibold" x-text="{{ $expr }}"></dd>
                        </button>
                    @endforeach
                </dl>
            </div>
        </div>

        <div class="{{ $panel }}">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Last 7 Days: Sales vs Expenses</h2>
                    <p class="text-sm text-muted">Daily sales and expenses (bars) with job orders (line), ending on the selected date.</p>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                    <span x-text="`Sales: ${data.analytics.trend_7d.total_sales}`"></span>
                    <span x-text="`Expenses: ${data.analytics.trend_7d.total_expenses}`"></span>
                    <span x-text="`Avg/day: ${data.analytics.trend_7d.average_sales} · ${data.analytics.trend_7d.average_orders} orders`"></span>
                </div>
            </div>
            <div class="h-72"><canvas x-ref="trend7Chart"></canvas></div>
        </div>

        <div class="{{ $tableWrap }}">
            <div class="flex items-center justify-between border-b border-border px-4 py-3 dark:border-gray-800">
                <div>
                    <h2 class="text-base font-semibold">Recent Job Orders</h2>
                    <p class="text-sm text-muted">Latest transactions.</p>
                </div>
                <a href="{{ route('admin.job-orders.index') }}" class="{{ $linkBtn }}">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="px-4 py-3">JO #</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
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
                        <tr x-show="data.recent_orders.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted">No recent job orders.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============ SALES ============ --}}
    <section x-show="tab === 'sales'" x-cloak class="space-y-4" role="tabpanel">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
            @foreach($salesCards as [$label, $expr, $icon, $hint])
                <div class="{{ $panel }}">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-muted">{{ $label }}</p>
                        <span data-lucide="{{ $icon }}" class="h-4 w-4 text-primary"></span>
                    </div>
                    <p class="mt-1 text-lg font-semibold" x-text="{{ $expr }}"></p>
                    <p class="text-xs text-muted">{{ $hint }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(18rem,0.9fr)]">
            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Sales Trend</h2>
                    <p class="text-sm text-muted" x-text="data.period.is_single_day ? 'Sales owned per hour.' : 'Sales owned per day.'"></p>
                </div>
                <div class="h-72"><canvas x-ref="salesChart"></canvas></div>
            </div>
            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Payment Mix</h2>
                    <p class="text-sm text-muted">Physical collections by method.</p>
                </div>
                <div class="h-72"><canvas x-ref="paymentMixChart"></canvas></div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Order Payment Status</h2>
                    <p class="text-sm text-muted">Job orders created in this period.</p>
                </div>
                <div class="h-48"><canvas x-ref="paymentStatusChart"></canvas></div>
                <dl class="mt-3 divide-y divide-border text-sm dark:divide-gray-800">
                    <div class="flex justify-between gap-3 py-2"><dt class="text-muted">Fully paid / partial / unpaid</dt><dd class="font-semibold" x-text="`${data.analytics.orders_breakdown.paid} / ${data.analytics.orders_breakdown.partial} / ${data.analytics.orders_breakdown.unpaid}`"></dd></div>
                    <div class="flex justify-between gap-3 py-2"><dt class="text-muted">Rush orders</dt><dd class="font-semibold" x-text="`${data.analytics.orders_breakdown.rush_count} · ${data.analytics.orders_breakdown.rush_amount}`"></dd></div>
                    <div class="flex justify-between gap-3 py-2"><dt class="text-muted">Orders with discount</dt><dd class="font-semibold" x-text="data.analytics.orders_breakdown.discounted_count"></dd></div>
                    <div class="flex justify-between gap-3 py-2"><dt class="text-muted">Cancelled orders</dt><dd class="font-semibold text-red-600 dark:text-red-400" x-text="`${data.analytics.orders_breakdown.cancelled_count} · ${data.analytics.orders_breakdown.cancelled_amount}`"></dd></div>
                </dl>
            </div>

            <div class="{{ $panel }} xl:col-span-2">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Sales by Service Category</h2>
                    <p class="text-sm text-muted">Where the revenue comes from, with each category's share.</p>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="h-64"><canvas x-ref="categorySalesChart"></canvas></div>
                    <div class="divide-y divide-border text-sm dark:divide-gray-800">
                        <template x-for="row in data.analytics.category_sales.rows" :key="row.label">
                            <div class="py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="min-w-0 truncate font-medium" x-text="row.label"></span>
                                    <span class="shrink-0 text-xs text-muted" x-text="`${row.amount} · ${pct(row.share)}`"></span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-smoke dark:bg-gray-800"><div class="h-full rounded-full bg-primary" :style="`width: ${bar(row.share)}%`"></div></div>
                            </div>
                        </template>
                        <p x-show="data.analytics.category_sales.rows.length === 0" class="py-6 text-center text-muted">No service sales in this period.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="{{ $panel }}">
                <h2 class="mb-1 text-base font-semibold">Customers & Peak Times</h2>
                <p class="mb-3 text-sm text-muted">Who bought and when sales were highest.</p>
                <dl class="divide-y divide-border text-sm dark:divide-gray-800">
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Customers served</dt><dd class="font-semibold" x-text="data.analytics.customers.served"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">New customers registered</dt><dd class="font-semibold" x-text="data.analytics.customers.new"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Returning customers</dt><dd class="font-semibold" x-text="`${data.analytics.customers.returning} (${pct(data.analytics.customers.returning_rate)})`"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Peak sales hour</dt><dd class="font-semibold" x-text="`${data.analytics.peak.hour} · ${data.analytics.peak.hour_amount}`"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5" x-show="!data.period.is_single_day"><dt class="text-muted">Busiest weekday</dt><dd class="font-semibold" x-text="`${data.analytics.peak.day} · ${data.analytics.peak.day_amount}`"></dd></div>
                </dl>
            </div>

            <div class="{{ $panel }}">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">Add-On Sales</h2>
                        <p class="text-sm text-muted">Detergent and fabcon products sold with orders.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-semibold" x-text="data.analytics.add_ons.total"></p>
                        <p class="text-xs text-muted" x-text="`${data.analytics.add_ons.units} unit(s)`"></p>
                    </div>
                </div>
                <div class="divide-y divide-border text-sm dark:divide-gray-800">
                    <template x-for="row in data.analytics.add_ons.rows" :key="row.label">
                        <div class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate font-medium" x-text="row.label"></span>
                            <span class="shrink-0 text-xs text-muted" x-text="`${row.quantity} sold · ${row.amount}`"></span>
                        </div>
                    </template>
                    <p x-show="data.analytics.add_ons.rows.length === 0" class="py-6 text-center text-muted">No add-ons sold in this period.</p>
                </div>
            </div>

            <div class="{{ $panel }}" x-show="data.analytics.baggage">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">Baggage Sales</h2>
                        <p class="text-sm text-muted">Bags stored at the Baggage Counter. Already included in all sales totals.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-semibold" x-text="data.analytics.baggage?.total"></p>
                        <p class="text-xs text-muted" x-text="`${data.analytics.baggage?.bags ?? 0} bag(s)`"></p>
                    </div>
                </div>
                <div class="divide-y divide-border text-sm dark:divide-gray-800">
                    <template x-for="row in (data.analytics.baggage?.rows ?? [])" :key="row.label">
                        <div class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate font-medium" x-text="row.label"></span>
                            <span class="shrink-0 text-xs text-muted" x-text="`${row.bags} bag(s) · ${row.amount}`"></span>
                        </div>
                    </template>
                    <p x-show="(data.analytics.baggage?.rows ?? []).length === 0" class="py-6 text-center text-muted">No bags stored in this period.</p>
                    <div class="flex items-center justify-between gap-3 py-2 text-xs">
                        <span class="text-muted">At the counter now</span>
                        <span>
                            <span class="font-semibold" x-text="data.analytics.baggage?.stored_now ?? 0"></span> stored
                            · <span class="font-semibold" :class="Number(data.analytics.baggage?.overdue_now ?? 0) > 0 ? 'text-red-600' : ''" x-text="data.analytics.baggage?.overdue_now ?? 0"></span> overdue
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">POS Type Mix</h2>
                    <p class="text-sm text-muted">Walk-in/drop-off versus delivery orders.</p>
                </div>
                <div class="h-64"><canvas x-ref="transactionTypeChart"></canvas></div>
            </div>

            <div class="{{ $panel }} xl:col-span-2">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Branch Sales</h2>
                    <p class="text-sm text-muted">Sales owned by branch in the selected range.</p>
                </div>
                <div class="h-64"><canvas x-ref="branchSalesChart"></canvas></div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Top Selling Services</h2>
                    <p class="text-sm text-muted">Ranked by service sales amount.</p>
                </div>
                <div class="h-64"><canvas x-ref="topServicesChart"></canvas></div>
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

            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Top Selling Presets</h2>
                    <p class="text-sm text-muted">Preset sales from POS preset cart selections.</p>
                </div>
                <div class="h-64"><canvas x-ref="topPresetsChart"></canvas></div>
                <div class="mt-3 divide-y divide-border text-sm dark:divide-gray-800">
                    <template x-for="preset in data.top_presets" :key="preset.label">
                        <div class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate font-medium" x-text="preset.label"></span>
                            <span class="shrink-0 text-xs text-muted" x-text="`${preset.orders_count} order(s) · ${preset.amount}`"></span>
                        </div>
                    </template>
                    <p x-show="data.top_presets.length === 0" class="py-6 text-center text-sm text-muted">No preset sales in this date range.</p>
                </div>
            </div>
        </div>

        <div class="{{ $tableWrap }}">
            <div class="flex items-center justify-between border-b border-border px-4 py-3 dark:border-gray-800">
                <div>
                    <h2 class="text-base font-semibold">Recent Payments</h2>
                    <p class="text-sm text-muted">Latest payments in the selected period.</p>
                </div>
                @if($user->hasMenuAccess('payments'))
                    <a href="{{ route('admin.payments.index') }}" class="{{ $linkBtn }}">View payments</a>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="px-4 py-3">Payment #</th>
                            <th class="px-4 py-3">JO #</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Time</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        <template x-for="payment in data.recent_payments" :key="payment.id">
                            <tr>
                                <td class="px-4 py-3 font-medium" x-text="payment.number"></td>
                                <td class="px-4 py-3" x-text="payment.order"></td>
                                <td class="px-4 py-3" x-text="payment.customer"></td>
                                <td class="px-4 py-3" x-text="payment.method"></td>
                                <td class="px-4 py-3 text-muted" x-text="payment.time"></td>
                                <td class="px-4 py-3 text-right font-medium" x-text="payment.amount"></td>
                            </tr>
                        </template>
                        <tr x-show="data.recent_payments.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted">No payments in this period yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============ DEDUCTIONS ============ --}}
    <section x-show="tab === 'deductions'" x-cloak class="space-y-4" role="tabpanel">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
            @foreach($deductionCards as [$label, $expr, $icon, $hint])
                <div class="{{ $panel }}">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-muted">{{ $label }}</p>
                        <span data-lucide="{{ $icon }}" class="h-4 w-4 text-primary"></span>
                    </div>
                    <p class="mt-1 text-lg font-semibold" x-text="{{ $expr }}"></p>
                    <p class="text-xs text-muted">{{ $hint }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Deductions Breakdown</h2>
                    <p class="text-sm text-muted">Where the money went this period.</p>
                </div>
                <div class="h-64"><canvas x-ref="deductionsChart"></canvas></div>
            </div>

            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Expenses by Category</h2>
                    <p class="text-sm text-muted">Recorded expenses grouped by category.</p>
                </div>
                <div class="h-64"><canvas x-ref="expenseCategoriesChart"></canvas></div>
            </div>

            <div class="{{ $panel }}">
                <h2 class="mb-1 text-base font-semibold">Expense Funding Source</h2>
                <p class="mb-3 text-sm text-muted">Which account paid the expenses.</p>
                <dl class="divide-y divide-border text-sm dark:divide-gray-800">
                    @foreach($expenseSources as [$label, $expr])
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <dt class="text-muted">{{ $label }}</dt>
                            <dd class="font-semibold" x-text="{{ $expr }}"></dd>
                        </div>
                    @endforeach
                </dl>
                <div class="mt-3 divide-y divide-border border-t border-border pt-2 text-sm dark:divide-gray-800 dark:border-gray-800">
                    <template x-for="category in data.deductions.categories" :key="category.label">
                        <div class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate" x-text="category.label"></span>
                            <span class="shrink-0 text-xs text-muted" x-text="`${category.entries} entr${category.entries === '1' ? 'y' : 'ies'} · ${category.amount}`"></span>
                        </div>
                    </template>
                    <p x-show="data.deductions.categories.length === 0" class="py-4 text-center text-muted">No expenses recorded.</p>
                </div>
            </div>
        </div>

        <div class="{{ $tableWrap }}">
            <div class="flex items-center justify-between border-b border-border px-4 py-3 dark:border-gray-800">
                <div>
                    <h2 class="text-base font-semibold">Recent Expenses</h2>
                    <p class="text-sm text-muted">Latest expense entries in the selected period.</p>
                </div>
                <div class="flex gap-2">
                    @if($user->hasMenuAccess('petty_cash'))
                        <a href="{{ route('admin.petty-cash.index') }}" class="{{ $linkBtn }}">Petty cash</a>
                    @endif
                    @if($user->hasMenuAccess('expenses'))
                        <a href="{{ route('admin.expenses.index') }}" class="{{ $linkBtn }}">View expenses</a>
                    @endif
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Expense</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Paid Via</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        <template x-for="expense in data.recent_expenses" :key="expense.id">
                            <tr>
                                <td class="px-4 py-3 text-muted" x-text="expense.date"></td>
                                <td class="px-4 py-3 font-medium" x-text="expense.title"></td>
                                <td class="px-4 py-3" x-text="expense.category"></td>
                                <td class="px-4 py-3" x-text="expense.branch"></td>
                                <td class="px-4 py-3" x-text="expense.method"></td>
                                <td class="px-4 py-3 text-right font-medium" x-text="expense.amount"></td>
                            </tr>
                        </template>
                        <tr x-show="data.recent_expenses.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted">No expenses recorded in this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="{{ $tableWrap }}">
            <div class="flex flex-col gap-2 border-b border-border px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Petty Cash Movements</h2>
                    <p class="text-sm text-muted">Deposits and withdrawals recorded in this period, and who recorded them.</p>
                </div>
                <p class="text-xs text-muted">Expenses take <strong x-text="pct(data.analytics.ratios.expense_ratio)"></strong> of sales</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Recorded By</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        <template x-for="movement in data.analytics.money_movements" :key="movement.id">
                            <tr>
                                <td class="px-4 py-3 text-muted" x-text="movement.date"></td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                          :class="movement.direction === 'in' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'"
                                          x-text="movement.label"></span>
                                </td>
                                <td class="px-4 py-3" x-text="movement.description"></td>
                                <td class="px-4 py-3" x-text="movement.by"></td>
                                <td class="px-4 py-3 text-right font-medium" x-text="(movement.direction === 'in' ? '+ ' : '− ') + movement.amount"></td>
                            </tr>
                        </template>
                        <tr x-show="data.analytics.money_movements.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted">No deposits or withdrawals in this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============ CASH & BALANCES ============ --}}
    <section x-show="tab === 'balances'" x-cloak class="space-y-4" role="tabpanel">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Expected Cash Drawer', 'data.balances.expected_cash_drawer', 'wallet'],
                ['Expected GCash', 'data.balances.expected_gcash', 'payments'],
                ['Expected Bank', 'data.balances.expected_bank', 'store'],
                ['Expected Total', 'data.balances.expected_total', 'scale'],
            ] as [$label, $expr, $icon])
                <div class="{{ $panel }}">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-muted">{{ $label }}</p>
                        <span data-lucide="{{ $icon }}" class="h-4 w-4 text-primary"></span>
                    </div>
                    <p class="mt-1 text-lg font-semibold" x-text="{{ $expr }}"></p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="{{ $panel }}">
                <h2 class="mb-1 text-base font-semibold">Cash Drawer Computation</h2>
                <p class="mb-3 text-sm text-muted">How the expected drawer amount is calculated.</p>
                <dl class="divide-y divide-border text-sm dark:divide-gray-800">
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Cash collected here</dt><dd class="font-medium text-emerald-700 dark:text-emerald-400" x-text="`+ ${data.balances.cash_collections}`"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Deposits / owner funding</dt><dd class="font-medium text-emerald-700 dark:text-emerald-400" x-text="`+ ${data.balances.cash_in}`"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Store-cash expenses</dt><dd class="font-medium text-red-600 dark:text-red-400" x-text="`− ${data.balances.store_cash_expenses}`"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Withdrawals / repayments</dt><dd class="font-medium text-red-600 dark:text-red-400" x-text="`− ${data.balances.cash_out}`"></dd></div>
                    <div class="flex justify-between gap-3 py-3 text-base"><dt class="font-semibold">Expected drawer</dt><dd class="font-bold" x-text="data.balances.expected_cash_drawer"></dd></div>
                </dl>
            </div>

            <div class="{{ $panel }}">
                <h2 class="mb-1 text-base font-semibold">Balances to Watch</h2>
                <p class="mb-3 text-sm text-muted">Money owed to and by the business.</p>
                <dl class="divide-y divide-border text-sm dark:divide-gray-800">
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <dt class="text-muted">Unpaid customer balance</dt>
                        <dd class="flex items-center gap-3">
                            <span class="font-semibold" x-text="data.stats.receivables"></span>
                            @if($user->hasMenuAccess('receivables'))<a href="{{ route('admin.receivables.index') }}" class="text-xs text-primary hover:underline">Open</a>@endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <dt class="text-muted">Accounts payable</dt>
                        <dd class="flex items-center gap-3">
                            <span class="font-semibold" x-text="data.stats.accounts_payable"></span>
                            @if($user->hasMenuAccess('accounts_payable'))<a href="{{ route('admin.accounts-payable.index') }}" class="text-xs text-primary hover:underline">Open</a>@endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <dt class="text-muted">Z Reading over / short</dt>
                        <dd class="flex items-center gap-3">
                            <span class="font-semibold" :class="data.balances.over_short_is_negative ? 'text-red-600 dark:text-red-400' : ''" x-text="data.balances.over_short"></span>
                            @if($user->hasMenuAccess('z_readings'))<a href="{{ route('admin.z-readings.index') }}" class="text-xs text-primary hover:underline">Open</a>@endif
                        </dd>
                    </div>
                </dl>
                <div class="mt-4 h-48"><canvas x-ref="financialChart"></canvas></div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Receivables Aging</h2>
                    <p class="text-sm text-muted">How long unpaid customer balances have been open (all dates).</p>
                </div>
                <div class="h-52"><canvas x-ref="agingChart"></canvas></div>
                <dl class="mt-3 divide-y divide-border text-sm dark:divide-gray-800">
                    <template x-for="(row, index) in data.analytics.receivables_aging.rows" :key="row.label">
                        <div class="flex justify-between gap-3 py-2">
                            <dt class="text-muted" :class="index >= 2 ? 'text-red-600 dark:text-red-400' : ''" x-text="row.label"></dt>
                            <dd class="font-semibold" x-text="`${row.count} order(s) · ${row.amount}`"></dd>
                        </div>
                    </template>
                </dl>
            </div>

            <div class="{{ $tableWrap }}">
                <div class="flex items-center justify-between border-b border-border px-4 py-3 dark:border-gray-800">
                    <div>
                        <h2 class="text-base font-semibold">Customers With Biggest Balances</h2>
                        <p class="text-sm text-muted">Who to follow up for collection first.</p>
                    </div>
                    @if($user->hasMenuAccess('receivables'))
                        <a href="{{ route('admin.receivables.index') }}" class="{{ $linkBtn }}">Receivables</a>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="{{ $thead }}">
                            <tr>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3 text-center">Orders</th>
                                <th class="px-4 py-3">Oldest Unpaid</th>
                                <th class="px-4 py-3 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border dark:divide-gray-800">
                            <template x-for="debtor in data.analytics.top_debtors" :key="debtor.id">
                                <tr>
                                    <td class="px-4 py-3 font-medium" x-text="debtor.name"></td>
                                    <td class="px-4 py-3 text-muted" x-text="debtor.phone"></td>
                                    <td class="px-4 py-3 text-center" x-text="debtor.orders"></td>
                                    <td class="px-4 py-3" x-text="debtor.oldest"></td>
                                    <td class="px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400" x-text="debtor.balance"></td>
                                </tr>
                            </template>
                            <tr x-show="data.analytics.top_debtors.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-muted">No unpaid customer balances.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ OPERATIONS ============ --}}
    <section x-show="tab === 'operations'" x-cloak class="space-y-4" role="tabpanel">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($operationsCards as [$label, $expr, $icon, $hint])
                <div class="{{ $panel }}">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-muted">{{ $label }}</p>
                        <span data-lucide="{{ $icon }}" class="h-4 w-4 text-primary"></span>
                    </div>
                    <p class="mt-1 text-lg font-semibold" x-text="{{ $expr }}"></p>
                    <p class="text-xs text-muted">{{ $hint }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.9fr)]">
            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Workflow Status</h2>
                    <p class="text-sm text-muted">Job orders created in this period, by status.</p>
                </div>
                <div class="h-72"><canvas x-ref="statusChart"></canvas></div>
            </div>

            <div class="{{ $panel }}">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Low Stock</h2>
                        <p class="text-sm text-muted">Items at or below reorder level.</p>
                    </div>
                    @if($user->hasMenuAccess('inventory'))
                        <a href="{{ route('admin.inventory.index') }}" class="{{ $linkBtn }}">Inventory</a>
                    @endif
                </div>
                <div class="divide-y divide-border text-sm dark:divide-gray-800">
                    <template x-for="item in data.low_stock_items" :key="item.id">
                        <div class="flex items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate font-medium" x-text="item.name"></p>
                                <p class="text-xs text-muted" x-text="`${item.branch} · reorder at ${item.reorder_level}`"></p>
                            </div>
                            <span class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-300" x-text="item.quantity"></span>
                        </div>
                    </template>
                    <p x-show="data.low_stock_items.length === 0" class="py-8 text-center text-muted">All stock levels are healthy.</p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="{{ $panel }}">
                <h2 class="mb-1 text-base font-semibold">Turnaround Time</h2>
                <p class="mb-3 text-sm text-muted">From order drop-off to completion, for orders completed in this period.</p>
                <p class="text-3xl font-bold tracking-tight" x-text="data.analytics.turnaround.average"></p>
                <p class="text-xs text-muted" x-text="`average across ${data.analytics.turnaround.completed} completed order(s)`"></p>
                <dl class="mt-3 divide-y divide-border text-sm dark:divide-gray-800">
                    <div class="flex justify-between gap-3 py-2"><dt class="text-muted">Fastest</dt><dd class="font-semibold" x-text="data.analytics.turnaround.fastest"></dd></div>
                    <div class="flex justify-between gap-3 py-2"><dt class="text-muted">Slowest</dt><dd class="font-semibold" x-text="data.analytics.turnaround.slowest"></dd></div>
                    <div class="flex justify-between gap-3 py-2"><dt class="text-muted">Average to finish processing</dt><dd class="font-semibold" x-text="data.analytics.turnaround.production_average"></dd></div>
                </dl>
            </div>

            <div class="{{ $panel }}">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">Machine Cycles</h2>
                        <p class="text-sm text-muted">Cycles started in this period, by type.</p>
                    </div>
                    <span class="text-lg font-semibold" x-text="data.analytics.cycles.total"></span>
                </div>
                <div class="h-56"><canvas x-ref="cyclesChart"></canvas></div>
            </div>

            <div class="{{ $panel }}">
                <h2 class="mb-1 text-base font-semibold">Inventory Used</h2>
                <p class="mb-3 text-sm text-muted">Most-used supplies in this period, with estimated cost.</p>
                <div class="divide-y divide-border text-sm dark:divide-gray-800">
                    <template x-for="item in data.analytics.inventory_usage" :key="item.name">
                        <div class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate font-medium" x-text="item.name"></span>
                            <span class="shrink-0 text-xs text-muted" x-text="`${item.used} · ${item.cost}`"></span>
                        </div>
                    </template>
                    <p x-show="data.analytics.inventory_usage.length === 0" class="py-6 text-center text-muted">No stock deductions in this period.</p>
                </div>
            </div>
        </div>

        <div class="{{ $tableWrap }}">
            <div class="flex flex-col gap-2 border-b border-border px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Customers Who Trust {{ $appBusinessName }}</h2>
                    <p class="text-sm text-muted">Customers with 10 or more laundry orders, ranked by total orders.</p>
                </div>
                @if($user->hasMenuAccess('customers'))
                    <a href="{{ route('admin.customers.index') }}" class="{{ $linkBtn }}">View customers</a>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3 text-center">Laundry Orders</th>
                            <th class="px-4 py-3">Latest Service</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
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
                        <tr x-show="data.trusted_customers.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted">Customer laundry history will appear here after the first job order.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============ BAGGAGE ============ --}}
    @if(isset($tabs['baggage']))
    @php
        $baggageCards = [
            ['Baggage Sales', 'data.analytics.baggage.total', 'payments', 'Paid storage in this period'],
            ['Bags Stored', 'data.analytics.baggage.bags', 'package', 'Deposits in this period'],
            ['Average per Bag', 'data.analytics.baggage.average', 'scale', 'Sales ÷ bags'],
            ['At the Counter Now', 'data.analytics.baggage.stored_now', 'tag', 'Tags in use right now'],
            ['Overdue Now', 'data.analytics.baggage.overdue_now', 'alertTriangle', 'Past their due time'],
            ['Released Late', 'data.analytics.baggage.late_releases', 'timer', 'Picked up after the due time'],
            ['Average Storage Time', 'data.analytics.baggage.average_storage', 'timer', 'Deposit to release'],
            ['Voided', 'data.analytics.baggage.voided', 'x', 'Cancelled by mistake'],
        ];
    @endphp
    <section x-show="tab === 'baggage'" x-cloak class="space-y-4" role="tabpanel">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($baggageCards as [$label, $expr, $icon, $hint])
                <div class="{{ $panel }}">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-muted">{{ $label }}</p>
                        <span data-lucide="{{ $icon }}" class="h-4 w-4 text-primary"></span>
                    </div>
                    <p class="mt-1 text-lg font-semibold" x-text="{{ $expr }}"></p>
                    <p class="text-xs text-muted">{{ $hint }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="{{ $panel }}">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">Sales by Bag Size</h2>
                        <p class="text-sm text-muted">Small, medium, large/XL and overnight.</p>
                    </div>
                    @if($user->hasMenuAccess('baggage'))
                        <a href="{{ route('admin.baggage.index') }}" class="{{ $linkBtn }}">Baggage Counter</a>
                    @endif
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="h-56"><canvas x-ref="baggageSizesChart"></canvas></div>
                    <div class="divide-y divide-border text-sm dark:divide-gray-800">
                        <template x-for="row in data.analytics.baggage.rows" :key="row.label">
                            <div class="py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="min-w-0 truncate font-medium" x-text="row.label"></span>
                                    <span class="shrink-0 text-xs text-muted" x-text="`${row.bags} bag(s) · ${row.amount}`"></span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-smoke dark:bg-gray-800"><div class="h-full rounded-full bg-primary" :style="`width: ${bar(row.share)}%`"></div></div>
                            </div>
                        </template>
                        <p x-show="data.analytics.baggage.rows.length === 0" class="py-6 text-center text-muted">No bags stored in this period.</p>
                    </div>
                </div>
            </div>

            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Baggage Sales per Day</h2>
                    <p class="text-sm text-muted">Storage fees collected each day of the period.</p>
                </div>
                <div class="h-56"><canvas x-ref="baggageDailyChart"></canvas></div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="{{ $panel }}">
                <div class="mb-4">
                    <h2 class="text-base font-semibold">Busiest Drop-off Hours</h2>
                    <p class="text-sm text-muted">Bags deposited per hour of the day.</p>
                </div>
                <div class="h-56"><canvas x-ref="baggageHourlyChart"></canvas></div>
            </div>

            <div class="{{ $panel }}">
                <h2 class="mb-1 text-base font-semibold">Payments & Pick-up</h2>
                <p class="mb-3 text-sm text-muted">How bags were paid and how fast customers came back.</p>
                <dl class="divide-y divide-border text-sm dark:divide-gray-800">
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Cash</dt><dd class="font-semibold" x-text="data.analytics.baggage.cash"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">GCash</dt><dd class="font-semibold" x-text="data.analytics.baggage.gcash"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Bags released</dt><dd class="font-semibold" x-text="data.analytics.baggage.released"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Picked up on time</dt><dd class="font-semibold" x-text="pct(data.analytics.baggage.on_time_rate)"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Average storage time</dt><dd class="font-semibold" x-text="data.analytics.baggage.average_storage"></dd></div>
                    <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Longest storage</dt><dd class="font-semibold" x-text="data.analytics.baggage.longest_storage"></dd></div>
                </dl>
            </div>
        </div>

        <div class="{{ $tableWrap }}">
            <div class="border-b border-border px-4 py-3 dark:border-gray-800">
                <h2 class="text-base font-semibold">At the Counter Now</h2>
                <p class="text-sm text-muted">Bags not yet released, soonest due first. Includes bags from earlier days.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="px-4 py-3">Tag</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Size</th>
                            <th class="px-4 py-3">Deposited</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3">Time Left</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        <template x-for="row in data.analytics.baggage.at_counter" :key="'counter-' + row.id">
                            <tr>
                                <td class="px-4 py-3 font-semibold" x-text="'#' + row.tag"></td>
                                <td class="px-4 py-3" x-text="row.branch"></td>
                                <td class="px-4 py-3" x-text="row.size"></td>
                                <td class="px-4 py-3 whitespace-nowrap" x-text="row.deposited_at"></td>
                                <td class="px-4 py-3 whitespace-nowrap" x-text="row.due_at"></td>
                                <td class="px-4 py-3 whitespace-nowrap font-semibold" style="font-variant-numeric:tabular-nums" :class="bagOverdue(row) ? 'text-red-600' : 'text-amber-700'" x-text="bagCountdown(row)"></td>
                            </tr>
                        </template>
                        <tr x-show="data.analytics.baggage.at_counter.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted">No bags at the counter right now.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="{{ $tableWrap }}">
            <div class="border-b border-border px-4 py-3 dark:border-gray-800">
                <h2 class="text-base font-semibold">All Baggage in This Period</h2>
                <p class="text-sm text-muted">Every bag deposited in the selected dates, newest first<span x-show="data.analytics.baggage.list_truncated"> (latest 300 shown)</span>.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="px-4 py-3">Tag</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Size</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Deposited</th>
                            <th class="px-4 py-3">Released</th>
                            <th class="px-4 py-3">Stored For</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Sale</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        <template x-for="row in data.analytics.baggage.list" :key="'bag-' + row.id">
                            <tr>
                                <td class="px-4 py-3 font-semibold" x-text="'#' + row.tag"></td>
                                <td class="px-4 py-3" x-text="row.branch"></td>
                                <td class="px-4 py-3">
                                    <span x-text="row.size"></span>
                                    <span x-show="row.notes" class="block text-xs text-muted" x-text="row.notes"></span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap"><span x-text="row.amount"></span> <span class="text-xs text-muted" x-text="row.payment_type"></span></td>
                                <td class="px-4 py-3 whitespace-nowrap" x-text="row.deposited_at"></td>
                                <td class="px-4 py-3 whitespace-nowrap" x-text="row.released_at || '-'"></td>
                                <td class="px-4 py-3 whitespace-nowrap" x-text="row.stored_for"></td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold" :class="{{ $toneClasses }}" x-text="row.status"></span></td>
                                <td class="px-4 py-3 text-xs text-muted">
                                    <span x-text="'In: ' + (row.deposited_by || '-')"></span>
                                    <span x-show="row.released_by" class="block" x-text="'Out: ' + row.released_by"></span>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <a x-show="row.job_order_url" :href="row.job_order_url" class="text-primary hover:underline" x-text="row.job_order_number"></a>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="data.analytics.baggage.list.length === 0">
                            <td colspan="10" class="px-4 py-10 text-center text-muted">No bags deposited in this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @endif

    {{-- ============ AUDIT & STAFF ============ --}}
    @if($canSeeAudit)
    <section x-show="tab === 'audit'" x-cloak class="space-y-4" role="tabpanel">
        <div class="{{ $panel }}">
            <h2 class="mb-1 text-base font-semibold">Things to Review</h2>
            <p class="mb-3 text-sm text-muted">Actions in this period that change money or stock. Red means check it; amber means worth a look.</p>
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <template x-for="row in data.analytics.audit?.flags ?? []" :key="row.label">
                    <div class="rounded-md border px-3 py-2.5" :class="{{ $toneClasses }}">
                        <p class="text-xs font-medium opacity-80" x-text="row.label"></p>
                        <p class="mt-0.5 text-sm font-semibold" x-text="row.value"></p>
                    </div>
                </template>
            </div>
        </div>

        <div class="{{ $tableWrap }}">
            <div class="border-b border-border px-4 py-3 dark:border-gray-800">
                <h2 class="text-base font-semibold">Staff Performance</h2>
                <p class="text-sm text-muted">Orders each staff member created and payments they received in this period.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3 text-center">Orders</th>
                            <th class="px-4 py-3 text-right">Billed</th>
                            <th class="px-4 py-3 text-right">Discounts Given</th>
                            <th class="px-4 py-3 text-center">Cancelled</th>
                            <th class="px-4 py-3 text-center">Payments</th>
                            <th class="px-4 py-3 text-right">Collected</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        <template x-for="staff in data.analytics.audit?.staff ?? []" :key="staff.id">
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium" x-text="staff.name"></p>
                                    <p class="text-xs text-muted" x-text="staff.role"></p>
                                </td>
                                <td class="px-4 py-3 text-center" x-text="staff.orders"></td>
                                <td class="px-4 py-3 text-right" x-text="staff.billed"></td>
                                <td class="px-4 py-3 text-right" x-text="staff.discounts"></td>
                                <td class="px-4 py-3 text-center" :class="staff.cancelled > 0 ? 'font-semibold text-red-600 dark:text-red-400' : ''" x-text="staff.cancelled"></td>
                                <td class="px-4 py-3 text-center" x-text="staff.payments"></td>
                                <td class="px-4 py-3 text-right font-semibold" x-text="staff.collected"></td>
                            </tr>
                        </template>
                        <tr x-show="(data.analytics.audit?.staff ?? []).length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted">No staff activity in this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="{{ $panel }}">
            <h2 class="mb-1 text-base font-semibold">Activity Log</h2>
            <p class="mb-3 text-sm text-muted">The latest 15 recorded actions in this period.</p>
            <ol class="space-y-2">
                <template x-for="row in data.analytics.audit?.activity ?? []" :key="row.id">
                    <li class="flex items-start gap-3 text-sm">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                              :class="row.tone === 'bad' ? 'bg-red-500' : (row.tone === 'warn' ? 'bg-amber-500' : 'bg-sky-500')"></span>
                        <div class="min-w-0 flex-1">
                            <p><span class="font-medium" x-text="row.user"></span> <span class="text-muted">·</span> <span x-text="row.action"></span></p>
                            <p class="truncate text-xs text-muted" x-show="row.detail" x-text="row.detail"></p>
                        </div>
                        <span class="shrink-0 text-xs text-muted" x-text="row.time"></span>
                    </li>
                </template>
                <li x-show="(data.analytics.audit?.activity ?? []).length === 0" class="py-6 text-center text-sm text-muted">No recorded activity in this period.</li>
            </ol>
        </div>
    </section>
    @endif
</div>

<script>
function dashboardPage({ fetchUrl, initialData, initialDateRange, hasExplicitRange, initialTab, tabs }) {
    const charts = {};
    const REFRESH_MS = 30000;

    // Which charts live on which tab; built lazily the first time a tab is shown
    // so Chart.js never measures a hidden (0px) canvas.
    const tabCharts = {
        overview: ['overviewSales', 'trend7'],
        sales: ['sales', 'paymentMix', 'paymentStatus', 'categorySales', 'transactionType', 'branchSales', 'topServices', 'topPresets'],
        deductions: ['deductions', 'expenseCategories'],
        balances: ['financial', 'aging'],
        operations: ['status', 'cycles'],
        baggage: ['baggageSizes', 'baggageDaily', 'baggageHourly'],
        audit: [],
    };

    const pad = (n) => String(n).padStart(2, '0');
    const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    return {
        data: initialData,
        dateRange: initialDateRange,
        tab: initialTab,
        status: 'idle',
        inFlight: false,
        picker: null,
        presets: [],
        clock: Date.now(),

        init() {
            // Live countdown for bags at the Baggage Counter.
            window.setInterval(() => { this.clock = Date.now(); }, 1000);

            const hashTab = window.location.hash.replace('#', '');
            if (tabs.includes(hashTab)) this.tab = hashTab;

            this.presets = this.buildPresets();

            this.$nextTick(() => {
                this.initDateRange();
                this.ensureCharts(this.tab);
                window.renderLucideIcons();
            });

            window.setInterval(() => this.refresh(), REFRESH_MS);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.refresh();
            });
            window.addEventListener('online', () => this.refresh(true));
        },

        get activePreset() {
            const match = this.presets.find((preset) => preset.range === this.dateRange);
            return match ? match.key : null;
        },

        buildPresets() {
            const today = new Date();
            const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
            const weekAgo = new Date(today); weekAgo.setDate(today.getDate() - 6);
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
            const range = (a, b) => `${ymd(a)} to ${ymd(b)}`;

            return [
                { key: 'today', label: 'Today', range: range(today, today) },
                { key: 'yesterday', label: 'Yesterday', range: range(yesterday, yesterday) },
                { key: 'week', label: 'Last 7 days', range: range(weekAgo, today) },
                { key: 'month', label: 'This month', range: range(monthStart, today) },
                { key: 'last_month', label: 'Last month', range: range(lastMonthStart, lastMonthEnd) },
            ];
        },

        applyPreset(preset) {
            this.dateRange = preset.range;
            this.$refs.dateRange.value = preset.range;
            this.$nextTick(() => this.$refs.filterForm.submit());
        },

        setTab(name) {
            if (!tabs.includes(name)) return;
            this.tab = name;
            try { history.replaceState(null, '', `#${name}`); } catch (e) {}
            this.$nextTick(() => this.ensureCharts(name));
        },

        pct(value) {
            return value === null || value === undefined ? '—' : `${value}%`;
        },

        bagOverdue(row) {
            return Boolean(row?.due_iso) && this.clock > new Date(row.due_iso).getTime();
        },

        bagCountdown(row) {
            if (!row?.due_iso) return '';
            const diff = new Date(row.due_iso).getTime() - this.clock;
            const total = Math.floor(Math.abs(diff) / 1000);
            const text = `${Math.floor(total / 3600)}h ${String(Math.floor((total % 3600) / 60)).padStart(2, '0')}m ${String(total % 60).padStart(2, '0')}s`;
            return diff >= 0 ? `${text} left` : `Overdue ${text}`;
        },

        bar(value) {
            return Math.max(0, Math.min(100, Number(value) || 0));
        },

        changeLabel() {
            const pct = this.data.sales.change_percent;
            if (pct === null || pct === undefined) {
                return this.data.sales.change_direction === 'up' ? 'New sales' : 'No change';
            }
            const arrow = pct > 0 ? '▲' : (pct < 0 ? '▼' : '•');
            return `${arrow} ${Math.abs(pct)}%`;
        },

        initDateRange() {
            if (!window.flatpickr) return;

            this.picker = window.flatpickr(this.$refs.dateRange, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                defaultDate: this.dateRange ? this.dateRange.split(' to ') : null,
                onClose: (dates, value) => {
                    // A single click in range mode gives one date; treat it as a one-day range.
                    this.dateRange = dates.length === 1 ? `${value} to ${value}` : value;
                },
            });
        },

        refresh(force = false) {
            if (this.inFlight) return;
            if (!force && document.hidden) return;

            this.inFlight = true;
            this.status = 'loading';

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 20000);

            fetch(fetchUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => {
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return response.json();
                })
                .then((payload) => {
                    this.data = payload;
                    this.status = 'idle';

                    // Without an explicit range the server always returns "today",
                    // so keep the picker in sync if the page stays open past midnight.
                    if (!hasExplicitRange && payload.period?.range && payload.period.range !== this.dateRange) {
                        this.dateRange = payload.period.range;
                        this.presets = this.buildPresets();
                        this.picker?.setDate(payload.period.range.split(' to '), false);
                    }

                    this.updateCharts();
                    this.$nextTick(() => window.renderLucideIcons());
                })
                .catch(() => { this.status = 'error'; })
                .finally(() => {
                    clearTimeout(timeout);
                    this.inFlight = false;
                });
        },

        // ---------- charts ----------
        theme() {
            const color = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#2E7D32';
            const grid = document.documentElement.classList.contains('dark') ? '#1f2937' : '#e2e8f0';
            return { color, grid, palette: [color, '#0ea5e9', '#f59e0b', '#10b981', '#6366f1', '#ef4444', '#14b8a6', '#8b5cf6'] };
        },

        chartDefinitions() {
            const { color, grid, palette } = this.theme();
            const line = (key) => ({
                type: 'line',
                key,
                data: () => ({
                    labels: this.data.charts[key].labels,
                    datasets: [{ label: 'Sales', data: this.data.charts[key].values, borderColor: color, backgroundColor: color + '22', fill: true, tension: 0.35, pointRadius: 2 }],
                }),
                options: this.axisOptions(grid, true),
            });
            const bar = (key, horizontal = false, money = true) => ({
                type: 'bar',
                key,
                data: () => this.dataset(key, palette),
                options: horizontal ? { ...this.axisOptions(grid, money), indexAxis: 'y' } : this.axisOptions(grid, money),
            });
            const pie = (key, type = 'doughnut') => ({
                type,
                key,
                data: () => this.dataset(key, palette),
                options: this.pieOptions(),
            });

            return {
                overviewSales: { ref: 'overviewSalesChart', ...line('sales') },
                sales: { ref: 'salesChart', ...line('sales') },
                paymentMix: { ref: 'paymentMixChart', ...pie('payment_mix') },
                transactionType: { ref: 'transactionTypeChart', ...pie('transaction_types', 'pie') },
                branchSales: { ref: 'branchSalesChart', ...bar('branch_sales', true) },
                topServices: { ref: 'topServicesChart', ...bar('top_services', true) },
                topPresets: { ref: 'topPresetsChart', ...bar('top_presets', true) },
                deductions: { ref: 'deductionsChart', ...pie('deductions_breakdown') },
                expenseCategories: { ref: 'expenseCategoriesChart', ...bar('expense_categories', true) },
                financial: { ref: 'financialChart', ...bar('financial_snapshot') },
                paymentStatus: { ref: 'paymentStatusChart', ...pie('payment_status') },
                categorySales: { ref: 'categorySalesChart', ...bar('category_sales', true) },
                aging: { ref: 'agingChart', ...bar('receivables_aging') },
                cycles: { ref: 'cyclesChart', ...bar('cycles', false, false) },
                baggageSizes: { ref: 'baggageSizesChart', ...pie('baggage_sizes') },
                baggageDaily: { ref: 'baggageDailyChart', ...bar('baggage_daily') },
                baggageHourly: { ref: 'baggageHourlyChart', ...bar('baggage_hourly', false, false) },
                trend7: {
                    ref: 'trend7Chart',
                    type: 'bar',
                    key: null,
                    data: () => this.trendData(color),
                    options: {
                        ...this.axisOptions(grid, false),
                        plugins: {
                            legend: { display: true, position: 'bottom' },
                            tooltip: { callbacks: { label: (ctx) => ctx.dataset.yAxisID === 'orders'
                                ? `${ctx.dataset.label}: ${ctx.parsed.y}`
                                : `${ctx.dataset.label}: ${this.formatMoney(ctx.parsed.y)}` } },
                        },
                        scales: {
                            x: { grid: { color: grid }, ticks: { color: '#64748B', maxRotation: 0, autoSkip: true } },
                            y: { beginAtZero: true, grid: { color: grid }, ticks: { color: '#64748B' } },
                            orders: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { color: '#64748B', precision: 0 } },
                        },
                    },
                },
                status: {
                    ref: 'statusChart',
                    type: 'bar',
                    key: 'status',
                    data: () => ({
                        labels: this.data.charts.status.labels,
                        datasets: [{ label: 'Orders', data: this.data.charts.status.values, backgroundColor: color, borderRadius: 6 }],
                    }),
                    options: this.axisOptions(grid, false),
                },
            };
        },

        ensureCharts(tabName) {
            if (!window.Chart) return;
            const definitions = this.chartDefinitions();

            (tabCharts[tabName] || []).forEach((name) => {
                if (charts[name]) {
                    charts[name].resize();
                    return;
                }
                const def = definitions[name];
                const canvas = this.$refs[def.ref];
                if (!def || !canvas) return;

                try {
                    charts[name] = new window.Chart(canvas, { type: def.type, data: def.data(), options: def.options });
                    charts[name].$dashboardKey = def.key;
                } catch (e) {
                    console.error(`Dashboard chart "${name}" failed`, e);
                }
            });
        },

        trendData(color) {
            const t = this.data.analytics.trend_7d;
            return {
                labels: t.labels,
                datasets: [
                    { type: 'bar', label: 'Sales', data: t.sales, backgroundColor: color, borderRadius: 4, yAxisID: 'y', order: 2 },
                    { type: 'bar', label: 'Expenses', data: t.expenses, backgroundColor: '#f59e0b', borderRadius: 4, yAxisID: 'y', order: 2 },
                    { type: 'line', label: 'Job Orders', data: t.orders, borderColor: '#6366f1', backgroundColor: '#6366f1', tension: 0.3, pointRadius: 3, yAxisID: 'orders', order: 1 },
                ],
            };
        },

        updateCharts() {
            if (charts.trend7) {
                const t = this.data.analytics.trend_7d;
                charts.trend7.data.labels = t.labels;
                charts.trend7.data.datasets[0].data = t.sales;
                charts.trend7.data.datasets[1].data = t.expenses;
                charts.trend7.data.datasets[2].data = t.orders;
                charts.trend7.update('none');
            }

            Object.values(charts).forEach((chart) => {
                const source = this.data.charts?.[chart.$dashboardKey];
                if (!source) return;
                chart.data.labels = source.labels;
                chart.data.datasets[0].data = source.values;
                chart.update('none');
            });
        },

        dataset(key, colors) {
            return {
                labels: this.data.charts[key].labels,
                datasets: [{ label: 'Amount', data: this.data.charts[key].values, backgroundColor: colors, borderColor: colors, borderRadius: 6 }],
            };
        },

        formatMoney(value) {
            const currency = this.data.currency || 'PHP';
            return `${currency} ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        },

        axisOptions(grid, money) {
            const valueAxis = { beginAtZero: true, grid: { color: grid }, ticks: { color: '#64748B', precision: 0 } };
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: money ? { callbacks: { label: (ctx) => this.formatMoney(ctx.parsed.x !== undefined && ctx.chart.options.indexAxis === 'y' ? ctx.parsed.x : ctx.parsed.y) } } : {},
                },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: '#64748B', maxRotation: 0, autoSkip: true } },
                    y: valueAxis,
                },
            };
        },

        pieOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            };
        },
    };
}
</script>
@endsection
