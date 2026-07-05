@extends('layouts.app')

@section('page_title', 'Create Z Reading')

@section('content')
@php
    $currency = $appSettings?->currency ?? 'PHP';
    $cashCount = old('cash_count', $reading?->cash_count ?? []);
    $actualGcash = old('actual_gcash_amount', $reading?->actual_gcash_amount ?? $summary['expected_gcash_amount']);
    $actualBank = old('actual_bank_amount', $reading?->actual_bank_amount ?? $summary['expected_bank_amount']);
@endphp

<div
    x-data="{
        denominations: @js(array_keys($denominations)),
        counts: @js(collect($denominations)->mapWithKeys(fn ($label, $value) => [$value => (int) ($cashCount[$value] ?? 0)])->all()),
        expectedCashDrawer: Number(@js($summary['expected_cash_drawer_amount'])),
        expectedGcash: Number(@js($summary['expected_gcash_amount'])),
        expectedBank: Number(@js($summary['expected_bank_amount'])),
        actualGcash: Number(@js($actualGcash ?: 0)),
        actualBank: Number(@js($actualBank ?: 0)),
        machineCounters: @js($machineCounters),
        get actualCash() {
            return this.denominations.reduce((total, value) => total + (Number(value) * Number(this.counts[value] || 0)), 0);
        },
        get expectedTotal() {
            return this.expectedCashDrawer + this.expectedGcash + this.expectedBank;
        },
        get actualTotal() {
            return this.actualCash + Number(this.actualGcash || 0) + Number(this.actualBank || 0);
        },
        get overShort() {
            return this.actualTotal - this.expectedTotal;
        },
        money(value) {
            return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        cycleTotal(machine, type) {
            const values = this.machineCounters[machine]?.[type] || {};
            if (values.beginning === '' || values.ending === '' || values.beginning == null || values.ending == null) return 0;
            return Math.max(0, Number(values.ending) - Number(values.beginning));
        }
    }"
    class="space-y-4"
>
    <div class="flex flex-col gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-1.5 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="receipt" class="h-3.5 w-3.5"></span>
                End-of-day closing
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Create Z Reading</h1>
            <p class="text-sm text-muted">Encode machine counters, cash count, GCash, and over/short reconciliation.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.z-readings.index', ['branch_id' => $branch->id, 'business_date' => $businessDate]) }}" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900">
                <span data-lucide="arrow-left" class="h-4 w-4"></span>
                Back
            </a>
            @if($reading)
                <a href="{{ route('admin.z-readings.pdf', $reading) }}" target="_blank" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900">
                    <span data-lucide="file-text" class="h-4 w-4"></span>
                    PDF
                </a>
            @endif
            <span class="inline-flex h-9 items-center rounded-md bg-smoke px-3 text-sm font-medium text-muted dark:bg-gray-950">
                {{ \Illuminate\Support\Carbon::parse($businessDate)->format('M d, Y') }}
            </span>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.z-readings.create') }}" class="grid gap-2 rounded-lg border border-border bg-white p-2.5 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-[1fr_1fr_auto]">
        @if($canChooseBranch)
            <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                @foreach($branches as $optionBranch)
                    <option value="{{ $optionBranch->id }}" @selected((int) $branch->id === (int) $optionBranch->id)>{{ $optionBranch->name }}</option>
                @endforeach
            </select>
        @else
            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
            <div class="flex h-9 items-center rounded-md border border-border bg-smoke px-3 text-sm font-medium dark:border-gray-800 dark:bg-gray-950">{{ $branch->name }}</div>
        @endif

        <input name="business_date" value="{{ $businessDate }}" type="date" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">

        <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
            <span data-lucide="search" class="h-4 w-4"></span>
        </button>
    </form>

    <div class="grid gap-2 md:grid-cols-4">
        <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium text-muted">Expected Cash Drawer</p>
            <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format((float) $summary['expected_cash_drawer_amount'], 2) }}</p>
            <p class="mt-0.5 hidden text-xs text-muted xl:block">Cash collections and deposits less cash expenses and withdrawals.</p>
        </div>
        <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium text-muted">Expected GCash</p>
            <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format((float) $summary['expected_gcash_amount'], 2) }}</p>
            <p class="mt-0.5 hidden text-xs text-muted xl:block">Collections plus owner funding, less payable repayments.</p>
        </div>
        <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium text-muted">Declared Total</p>
            <p class="mt-1 text-lg font-semibold">{{ $currency }} <span x-text="money(actualTotal)"></span></p>
            <p class="mt-0.5 hidden text-xs text-muted xl:block">Cash plus cashless balances.</p>
        </div>
        <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium text-muted">Balance Over / Short</p>
            <p class="mt-1 text-lg font-semibold" :class="overShort < 0 ? 'text-red-600' : (overShort > 0 ? 'text-emerald-600' : '')">
                {{ $currency }} <span x-text="money(overShort)"></span>
            </p>
            <p class="mt-0.5 text-xs text-muted">{{ $summary['transaction_count'] }} job orders</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.z-readings.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
        <input type="hidden" name="business_date" value="{{ $businessDate }}">

        <!-- Daily Operations Summary -->
        <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-3">
                <h2 class="text-base font-semibold">Daily Operations Summary</h2>
                <p class="text-xs text-muted">Workbook-style totals are summarized here; transaction, payment, expense, inventory, and machine details are included in the PDF.</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    ['Job Orders', number_format((int) $summary['transaction_count'])],
                    ['Total Sales', $currency.' '.number_format((float) $summary['daily_total_sales'], 2)],
                    ['Current Payments', $currency.' '.number_format((float) $summary['current_sales_payment_total'], 2)],
                    ['Previous Payments', $currency.' '.number_format((float) $summary['previous_payment_total'], 2)],
                    ['Unpaid', $currency.' '.number_format((float) $summary['daily_unpaid_amount'], 2)],
                    ['Expenses', $currency.' '.number_format((float) array_sum(array_column(data_get($summary, 'expense_breakdown.items', []), 'amount')), 2)],
                    ['Wash Cycles', number_format((int) collect($summary['machine_cycles'])->where('cycle_type', 'wash')->sum('cycle_count'))],
                    ['Dry Cycles', number_format((int) collect($summary['machine_cycles'])->where('cycle_type', 'dry')->sum('cycle_count'))],
                ] as [$label, $value])
                    <div class="rounded-md bg-smoke p-2.5 dark:bg-gray-950">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted">{{ $label }}</p>
                        <p class="mt-1 text-sm font-semibold">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Cash Count and Balances Side by Side -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column: Cash Count -->
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-2 border-b border-border p-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold">Cash Count</h2>
                            <p class="text-xs text-muted">Enter quantity for each bill or coin.</p>
                        </div>
                        <div class="rounded-md bg-smoke px-3 py-1.5 text-right dark:bg-gray-950">
                            <p class="text-[11px] font-medium uppercase text-muted">Actual Cash</p>
                            <p class="text-base font-semibold">{{ $currency }} <span x-text="money(actualCash)"></span></p>
                        </div>
                    </div>
                    <div class="grid gap-2 p-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                        @foreach($denominations as $value => $label)
                            <label class="grid grid-cols-[minmax(5.5rem,1fr)_5.75rem] items-center gap-2 rounded-md border border-border bg-smoke p-2 dark:border-gray-800 dark:bg-gray-950">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold">{{ $label }}</span>
                                    <span class="mt-0.5 block text-xs text-muted">{{ $currency }} <span x-text="money(Number('{{ $value }}') * Number(counts['{{ $value }}'] || 0))"></span></span>
                                </span>
                                <input
                                    name="cash_count[{{ $value }}]"
                                    x-model.number="counts['{{ $value }}']"
                                    type="number"
                                    min="0"
                                    step="1"
                                    inputmode="numeric"
                                    class="h-9 w-full rounded-md border border-border bg-white px-2 text-right text-sm font-semibold dark:border-gray-800 dark:bg-gray-900"
                                    aria-label="{{ $label }} quantity"
                                >
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-3">
                        <h2 class="text-base font-semibold">Machine Counter Readings</h2>
                        <p class="text-xs text-muted">Beginning comes from the previous Z Reading ending; ending is auto-computed from detected cycles for the selected date.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        @foreach(['wash' => 'Wash', 'dry' => 'Dry'] as $type => $label)
                            @for($machine = 1; $machine <= $machineCount; $machine++)
                                <div class="overflow-hidden rounded-md border border-border dark:border-gray-800">
                                    <div class="bg-smoke px-3 py-2 text-center text-xs font-semibold uppercase dark:bg-gray-950">{{ $label }} {{ $machine }}</div>
                                    <div class="grid grid-cols-2 gap-2 p-2">
                                        @foreach(['beginning' => 'Beginning', 'ending' => 'Ending'] as $field => $fieldLabel)
                                            <label>
                                                <span class="text-[11px] font-medium uppercase text-muted">{{ $fieldLabel }}</span>
                                                @if($field === 'beginning')
                                                    <input type="hidden" name="machine_counters[{{ $machine }}][{{ $type }}][{{ $field }}]" x-model.number="machineCounters['{{ $machine }}']['{{ $type }}']['{{ $field }}']">
                                                    <input
                                                        x-model.number="machineCounters['{{ $machine }}']['{{ $type }}']['{{ $field }}']"
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        inputmode="numeric"
                                                        disabled
                                                        class="mt-1 h-9 w-full rounded-md border border-border bg-smoke px-2 text-right text-sm font-semibold text-muted dark:border-gray-800 dark:bg-gray-950"
                                                        aria-label="{{ $fieldLabel }} {{ $label }} {{ $machine }}"
                                                    >
                                                @else
                                                    <input type="hidden" name="machine_counters[{{ $machine }}][{{ $type }}][{{ $field }}]" x-model.number="machineCounters['{{ $machine }}']['{{ $type }}']['{{ $field }}']">
                                                    <div class="mt-1 h-9 rounded-md border border-border bg-smoke px-2 py-2 text-right text-sm font-semibold dark:border-gray-800 dark:bg-gray-950" x-text="machineCounters['{{ $machine }}']['{{ $type }}']['{{ $field }}']"></div>
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="flex justify-between border-t border-border bg-blue-50 px-3 py-2 text-xs font-semibold dark:border-gray-800 dark:bg-blue-950/30">
                                        <span>Total {{ $label }} Cycle</span>
                                        <span x-text="cycleTotal('{{ $machine }}', '{{ $type }}')"></span>
                                    </div>
                                </div>
                            @endfor
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right Column: Balances & System Expected -->
            <div class="space-y-3">
                <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-base font-semibold">Balances & System Expected</h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                        <label class="block">
                            <span class="text-xs font-medium text-muted">Actual GCash Balance</span>
                            <input name="actual_gcash_amount" x-model.number="actualGcash" type="number" min="0" step="0.01" inputmode="decimal" class="mt-1 h-9 w-full rounded-md border border-border bg-white px-3 text-sm font-semibold dark:border-gray-800 dark:bg-gray-950">
                        </label>
                        <label class="block">
                            <span class="text-xs font-medium text-muted">Actual Bank Balance</span>
                            <input name="actual_bank_amount" x-model.number="actualBank" type="number" min="0" step="0.01" inputmode="decimal" class="mt-1 h-9 w-full rounded-md border border-border bg-white px-3 text-sm font-semibold dark:border-gray-800 dark:bg-gray-950">
                        </label>
                    </div>
                    <div class="mt-3 space-y-1.5 border-t border-border pt-3 text-sm dark:border-gray-800">
                        <div class="flex justify-between"><span class="text-muted">Cash payments</span><span class="font-medium">{{ $currency }} {{ number_format((float) $summary['expected_cash_amount'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Store-cash expenses</span><span class="font-medium">- {{ $currency }} {{ number_format((float) $summary['cash_expense_amount'], 2) }}</span></div>
                        @if((float) data_get($summary, 'expense_breakdown.owner', 0) > 0)
                            <div class="flex justify-between"><span class="text-muted">Owner-paid expenses (not counted)</span><span class="font-medium">{{ $currency }} {{ number_format((float) data_get($summary, 'expense_breakdown.owner', 0), 2) }}</span></div>
                        @endif
                        <div class="flex justify-between"><span class="text-muted">Cash added</span><span class="font-medium">{{ $currency }} {{ number_format((float) data_get($summary, 'expense_breakdown.money_movements.cash_in', 0), 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Cash withdrawn</span><span class="font-medium">- {{ $currency }} {{ number_format((float) data_get($summary, 'expense_breakdown.money_movements.cash_out', 0), 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Expected GCash net balance</span><span class="font-medium">{{ $currency }} {{ number_format((float) $summary['expected_gcash_amount'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Expected Bank net balance</span><span class="font-medium">{{ $currency }} {{ number_format((float) $summary['expected_bank_amount'], 2) }}</span></div>
                        <div class="rounded-md bg-smoke px-2 py-1.5 text-xs text-muted dark:bg-gray-950">
                            Expected GCash and Bank include owner funding, then subtract store-paid expenses and accounts payable repayments for the same payment channel.
                        </div>
                        <div class="flex justify-between border-t border-border pt-2 font-semibold dark:border-gray-800"><span>Expected Total</span><span>{{ $currency }} {{ number_format((float) $summary['expected_total_amount'], 2) }}</span></div>
                    </div>
                </div>

                <a href="{{ route('admin.petty-cash.index', ['branch_id' => $branch->id, 'movement_date' => $businessDate]) }}" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border border-border bg-white px-4 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900">
                    <span data-lucide="wallet" class="h-4 w-4"></span>
                    Manage Petty Cash
                </a>
            </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end">
            <button type="submit" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-6 text-sm font-medium text-white hover:opacity-90">
                <span data-lucide="save" class="h-4 w-4"></span>
                Save Z Reading
            </button>
        </div>
    </form>
</div>
@endsection
