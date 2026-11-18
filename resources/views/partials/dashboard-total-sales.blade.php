{{-- Total Sales = normal Job Order sales + PO sales (shown on the Overview and Sales tabs). --}}
<div class="{{ $panel }}">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-base font-semibold">Total Sales</h2>
            <p class="text-sm text-muted">Normal job orders plus PO orders in this period.</p>
        </div>
        @if(auth()->user()->hasMenuAccess('po_transactions'))
            <a href="{{ route('admin.po-transactions.index') }}" class="{{ $linkBtn }}">PO transactions</a>
        @endif
    </div>
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sky-700 dark:border-sky-900/60 dark:bg-sky-500/10 dark:text-sky-300">
            <p class="text-xs font-medium">Job Order Sales</p>
            <p class="mt-1 text-2xl font-semibold" x-text="data.sales.job_order_sales"></p>
            <p class="text-xs opacity-80">Payments from normal job orders</p>
        </div>
        <div class="rounded-lg border border-violet-200 bg-violet-50 p-3 text-violet-700 dark:border-violet-900/60 dark:bg-violet-500/10 dark:text-violet-300">
            <p class="text-xs font-medium">PO Sales</p>
            <p class="mt-1 text-2xl font-semibold" x-text="data.sales.po_sales"></p>
            <p class="text-xs opacity-80" x-text="`${data.sales.po_count} PO · collected ${data.sales.po_collected} · balance ${data.sales.po_balance}`"></p>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-500/10 dark:text-emerald-300">
            <p class="text-xs font-medium">Total Sales (Job Order + PO)</p>
            <p class="mt-1 text-2xl font-bold" x-text="data.sales.combined_sales"></p>
            <p class="text-xs opacity-80">Job Order Sales + PO Sales</p>
        </div>
    </div>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-700 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-300">
            <p class="text-xs font-medium">Less: All Expenses</p>
            <p class="mt-1 text-2xl font-semibold" x-text="`− ${data.sales.all_expenses}`"></p>
            <p class="text-xs opacity-80" x-text="`${data.sales.expenses_count} expense entries (store cash, GCash, bank, owner-paid)`"></p>
        </div>
        <div class="rounded-lg border p-3"
             :class="data.sales.net_is_negative
                ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-300'
                : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-500/10 dark:text-emerald-300'">
            <p class="text-xs font-medium">Net Sales After All Expenses</p>
            <p class="mt-1 text-2xl font-bold" x-text="data.sales.net_after_expenses"></p>
            <p class="text-xs opacity-80">Total Sales − All Expenses</p>
        </div>
    </div>
</div>
