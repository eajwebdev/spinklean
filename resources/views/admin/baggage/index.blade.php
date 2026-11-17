@extends('layouts.app')

@section('page_title', 'Baggage Counter')

@section('content')
@php
    $currency = $appSettings?->currency ?? 'PHP';
    $branchQuery = $branch ? ['branch_id' => $branch->id] : [];
@endphp
<div
    x-data="baggageCounter(@js($tags), @js($rates), @js(old('tag_number') ? (int) old('tag_number') : null))"
    x-init="init()"
    class="space-y-4"
>
    <!-- Header -->
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-900">
                <span data-lucide="package" class="h-3.5 w-3.5"></span>
                Baggage drop off
            </div>
            <h1 class="text-xl font-semibold">Baggage Counter</h1>
            <p class="text-sm text-muted">Tap a free tag to store a bag, tap a used tag to release it. Bags are due back in 2 hours (overnight: 24 hours).</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($branches->isNotEmpty())
                <form method="GET">
                    <select name="branch_id" onchange="this.form.submit()" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        @foreach($branches as $option)
                            <option value="{{ $option->id }}" @selected($branch?->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </form>
            @elseif($branch)
                <div class="flex items-center gap-2 rounded-md bg-primary/10 px-3 py-1.5">
                    <span data-lucide="store" class="h-3.5 w-3.5 text-primary"></span>
                    <span class="text-xs font-medium text-primary">{{ $branch->name }}</span>
                </div>
            @endif
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if(! $branch)
        <div class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted dark:border-gray-800">
            Your account is not assigned to a branch yet.
        </div>
    @else
        <!-- Summary -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Free tags</p>
                <p class="text-xl font-semibold text-green-700 dark:text-green-400"><span x-text="freeCount"></span> <span class="text-sm font-medium text-muted">/ <span x-text="tags.length"></span></span></p>
            </div>
            <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Bags stored</p>
                <p class="text-xl font-semibold" x-text="usedCount"></p>
            </div>
            <div class="rounded-lg border bg-white p-3 shadow-sm dark:bg-gray-900" :class="overdueCount > 0 ? 'border-red-200 dark:border-red-900/60' : 'border-border dark:border-gray-800'">
                <p class="text-xs text-muted">Overdue</p>
                <p class="text-xl font-semibold" :class="overdueCount > 0 ? 'text-red-600' : ''" x-text="overdueCount"></p>
            </div>
            <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Sales today</p>
                <p class="text-xl font-semibold text-primary">{{ $currency }} {{ number_format($todayTotal, 2) }}</p>
            </div>
        </div>

        <!-- Rates -->
        <div class="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted">Rates per bag</span>
            @forelse($rates as $rate)
                <span class="inline-flex items-center gap-1.5 rounded-md bg-smoke px-2.5 py-1 text-xs dark:bg-gray-950">
                    <span class="font-semibold text-primary">{{ $currency }} {{ number_format($rate['price'], 2) }}</span>
                    <span class="text-muted">{{ $rate['label'] }} · {{ $rate['hours'] }}h</span>
                </span>
            @empty
                <span class="text-xs text-muted">No active rates. Turn on the "Baggage" services on the Laundry Services page.</span>
            @endforelse
        </div>

        <!-- Tags -->
        <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span data-lucide="tag" class="h-4 w-4 text-muted"></span>
                    <h2 class="text-sm font-semibold">Claim Tags</h2>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-[11px] text-muted">
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-green-500"></span>Free</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full" style="background:#f59e0b"></span>Stored</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500"></span>Overdue</span>
                </div>
            </div>

            <div x-show="tags.length === 0" class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted dark:border-gray-800">
                No claim tags set up for this branch yet.
                @if($canConfigure)
                    Set the number of tags below (e.g. 6 for tags 01 to 06).
                @else
                    Ask your branch manager to set them up.
                @endif
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(9.5rem,1fr));gap:0.5rem;">
                <template x-for="tag in tags" :key="tag.number">
                    <button
                        type="button"
                        @click="tag.deposit ? openRelease(tag) : openDeposit(tag)"
                        class="rounded-lg border-2 p-3 text-left transition hover:shadow-sm"
                        :class="!tag.deposit ? 'border-green-200 bg-green-50 dark:border-green-900/60 dark:bg-green-500/10' : (isOverdue(tag.deposit) ? 'border-red-500 bg-red-50 dark:bg-red-500/10' : 'border-amber-200 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-500/10')"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-2xl font-bold leading-none" x-text="tag.label"></span>
                            <span x-show="!tag.deposit" class="rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-green-800">Free</span>
                            <span x-show="tag.deposit && !isOverdue(tag.deposit)" class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-800">Stored</span>
                            <span x-show="tag.deposit && isOverdue(tag.deposit)" class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-red-800">Overdue</span>
                        </div>
                        <template x-if="!tag.deposit">
                            <p class="mt-3 text-xs font-medium text-green-700 dark:text-green-400">Tap to store a bag</p>
                        </template>
                        <template x-if="tag.deposit">
                            <div class="mt-2 space-y-0.5">
                                <p class="truncate text-xs font-medium" x-text="tag.deposit.size_label"></p>
                                <p class="text-[11px] text-muted" x-text="'In: ' + tag.deposit.deposited_at_label"></p>
                                <p class="text-sm font-bold" style="font-variant-numeric:tabular-nums" :class="isOverdue(tag.deposit) ? 'text-red-600' : 'text-amber-700'" x-text="countdown(tag.deposit)"></p>
                            </div>
                        </template>
                    </button>
                </template>
            </div>
        </div>

        @if($canConfigure)
            <form method="POST" action="{{ route('admin.baggage.settings', $branchQuery) }}" class="flex flex-wrap items-end gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @csrf
                @method('PATCH')
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">Number of claim tags (01 to N)</label>
                    <input type="number" name="baggage_tag_count" min="0" max="{{ \App\Support\BaggageCounter::MAX_TAGS }}" value="{{ old('baggage_tag_count', $branch->baggage_tag_count) }}" class="h-9 w-32 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950" required>
                </div>
                <button type="submit" class="h-9 rounded-md border border-border px-4 text-sm font-medium hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">Save tags</button>
                <p class="text-[11px] text-muted">Match your printed tag cards. Rates can be changed on Laundry Services (category "Baggage Storage").</p>
            </form>
        @endif

        <!-- Today's log -->
        <div class="rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-2 border-b border-border p-3 dark:border-gray-800">
                <span data-lucide="timer" class="h-4 w-4 text-muted"></span>
                <h2 class="text-sm font-semibold">Today's baggage log</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-smoke text-xs text-muted dark:bg-gray-950">
                        <tr>
                            <th class="px-3 py-2 text-left">Tag</th>
                            <th class="px-3 py-2 text-left">Size</th>
                            <th class="px-3 py-2 text-left">Deposited</th>
                            <th class="px-3 py-2 text-left">Released</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                            <th class="px-3 py-2 text-left">Sale</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        @forelse($today as $row)
                            <tr>
                                <td class="px-3 py-2 font-semibold">#{{ $row->tagLabel() }}</td>
                                <td class="px-3 py-2">{{ \App\Support\BaggageCounter::label($row->size) }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row->deposited_at?->format('h:i A') }} <span class="text-xs text-muted">{{ $row->depositor?->name }}</span></td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row->released_at?->format('h:i A') ?? '-' }} <span class="text-xs text-muted">{{ $row->releaser?->name }}</span></td>
                                <td class="px-3 py-2">
                                    @if($row->voided_at)
                                        <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-red-800">Voided</span>
                                    @elseif($row->released_at)
                                        <span class="rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-green-800">Released{{ $row->released_at->greaterThan($row->due_at) ? ' (late)' : '' }}</span>
                                    @elseif($row->isOverdue())
                                        <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-red-800">Overdue</span>
                                    @else
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-800">Stored</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $currency }} {{ number_format((float) $row->amount, 2) }} <span class="text-xs uppercase text-muted">{{ $row->payment_type }}</span></td>
                                <td class="px-3 py-2 text-xs text-muted">
                                    @if($row->jobOrder)
                                        <a href="{{ route('admin.job-orders.show', $row->job_order_id) }}" class="text-primary hover:underline">{{ $row->jobOrder->job_order_number }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-6 text-center text-sm text-muted">No bags stored today.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Deposit modal -->
        <div x-cloak x-show="depositTag" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" @keydown.escape.window="depositTag = null">
            <form method="POST" action="{{ route('admin.baggage.store', $branchQuery) }}" @click.outside="depositTag = null" class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900">
                @csrf
                <input type="hidden" name="tag_number" :value="depositTag?.number">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-semibold">
                        <span data-lucide="package" class="h-5 w-5 text-primary"></span>
                        Store bag · Tag #<span x-text="depositTag?.label"></span>
                    </h2>
                    <button type="button" @click="depositTag = null" class="rounded-lg p-2 hover:bg-smoke dark:hover:bg-gray-800">
                        <span data-lucide="x" class="h-5 w-5"></span>
                    </button>
                </div>

                <p class="mb-2 text-xs font-medium text-muted">Bag size</p>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="rate in rates" :key="rate.size">
                        <label class="flex cursor-pointer flex-col rounded-lg border border-border p-2.5 transition hover:border-primary/50 dark:border-gray-800" :class="size === rate.size ? 'border-primary bg-primary/5' : ''">
                            <input type="radio" name="size" :value="rate.size" x-model="size" class="sr-only" required>
                            <span class="text-sm font-medium" x-text="rate.label"></span>
                            <span class="text-xs font-semibold text-primary">{{ $currency }} <span x-text="money(rate.price)"></span></span>
                            <span class="text-[10px] text-muted" x-text="rate.hours + ' hours max'"></span>
                        </label>
                    </template>
                </div>

                <p class="mb-2 mt-4 text-xs font-medium text-muted">Payment</p>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-2.5 dark:border-gray-800" :class="paymentType === 'cash' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="payment_type" value="cash" x-model="paymentType" class="sr-only">
                        <span data-lucide="wallet" class="h-4 w-4 text-muted"></span>
                        <span class="text-sm font-medium">Cash</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-2.5 dark:border-gray-800" :class="paymentType === 'gcash' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="payment_type" value="gcash" x-model="paymentType" class="sr-only">
                        <span data-lucide="payments" class="h-4 w-4 text-muted"></span>
                        <span class="text-sm font-medium">GCash</span>
                    </label>
                </div>
                <input x-show="paymentType === 'gcash'" name="payment_reference_no" placeholder="GCash reference number..." class="mt-2 h-9 w-full rounded-lg border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <input name="notes" maxlength="255" placeholder="Note (optional), e.g. black luggage" class="mt-2 h-9 w-full rounded-lg border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">

                <div class="mt-4 flex items-center justify-between rounded-lg bg-smoke px-3 py-2 dark:bg-gray-950">
                    <span class="text-sm text-muted">To collect</span>
                    <span class="text-lg font-bold text-primary">{{ $currency }} <span x-text="money(selectedRate?.price || 0)"></span></span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button type="button" @click="depositTag = null" class="h-10 rounded-lg border border-border font-medium hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">Cancel</button>
                    <button type="submit" :disabled="!size" class="h-10 rounded-lg bg-primary font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">Store &amp; Paid</button>
                </div>
            </form>
        </div>

        <!-- Release modal -->
        <div x-cloak x-show="releaseTag" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" @keydown.escape.window="releaseTag = null">
            <div @click.outside="releaseTag = null" class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-semibold">
                        <span data-lucide="package-check" class="h-5 w-5 text-primary"></span>
                        Release bag · Tag #<span x-text="releaseTag?.label"></span>
                    </h2>
                    <button type="button" @click="releaseTag = null" class="rounded-lg p-2 hover:bg-smoke dark:hover:bg-gray-800">
                        <span data-lucide="x" class="h-5 w-5"></span>
                    </button>
                </div>

                <template x-if="releaseTag?.deposit">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-muted">Size</span><span class="font-medium" x-text="releaseTag.deposit.size_label"></span></div>
                        <div class="flex justify-between"><span class="text-muted">Paid</span><span class="font-medium">{{ $currency }} <span x-text="money(releaseTag.deposit.amount)"></span> <span class="text-xs uppercase text-muted" x-text="releaseTag.deposit.payment_type"></span></span></div>
                        <div class="flex justify-between"><span class="text-muted">Deposited</span><span x-text="releaseTag.deposit.deposited_at_label"></span></div>
                        <div class="flex justify-between"><span class="text-muted">Due back</span><span x-text="releaseTag.deposit.due_at_label"></span></div>
                        <div class="flex justify-between"><span class="text-muted">Stored for</span><span class="font-medium" style="font-variant-numeric:tabular-nums" x-text="elapsed(releaseTag.deposit)"></span></div>
                        <div x-show="releaseTag.deposit.notes" class="flex justify-between gap-3"><span class="text-muted">Note</span><span class="text-right" x-text="releaseTag.deposit.notes"></span></div>
                        <div class="rounded-lg px-3 py-2 text-center text-sm font-bold" style="font-variant-numeric:tabular-nums" :class="isOverdue(releaseTag.deposit) ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'" x-text="countdown(releaseTag.deposit)"></div>
                        <p class="text-xs text-muted">Collect the customer's tag copy before releasing the bag.</p>
                    </div>
                </template>

                <form method="POST" :action="releaseTag?.deposit?.release_url" class="mt-4">
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="releaseTag = null" class="h-10 rounded-lg border border-border font-medium hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">Close</button>
                        <button type="submit" class="h-10 rounded-lg bg-primary font-semibold text-white hover:opacity-90">Release bag</button>
                    </div>
                </form>
                <form method="POST" :action="releaseTag?.deposit?.void_url" class="mt-3 text-center">
                    @csrf
                    @method('PATCH')
                    <button
                        type="submit"
                        x-on:click.prevent="Swal.fire({ title: 'Void this deposit?', text: 'Use this only for a mistake (wrong tag or size). The sale is cancelled and the tag becomes free.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Void' }).then((r) => { if (r.isConfirmed) $el.closest('form').submit(); })"
                        class="text-xs font-medium text-red-600 hover:underline"
                    >Void (mistake) - cancels the sale</button>
                </form>
            </div>
        </div>
    @endif
</div>

<script>
function baggageCounter(tags, rates, reopenTag) {
    return {
        tags: tags || [],
        rates: rates || [],
        now: Date.now(),
        depositTag: null,
        releaseTag: null,
        size: '',
        paymentType: 'cash',
        init() {
            setInterval(() => { this.now = Date.now(); }, 1000);
            // After a validation error, reopen the deposit form for the same tag.
            if (reopenTag) {
                const tag = this.tags.find(t => Number(t.number) === Number(reopenTag) && !t.deposit);
                if (tag) this.openDeposit(tag);
            }
            this.refreshIcons();
        },
        get freeCount() { return this.tags.filter(t => !t.deposit).length; },
        get usedCount() { return this.tags.filter(t => t.deposit).length; },
        get overdueCount() { return this.tags.filter(t => t.deposit && this.isOverdue(t.deposit)).length; },
        get selectedRate() { return this.rates.find(r => r.size === this.size); },
        openDeposit(tag) {
            this.releaseTag = null;
            this.depositTag = tag;
            this.size = this.rates.length === 1 ? this.rates[0].size : '';
            this.paymentType = 'cash';
            this.refreshIcons();
        },
        openRelease(tag) {
            this.depositTag = null;
            this.releaseTag = tag;
            this.refreshIcons();
        },
        isOverdue(deposit) {
            return Boolean(deposit?.due_at) && this.now > new Date(deposit.due_at).getTime();
        },
        duration(ms) {
            const total = Math.max(Math.floor(ms / 1000), 0);
            const h = Math.floor(total / 3600);
            const m = Math.floor((total % 3600) / 60);
            const s = total % 60;
            return `${h}h ${String(m).padStart(2, '0')}m ${String(s).padStart(2, '0')}s`;
        },
        countdown(deposit) {
            if (!deposit?.due_at) return '';
            const diff = new Date(deposit.due_at).getTime() - this.now;
            return diff >= 0 ? `${this.duration(diff)} left` : `Overdue ${this.duration(-diff)}`;
        },
        elapsed(deposit) {
            if (!deposit?.deposited_at) return '';
            return this.duration(this.now - new Date(deposit.deposited_at).getTime());
        },
        money(value) {
            return Number(value || 0).toFixed(2);
        },
        refreshIcons() {
            this.$nextTick(() => {
                if (typeof window.renderLucideIcons === 'function') window.renderLucideIcons();
            });
        },
    };
}
</script>
@endsection
