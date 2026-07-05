@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('content')
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
                Live sales, physical collections, workflow, receivables, and inventory signals.
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

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <template x-for="card in statCards" :key="card.key">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex h-8 w-8 items-center justify-center rounded-md bg-smoke text-primary dark:bg-gray-950">
                    <span :data-lucide="card.icon" class="h-4 w-4"></span>
                </div>
                <p class="text-xs font-medium text-muted" x-text="card.label"></p>
                <p class="mt-1 text-lg font-semibold" x-text="data.stats[card.key]"></p>
            </div>
        </template>
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.8fr)]">
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Sales Trend</h2>
                    <p class="text-sm text-muted">Sales owned by branch in the selected date range.</p>
                </div>
                <span data-lucide="payments" class="h-4 w-4 text-primary"></span>
            </div>
            <div class="h-72">
                <canvas x-ref="salesChart"></canvas>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Workflow Status</h2>
                    <p class="text-sm text-muted">Job orders by status.</p>
                </div>
                <span data-lucide="activity" class="h-4 w-4 text-primary"></span>
            </div>
            <div class="h-72">
                <canvas x-ref="statusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Payment Mix</h2>
                    <p class="text-sm text-muted">Physical collections by method.</p>
                </div>
                <span data-lucide="payments" class="h-4 w-4 text-primary"></span>
            </div>
            <div class="h-64">
                <canvas x-ref="paymentMixChart"></canvas>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">POS Type Mix</h2>
                    <p class="text-sm text-muted">Walk-in/drop-off versus delivery orders.</p>
                </div>
                <span data-lucide="shopping-bag" class="h-4 w-4 text-primary"></span>
            </div>
            <div class="h-64">
                <canvas x-ref="transactionTypeChart"></canvas>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Financial Snapshot</h2>
                    <p class="text-sm text-muted">Collections, expenses, receivables, and payables.</p>
                </div>
                <span data-lucide="scale" class="h-4 w-4 text-primary"></span>
            </div>
            <div class="h-64">
                <canvas x-ref="financialChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Top Selling Services</h2>
                    <p class="text-sm text-muted">Ranked by service sales amount in the selected range.</p>
                </div>
                <span data-lucide="services" class="h-4 w-4 text-primary"></span>
            </div>
            <div class="h-72">
                <canvas x-ref="topServicesChart"></canvas>
            </div>
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
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Top Selling Presets</h2>
                    <p class="text-sm text-muted">Preset sales from POS preset cart selections.</p>
                </div>
                <span data-lucide="tag" class="h-4 w-4 text-primary"></span>
            </div>
            <div class="h-72">
                <canvas x-ref="topPresetsChart"></canvas>
            </div>
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

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
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

        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Branch Sales</h2>
                    <p class="text-sm text-muted">Sales owned by branch in the selected range.</p>
                </div>
                <span data-lucide="branches" class="h-4 w-4 text-primary"></span>
            </div>
            <div class="h-80">
                <canvas x-ref="branchSalesChart"></canvas>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
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
</div>

<script>
function dashboardPage(fetchUrl, initialData, initialDateRange) {
    return {
        data: initialData,
        dateRange: initialDateRange,
        salesChart: null,
        statusChart: null,
        paymentMixChart: null,
        transactionTypeChart: null,
        financialChart: null,
        topServicesChart: null,
        topPresetsChart: null,
        branchSalesChart: null,
        statCards: [
            { key: 'sales', label: 'Sales Owned', icon: 'payments' },
            { key: 'collections', label: 'Physical Collections', icon: 'receipt' },
            { key: 'cash_drawer', label: 'Expected Cash Drawer', icon: 'wallet' },
            { key: 'gcash', label: 'Expected GCash', icon: 'payments' },
            { key: 'expenses', label: 'Recorded Expenses', icon: 'expense' },
            { key: 'accounts_payable', label: 'Accounts Payable', icon: 'receivables' },
            { key: 'receivables', label: 'Unpaid Customer Balance', icon: 'receivables' },
            { key: 'over_short', label: 'Z Reading Over / Short', icon: 'activity' },
            { key: 'orders', label: 'Orders in Period', icon: 'jobOrders' },
            { key: 'open_orders', label: 'Open Orders', icon: 'activity' },
            { key: 'ready_for_pickup', label: 'Ready for Pickup', icon: 'laundry' },
            { key: 'ready_for_delivery', label: 'Ready for Delivery', icon: 'truck' },
        ],
        init() {
            this.$nextTick(() => {
                this.initDateRange();
                this.drawCharts();
                window.renderLucideIcons();
            });

            window.setInterval(() => this.refresh(), 30000);
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
        drawCharts() {
            const color = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#2E7D32';
            const grid = document.documentElement.classList.contains('dark') ? '#1f2937' : '#e2e8f0';

            this.salesChart = new window.Chart(this.$refs.salesChart, {
                type: 'line',
                data: {
                    labels: this.data.charts.sales.labels,
                    datasets: [{
                        label: 'Sales',
                        data: this.data.charts.sales.values,
                        borderColor: color,
                        backgroundColor: color + '22',
                        fill: true,
                        tension: 0.35,
                    }]
                },
                options: this.chartOptions(grid)
            });

            this.statusChart = new window.Chart(this.$refs.statusChart, {
                type: 'bar',
                data: {
                    labels: this.data.charts.status.labels,
                    datasets: [{
                        label: 'Orders',
                        data: this.data.charts.status.values,
                        backgroundColor: color,
                        borderRadius: 6,
                    }]
                },
                options: this.chartOptions(grid)
            });

            this.paymentMixChart = new window.Chart(this.$refs.paymentMixChart, {
                type: 'doughnut',
                data: this.dataset('payment_mix', this.palette(color)),
                options: this.pieOptions()
            });

            this.transactionTypeChart = new window.Chart(this.$refs.transactionTypeChart, {
                type: 'pie',
                data: this.dataset('transaction_types', this.palette(color)),
                options: this.pieOptions()
            });

            this.financialChart = new window.Chart(this.$refs.financialChart, {
                type: 'bar',
                data: this.dataset('financial_snapshot', this.palette(color)),
                options: this.chartOptions(grid)
            });

            this.topServicesChart = new window.Chart(this.$refs.topServicesChart, {
                type: 'bar',
                data: this.dataset('top_services', this.palette(color)),
                options: this.horizontalOptions(grid)
            });

            this.topPresetsChart = new window.Chart(this.$refs.topPresetsChart, {
                type: 'bar',
                data: this.dataset('top_presets', this.palette(color)),
                options: this.horizontalOptions(grid)
            });

            this.branchSalesChart = new window.Chart(this.$refs.branchSalesChart, {
                type: 'bar',
                data: this.dataset('branch_sales', this.palette(color)),
                options: this.horizontalOptions(grid)
            });
        },
        updateCharts() {
            if (!this.salesChart || !this.statusChart) return;

            this.salesChart.data.labels = this.data.charts.sales.labels;
            this.salesChart.data.datasets[0].data = this.data.charts.sales.values;
            this.salesChart.update();

            this.statusChart.data.labels = this.data.charts.status.labels;
            this.statusChart.data.datasets[0].data = this.data.charts.status.values;
            this.statusChart.update();

            this.updateChart(this.paymentMixChart, 'payment_mix');
            this.updateChart(this.transactionTypeChart, 'transaction_types');
            this.updateChart(this.financialChart, 'financial_snapshot');
            this.updateChart(this.topServicesChart, 'top_services');
            this.updateChart(this.topPresetsChart, 'top_presets');
            this.updateChart(this.branchSalesChart, 'branch_sales');
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
        updateChart(chart, key) {
            if (!chart) return;

            chart.data.labels = this.data.charts[key].labels;
            chart.data.datasets[0].data = this.data.charts[key].values;
            chart.update();
        },
        palette(primary) {
            return [primary, '#0ea5e9', '#f59e0b', '#10b981', '#6366f1', '#ef4444', '#14b8a6', '#8b5cf6'];
        },
        chartOptions(grid) {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
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
