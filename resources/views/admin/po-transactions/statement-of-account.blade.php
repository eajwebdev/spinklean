@extends('layouts.app')

@section('page_title', 'PO Statement of Account')

@section('content')
@php
    $currency = $settings->currency ?? 'PHP';
    $dateRangeValue = request('date_range') ?: $dateFrom.' to '.$dateTo;
@endphp
<div class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="file-text" class="h-3.5 w-3.5"></span>
                Corporate billing report
            </div>
            <h1 class="text-xl font-semibold">PO Statement of Account</h1>
            <p class="text-sm text-muted">Bills laundry on the day it was delivered (Delivery Receipt date). Canceled job orders are excluded.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.po-transactions.index') }}" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900">
                <span data-lucide="arrow-left" class="h-4 w-4"></span>
                Back to PO Transactions
            </a>
            @if($customer)
                <a href="{{ route('admin.po-transactions.statement-of-account.pdf', request()->query()) }}" target="_blank" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
                    <span data-lucide="download" class="h-4 w-4"></span>
                    Download PDF
                </a>
            @endif
        </div>
    </div>

    <form
        method="GET"
        action="{{ route('admin.po-transactions.statement-of-account') }}"
        x-data="{
            dateRange: @js($dateRangeValue),
            customers: @js($customers->map(fn ($option) => [
                'id' => (string) $option->id,
                'label' => $option->name.($option->phone ? ' - '.$option->phone : ''),
                'search' => strtolower(collect([$option->name, $option->phone, $option->email])->filter()->implode(' ')),
            ])->values()),
            selectedCustomerId: @js($customer ? (string) $customer->id : ''),
            customerSearch: @js($customer ? $customer->name.($customer->phone ? ' - '.$customer->phone : '') : ''),
            customerOpen: false,
            get filteredCustomers() {
                const term = this.customerSearch.toLowerCase().trim();
                return term ? this.customers.filter(customer => customer.search.includes(term)) : this.customers;
            },
            selectCustomer(customer) {
                this.selectedCustomerId = customer.id;
                this.customerSearch = customer.label;
                this.customerOpen = false;
            },
            clearCustomer() {
                this.selectedCustomerId = '';
                this.customerSearch = '';
                this.customerOpen = true;
                this.$nextTick(() => this.$refs.customerSearch.focus());
            },
            init() {
                this.$nextTick(() => {
                    if (!window.flatpickr) return;
                    window.flatpickr(this.$refs.dateRange, {
                        mode: 'range',
                        dateFormat: 'Y-m-d',
                        defaultDate: this.dateRange ? this.dateRange.split(' to ') : null,
                        onClose: (dates, value) => this.dateRange = value,
                    });
                });
            },
        }"
        @submit="if (!selectedCustomerId) { $event.preventDefault(); customerOpen = true; $refs.customerSearch.focus(); }"
        class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:flex xl:items-end">
            <div class="relative min-w-0 xl:flex-1" @click.outside="customerOpen = false">
                <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-muted">PO Customer</span>
                <input type="hidden" name="customer_id" :value="selectedCustomerId">
                <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 focus-within:border-primary dark:border-gray-800 dark:bg-gray-950">
                    <span data-lucide="search" class="h-4 w-4 shrink-0 text-muted"></span>
                    <input
                        x-ref="customerSearch"
                        x-model="customerSearch"
                        @focus="customerOpen = true"
                        @input="selectedCustomerId = ''; customerOpen = true"
                        @keydown.escape="customerOpen = false"
                        type="search"
                        autocomplete="off"
                        placeholder="Search customer name, phone, or email..."
                        class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                    >
                    <button x-show="selectedCustomerId" type="button" @click="clearCustomer()" title="Clear customer" aria-label="Clear selected customer" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded hover:bg-smoke dark:hover:bg-gray-800">
                        <span data-lucide="x" class="h-3.5 w-3.5"></span>
                    </button>
                    <button x-show="!selectedCustomerId" type="button" @click="customerOpen = !customerOpen; if (customerOpen) $nextTick(() => $refs.customerSearch.focus())" title="Show PO customers" aria-label="Show PO customers" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded hover:bg-smoke dark:hover:bg-gray-800">
                        <span data-lucide="chevron-down" class="h-3.5 w-3.5"></span>
                    </button>
                </div>
                <div x-cloak x-show="customerOpen" x-transition class="absolute z-40 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-border bg-white p-1 shadow-xl dark:border-gray-800 dark:bg-gray-950">
                    <template x-for="option in filteredCustomers" :key="option.id">
                        <button type="button" @click="selectCustomer(option)" class="flex w-full items-center justify-between gap-3 rounded px-3 py-2 text-left text-sm hover:bg-smoke dark:hover:bg-gray-900">
                            <span class="min-w-0 truncate" x-text="option.label"></span>
                            <span x-show="String(selectedCustomerId) === String(option.id)" data-lucide="check" class="h-4 w-4 shrink-0 text-primary"></span>
                        </button>
                    </template>
                    <div x-show="filteredCustomers.length === 0" class="px-3 py-6 text-center text-sm text-muted">No PO customers found.</div>
                </div>
            </div>

            @if($canChooseBranch)
                <label class="xl:w-48">
                    <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-muted">Branch</span>
                    <select name="branch_id" onchange="this.form.customer_id.value = ''; this.form.submit()" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <option value="">All branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
            @else
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
            @endif

            <label class="xl:w-44">
                <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-muted">PO Status</span>
                <select name="status" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="xl:w-64">
                <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-muted">Statement Period</span>
                <span class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                    <span data-lucide="calendar" class="h-4 w-4 shrink-0 text-muted"></span>
                    <input x-ref="dateRange" x-model="dateRange" name="date_range" type="text" required autocomplete="off" placeholder="Select date range" class="min-w-0 flex-1 bg-transparent text-sm outline-none">
                </span>
            </label>

            <div class="flex items-center gap-2 sm:col-span-2 xl:col-span-1 xl:shrink-0">
                <button type="submit" class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90 xl:flex-none">
                    <span data-lucide="file-search" class="h-4 w-4"></span>
                    Generate
                </button>
                <a href="{{ route('admin.po-transactions.statement-of-account') }}" title="Clear filters" aria-label="Clear statement filters" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                    <span data-lucide="rotate-ccw" class="h-4 w-4"></span>
                </a>
            </div>
        </div>
    </form>

    @if(! $customer)
        <div class="rounded-lg border border-dashed border-border bg-white p-6 text-center dark:border-gray-800 dark:bg-gray-900">
            <span data-lucide="file-search" class="mx-auto mb-2 h-6 w-6 text-muted"></span>
            <p class="text-sm font-medium">Choose a PO customer to begin</p>
            <p class="mt-1 text-xs text-muted">The report will use the selected period, branch, and PO status.</p>
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Delivered this period</p>
                <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format((float) $summary['amount'], 2) }}</p>
                <p class="text-xs text-muted">{{ number_format($summary['deliveries']) }} delivery receipt(s)</p>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Total Paid</p>
                <p class="mt-1 text-lg font-semibold text-green-600 dark:text-green-400">{{ $currency }} {{ number_format((float) $summary['paid'], 2) }}</p>
                <p class="text-xs text-muted">On the job orders below</p>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Amount Due</p>
                <p class="mt-1 text-lg font-semibold {{ $summary['balance'] > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ $currency }} {{ number_format((float) $summary['balance'], 2) }}</p>
                <p class="text-xs text-muted">Delivered up to {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M d, Y') }}, not yet paid</p>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Not yet delivered</p>
                <p class="mt-1 text-lg font-semibold text-amber-600 dark:text-amber-400">{{ $currency }} {{ number_format((float) $summary['not_yet_delivered'], 2) }}</p>
                <p class="text-xs text-muted">Billed once it goes out on a DR</p>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-border p-4 dark:border-gray-800">
                <div class="flex flex-col justify-between gap-2 sm:flex-row">
                    <div>
                        <h2 class="font-semibold">{{ $customer->name }}</h2>
                        <p class="text-sm text-muted">{{ $customer->address ?: 'No address recorded' }}</p>
                        <p class="text-sm text-muted">{{ collect([$customer->phone, $customer->email])->filter()->implode(' | ') ?: 'No contact details recorded' }}</p>
                    </div>
                    <div class="text-sm sm:text-right">
                        <p class="font-medium">Statement Period</p>
                        <p class="text-muted">{{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M d, Y') }}</p>
                        <a href="{{ route('admin.po-deliveries.create', ['customer_id' => $customer->id]) }}" class="mt-2 inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-xs font-semibold hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                            <span data-lucide="truck" class="h-3.5 w-3.5"></span>
                            New Delivery Receipt
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-b border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted dark:border-gray-800">Deliveries in this period</div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3">Date Delivered</th>
                            <th class="px-4 py-3">DR #</th>
                            <th class="px-4 py-3">Job Orders</th>
                            <th class="px-4 py-3">PO Number</th>
                            <th class="px-4 py-3 text-right">Pieces</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        @forelse($deliveries as $row)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3">{{ $row['delivery']->delivered_on->format('M d, Y') }}</td>
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('admin.po-deliveries.print', $row['delivery']) }}" target="_blank" class="text-primary hover:underline">{{ $row['delivery']->dr_number }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $row['job_orders']->implode(', ') }}</td>
                                <td class="px-4 py-3">{{ $row['po_numbers']->implode(', ') }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format($row['pieces'], 2), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $row['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-muted">Nothing was delivered to this customer in this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if($deliveries->isNotEmpty())
                        <tfoot class="border-t border-border bg-smoke font-semibold dark:border-gray-800 dark:bg-gray-950">
                            <tr>
                                <td colspan="5" class="px-4 py-3">Statement Total</td>
                                <td class="px-4 py-3 text-right">{{ $currency }} {{ number_format((float) $summary['amount'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if($accounts->isNotEmpty())
                <div class="border-y border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted dark:border-gray-800">Job order balances</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                            <tr>
                                <th class="px-4 py-3">PO Number</th>
                                <th class="px-4 py-3">Job Order</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Order Amount</th>
                                <th class="px-4 py-3 text-right">Delivered</th>
                                <th class="px-4 py-3 text-right">Paid</th>
                                <th class="px-4 py-3 text-right">Due</th>
                                <th class="px-4 py-3 text-right">Not Yet Delivered</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border dark:divide-gray-800">
                            @foreach($accounts as $account)
                                @php($po = $account['po'])
                                @php($undelivered = max((float) $po->amount - \App\Support\PoDeliveries::deliveredAmount($po), 0))
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $po->po_number }}</td>
                                    <td class="px-4 py-3">{{ $po->jobOrder?->job_order_number ?? 'N/A' }}</td>
                                    <td class="px-4 py-3"><span class="{{ \App\Support\StatusBadge::classes($po->status) }}">{{ \App\Support\StatusBadge::label($po->status) }}</span></td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float) $po->amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float) $account['delivered'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-green-700 dark:text-green-300">{{ $currency }} {{ number_format((float) $po->paid_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $account['due'] > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ $currency }} {{ number_format((float) $account['due'], 2) }}</td>
                                    <td class="px-4 py-3 text-right {{ $undelivered > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted' }}">{{ number_format($undelivered, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if($summary['balance'] > 0)
            <form method="POST" action="{{ route('admin.po-transactions.statement-of-account.payments') }}" class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">
                <div class="mb-3">
                    <h2 class="font-semibold">Record Monthly Payment</h2>
                    <p class="text-sm text-muted">One payment for this statement, such as the customer's cheque. It is applied to the delivered job orders above, oldest delivery first. It can't pay for laundry not yet delivered.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <label>
                        <span class="mb-1 block text-sm font-medium">Amount</span>
                        <input type="number" name="amount" step="0.01" min="0.01" max="{{ $summary['balance'] }}" value="{{ old('amount', number_format((float) $summary['balance'], 2, '.', '')) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                        @error('amount')<span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label>
                        <span class="mb-1 block text-sm font-medium">Method</span>
                        <select name="payment_method" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                            <option value="cheque">Cheque</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="gcash">GCash</option>
                            <option value="cash">Cash</option>
                        </select>
                    </label>
                    <label>
                        <span class="mb-1 block text-sm font-medium">Reference No.</span>
                        <input name="reference_no" value="{{ old('reference_no') }}" placeholder="Cheque or bank reference" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    </label>
                    <label>
                        <span class="mb-1 block text-sm font-medium">Date Paid</span>
                        <input type="date" name="paid_on" value="{{ old('paid_on', today()->toDateString()) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    </label>
                    <label>
                        <span class="mb-1 block text-sm font-medium">Remarks</span>
                        <input name="remarks" value="{{ old('remarks', 'SOA '.\Illuminate\Support\Carbon::parse($dateFrom)->format('M Y')) }}" maxlength="500" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    </label>
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">
                        <span data-lucide="save" class="h-4 w-4"></span>
                        Record Payment
                    </button>
                </div>
            </form>
        @endif

        @if($payments->isNotEmpty())
            <div class="rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-border p-4 dark:border-gray-800">
                    <h2 class="font-semibold">Payment History</h2>
                    <p class="text-sm text-muted">Payments recorded against the job orders shown above.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                            <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Payment Number</th><th class="px-4 py-3">PO Number</th><th class="px-4 py-3">Method</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3 text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border dark:divide-gray-800">
                            @foreach($payments as $payment)
                                @php($paymentPo = $accounts->firstWhere('po.id', $payment->po_transaction_id)['po'] ?? null)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3">{{ $payment->paid_at?->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $payment->payment_number }}</td>
                                    <td class="px-4 py-3">{{ $paymentPo?->po_number ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">{{ \App\Support\StatusBadge::label($payment->payment_method) }}</td>
                                    <td class="px-4 py-3">{{ $payment->reference_no ?: 'N/A' }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ $currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
