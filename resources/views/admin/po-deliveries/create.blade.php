@extends('layouts.app')

@section('page_title', 'New Delivery Receipt')

@section('content')
@php($currency = $settings->currency ?? 'PHP')
<div
    x-data="{
        orders: @js($purchaseOrders),
        qty: {},
        init() {
            this.orders.forEach(order => {
                this.qty[order.id] = {};
                order.lines.forEach(line => this.qty[order.id][line.key] = @js(old('quantities')) ?.[order.id]?.[line.key] ?? '');
            });
            this.$nextTick(() => document.querySelector('[data-focus-order]')?.scrollIntoView({ block: 'center' }));
        },
        fill(order) { order.lines.forEach(line => this.qty[order.id][line.key] = line.remaining > 0 ? line.remaining : ''); },
        clear(order) { order.lines.forEach(line => this.qty[order.id][line.key] = ''); },
        get totals() {
            let pieces = 0, amount = 0;
            this.orders.forEach(order => order.lines.forEach(line => {
                const value = Number(this.qty[order.id]?.[line.key] || 0);
                if (!(line.service_category === 'delivery' || String(line.description).toLowerCase().startsWith('delivery'))) pieces += value;
                amount += value * Number(line.unit_price);
            }));
            return { pieces, amount };
        },
        over(order, line) { return Number(this.qty[order.id]?.[line.key] || 0) > Number(line.remaining) + 0.001; },
        get hasOver() { return this.orders.some(order => order.lines.some(line => this.over(order, line))); },
        money(value) { return Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        num(value) { return Number(value || 0).toLocaleString('en-PH', { maximumFractionDigits: 2 }); },
    }"
    class="space-y-4"
>
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="truck" class="h-3.5 w-3.5"></span>
                PO delivery
            </div>
            <h1 class="text-xl font-semibold">New Delivery Receipt</h1>
            <p class="text-sm text-muted">Record what goes out on this trip. Deliver all of a job order, or only part of it and the rest later.</p>
        </div>
        <a href="{{ route('admin.po-deliveries.index') }}" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900">
            <span data-lucide="arrow-left" class="h-4 w-4"></span>
            Delivery Receipts
        </a>
    </div>

    <form method="GET" action="{{ route('admin.po-deliveries.create') }}" class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <label class="block max-w-md">
            <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-muted">PO Customer</span>
            <select name="customer_id" onchange="this.form.submit()" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">Select a PO customer...</option>
                @foreach($customers as $option)
                    <option value="{{ $option->id }}" @selected($customer && (int) $customer->id === (int) $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </label>
    </form>

    @if(! $customer)
        <div class="rounded-lg border border-dashed border-border bg-white p-6 text-center dark:border-gray-800 dark:bg-gray-900">
            <span data-lucide="truck" class="mx-auto mb-2 h-6 w-6 text-muted"></span>
            <p class="text-sm font-medium">Choose a PO customer</p>
            <p class="mt-1 text-xs text-muted">Their job orders that still have laundry to deliver will be listed.</p>
        </div>
    @elseif($purchaseOrders->isEmpty())
        <div class="rounded-lg border border-dashed border-border bg-white p-6 text-center dark:border-gray-800 dark:bg-gray-900">
            <span data-lucide="check-circle-2" class="mx-auto mb-2 h-6 w-6 text-green-600"></span>
            <p class="text-sm font-medium">Everything for {{ $customer->name }} has been delivered.</p>
        </div>
    @else
        <form method="POST" action="{{ route('admin.po-deliveries.store') }}" class="space-y-4" @submit="if (hasOver || totals.pieces + totals.amount <= 0) { $event.preventDefault(); }">
            @csrf
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">

            <div class="grid gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-3">
                <label>
                    <span class="mb-1 block text-sm font-medium">DR #</span>
                    <input name="dr_number" value="{{ old('dr_number', $nextDrNumber) }}" maxlength="100" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    <span class="mt-1 block text-xs text-muted">Next number is filled in. Change it to match a printed DR booklet.</span>
                    @error('dr_number')<span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>@enderror
                </label>
                <label>
                    <span class="mb-1 block text-sm font-medium">Delivery date</span>
                    <input type="date" name="delivered_on" value="{{ old('delivered_on', today()->toDateString()) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    <span class="mt-1 block text-xs text-muted">The Statement of Account bills this DR on this date.</span>
                </label>
                <label>
                    <span class="mb-1 block text-sm font-medium">Notes</span>
                    <input name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="Optional" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                </label>
            </div>

            @error('quantities')
                <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-300">{{ $message }}</p>
            @enderror
            @foreach($errors->getMessages() as $key => $messages)
                @if(str_starts_with($key, 'quantities.'))
                    <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-300">{{ $messages[0] }}</p>
                @endif
            @endforeach

            <template x-for="order in orders" :key="order.id">
                <section class="overflow-hidden rounded-lg border bg-white shadow-sm dark:bg-gray-900" :class="order.focus ? 'border-primary ring-1 ring-primary' : 'border-border dark:border-gray-800'" :data-focus-order="order.focus ? true : null">
                    <div class="flex flex-col gap-2 border-b border-border p-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold"><span x-text="order.job_order_number"></span> <span class="font-normal text-muted" x-text="'· ' + order.po_number"></span></p>
                            <p class="text-xs text-muted">
                                <span x-text="'Ordered ' + order.order_date"></span>
                                <span x-show="order.tag_number" x-text="' · Tag #' + order.tag_number"></span>
                                <span x-text="' · ' + String(order.status || '').replaceAll('_', ' ')" class="capitalize"></span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="fill(order)" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-xs font-semibold hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                <span data-lucide="check-check" class="h-3.5 w-3.5"></span>
                                Deliver all remaining
                            </button>
                            <button type="button" @click="clear(order)" class="h-8 rounded-md border border-border px-3 text-xs font-semibold text-muted hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">Clear</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-smoke text-xs uppercase text-muted dark:bg-gray-950">
                                <tr>
                                    <th class="px-3 py-2">Item</th>
                                    <th class="px-3 py-2 text-right">Price</th>
                                    <th class="px-3 py-2 text-right">Ordered</th>
                                    <th class="px-3 py-2 text-right">Delivered</th>
                                    <th class="px-3 py-2 text-right">Remaining</th>
                                    <th class="w-36 px-3 py-2 text-right">Deliver now</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border dark:divide-gray-800">
                                <template x-for="line in order.lines" :key="line.key">
                                    <tr :class="line.remaining <= 0 ? 'text-muted' : ''">
                                        <td class="px-3 py-2 font-medium" x-text="line.description"></td>
                                        <td class="px-3 py-2 text-right" x-text="money(line.unit_price)"></td>
                                        <td class="px-3 py-2 text-right" x-text="num(line.ordered)"></td>
                                        <td class="px-3 py-2 text-right" x-text="num(line.delivered)"></td>
                                        <td class="px-3 py-2 text-right font-semibold" x-text="num(line.remaining)"></td>
                                        <td class="px-3 py-2 text-right">
                                            <input
                                                type="number"
                                                step="any"
                                                min="0"
                                                :max="line.remaining"
                                                :name="`quantities[${order.id}][${line.key}]`"
                                                x-model="qty[order.id][line.key]"
                                                :disabled="line.remaining <= 0"
                                                placeholder="0"
                                                class="h-8 w-24 rounded-md border px-2 text-right text-sm dark:bg-gray-950"
                                                :class="over(order, line) ? 'border-2 border-red-500 bg-red-50 text-red-700' : 'border-border dark:border-gray-700'"
                                            >
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>

            <div class="sticky bottom-0 flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-lg dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm">
                    <p><span class="font-semibold" x-text="num(totals.pieces)"></span> piece(s) / load(s) on this DR</p>
                    <p class="text-muted">About {{ $currency }} <span x-text="money(totals.amount)"></span> before any order discount</p>
                    <p x-show="hasOver" class="font-semibold text-red-600">Some quantities are more than what is left to deliver.</p>
                </div>
                <button type="submit" :disabled="hasOver || totals.pieces + totals.amount <= 0" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                    <span data-lucide="save" class="h-4 w-4"></span>
                    Save Delivery Receipt
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
