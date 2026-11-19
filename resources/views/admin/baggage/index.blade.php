@extends('layouts.app')

@section('page_title', 'Baggage Counter')

@section('content')
@php
    $currency = $appSettings?->currency ?? 'PHP';
    $branchQuery = $branch ? ['branch_id' => $branch->id] : [];
    $servicesUrl = auth()->user()->hasMenuAccess('services') ? route('admin.services.index') : null;
    $todayActive = $today->whereNull('voided_at');
    $todayReleased = $todayActive->whereNotNull('released_at')->count();
@endphp

{{-- Page-scoped styles: built on the app theme variables so no asset rebuild is needed. --}}
<style>
    .bc-card { background:#fff; border:1px solid var(--color-border); border-radius:.75rem; box-shadow:0 1px 2px rgb(0 0 0 / .04); }
    .dark .bc-card { background:#111827; border-color:#1f2937; }
    .bc-muted { color:var(--color-muted); }
    .bc-kpis { display:grid; gap:.75rem; grid-template-columns:repeat(2,minmax(0,1fr)); }
    @media (min-width:1024px) { .bc-kpis { grid-template-columns:repeat(4,minmax(0,1fr)); } }
    .bc-kpi { padding:.9rem 1rem; display:flex; gap:.8rem; align-items:flex-start; }
    .bc-kpi-icon { flex:none; width:2.25rem; height:2.25rem; border-radius:.6rem; display:flex; align-items:center; justify-content:center; }
    .bc-kpi-value { font-size:1.5rem; font-weight:700; line-height:1.15; font-variant-numeric:tabular-nums; }
    .bc-bar { height:.375rem; border-radius:999px; background:var(--color-smoke); overflow:hidden; }
    .dark .bc-bar { background:#1f2937; }
    .bc-bar > span { display:block; height:100%; border-radius:999px; transition:width .6s ease; }
    .bc-tone-free { --bc:#16a34a; --bc-soft:#f0fdf4; --bc-line:#bbf7d0; }
    .bc-tone-stored { --bc:#d97706; --bc-soft:#fffbeb; --bc-line:#fde68a; }
    .bc-tone-overdue { --bc:#dc2626; --bc-soft:#fef2f2; --bc-line:#fecaca; }
    .bc-tone-brand { --bc:var(--color-primary); --bc-soft:color-mix(in srgb, var(--color-primary) 10%, transparent); --bc-line:color-mix(in srgb, var(--color-primary) 30%, transparent); }
    .dark .bc-tone-free { --bc-soft:rgb(22 163 74 / .12); --bc-line:rgb(22 163 74 / .35); --bc:#4ade80; }
    .dark .bc-tone-stored { --bc-soft:rgb(217 119 6 / .12); --bc-line:rgb(217 119 6 / .35); --bc:#fbbf24; }
    .dark .bc-tone-overdue { --bc-soft:rgb(220 38 38 / .14); --bc-line:rgb(220 38 38 / .45); --bc:#f87171; }
    .bc-soft { background:var(--bc-soft); color:var(--bc); }
    .bc-fill { background:var(--bc); }
    .bc-pill { display:inline-flex; align-items:center; gap:.3rem; border-radius:999px; padding:.15rem .55rem; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; background:var(--bc-soft); color:var(--bc); }
    .bc-dot { width:.45rem; height:.45rem; border-radius:999px; background:var(--bc); }
    .bc-grid { display:grid; gap:.65rem; grid-template-columns:repeat(auto-fill,minmax(10.5rem,1fr)); }
    .bc-tag { position:relative; text-align:left; border-radius:.8rem; border:1px solid var(--bc-line); background:var(--bc-soft); padding:.85rem .9rem .8rem; transition:transform .12s ease, box-shadow .12s ease; min-height:8.4rem; display:flex; flex-direction:column; }
    .bc-tag:hover { transform:translateY(-1px); box-shadow:0 6px 16px -8px rgb(0 0 0 / .25); }
    .bc-tag:focus-visible { outline:2px solid var(--color-primary); outline-offset:2px; }
    .bc-tag-free { background:transparent; border-style:dashed; border-width:2px; }
    .bc-tag-free:hover { background:var(--bc-soft); }
    .bc-tag-number { font-size:1.75rem; font-weight:800; line-height:1; letter-spacing:-.02em; font-variant-numeric:tabular-nums; }
    .bc-tag-overdue { box-shadow:0 0 0 2px var(--bc) inset; animation:bc-pulse 2s ease-in-out infinite; }
    @keyframes bc-pulse { 0%,100% { box-shadow:0 0 0 2px var(--bc) inset; } 50% { box-shadow:0 0 0 2px var(--bc) inset, 0 0 0 4px var(--bc-line); } }
    @media (prefers-reduced-motion:reduce) { .bc-tag-overdue { animation:none; } .bc-bar > span { transition:none; } }
    .bc-seg { display:inline-flex; gap:.25rem; padding:.25rem; border-radius:.6rem; background:var(--color-smoke); }
    .dark .bc-seg { background:#0b1220; }
    .bc-seg button { display:inline-flex; align-items:center; gap:.4rem; border-radius:.45rem; padding:.35rem .7rem; font-size:.8rem; font-weight:600; color:var(--color-muted); }
    .bc-seg button[aria-pressed="true"] { background:#fff; color:inherit; box-shadow:0 1px 2px rgb(0 0 0 / .08); }
    .dark .bc-seg button[aria-pressed="true"] { background:#1f2937; }
    .bc-count { font-size:.68rem; font-weight:700; border-radius:999px; padding:0 .4rem; background:rgb(0 0 0 / .06); }
    .dark .bc-count { background:rgb(255 255 255 / .08); }
    .bc-input { height:2.5rem; width:100%; border-radius:.6rem; border:1px solid var(--color-border); background:#fff; padding:0 .8rem; font-size:.9rem; }
    .dark .bc-input { background:#030712; border-color:#374151; }
    .bc-input:focus { outline:2px solid color-mix(in srgb, var(--color-primary) 40%, transparent); border-color:var(--color-primary); }
    .bc-btn { display:inline-flex; align-items:center; justify-content:center; gap:.45rem; height:2.5rem; border-radius:.6rem; padding:0 1rem; font-size:.875rem; font-weight:600; transition:opacity .12s, background .12s; }
    .bc-btn-primary { background:var(--color-primary); color:#fff; }
    .bc-btn-primary:hover { opacity:.92; }
    .bc-btn-primary:disabled { opacity:.45; cursor:not-allowed; }
    .bc-btn-ghost { border:1px solid var(--color-border); background:#fff; }
    .bc-btn-ghost:hover { background:var(--color-smoke); }
    .dark .bc-btn-ghost { background:#111827; border-color:#374151; }
    .dark .bc-btn-ghost:hover { background:#1f2937; }
    .bc-choice { display:flex; flex-direction:column; gap:.1rem; cursor:pointer; border-radius:.7rem; border:1.5px solid var(--color-border); padding:.7rem .8rem; transition:border-color .12s, background .12s; }
    .dark .bc-choice { border-color:#374151; }
    .bc-choice:hover { border-color:color-mix(in srgb, var(--color-primary) 55%, transparent); }
    .bc-choice[data-active="true"] { border-color:var(--color-primary); background:color-mix(in srgb, var(--color-primary) 8%, transparent); }
    .bc-choice:has(input:focus-visible) { outline:2px solid var(--color-primary); outline-offset:2px; }
    .bc-modal-wrap { position:fixed; inset:0; z-index:50; display:flex; align-items:flex-end; justify-content:center; background:rgb(15 23 42 / .55); padding:0; }
    @media (min-width:640px) { .bc-modal-wrap { align-items:center; padding:1rem; } }
    .bc-modal { width:100%; max-width:30rem; max-height:92vh; overflow-y:auto; background:#fff; border-radius:1rem 1rem 0 0; box-shadow:0 25px 50px -12px rgb(0 0 0 / .35); }
    @media (min-width:640px) { .bc-modal { border-radius:1rem; } }
    .dark .bc-modal { background:#111827; }
    .bc-modal-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:1rem 1.25rem; border-bottom:1px solid var(--color-border); }
    .dark .bc-modal-head { border-color:#1f2937; }
    .bc-modal-body { padding:1.1rem 1.25rem; }
    .bc-modal-foot { display:grid; grid-template-columns:1fr 1.4fr; gap:.6rem; padding:0 1.25rem 1.25rem; }
    .bc-tag-badge { display:inline-flex; align-items:center; justify-content:center; min-width:3rem; height:3rem; padding:0 .5rem; border-radius:.75rem; font-size:1.35rem; font-weight:800; font-variant-numeric:tabular-nums; }
    .bc-kv { display:flex; justify-content:space-between; gap:1rem; padding:.45rem 0; font-size:.875rem; border-bottom:1px dashed var(--color-border); }
    .dark .bc-kv { border-color:#1f2937; }
    .bc-kv:last-child { border-bottom:0; }
    .bc-table th { font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; color:var(--color-muted); font-weight:600; padding:.6rem .9rem; text-align:left; white-space:nowrap; }
    .bc-table td { padding:.65rem .9rem; font-size:.85rem; vertical-align:middle; }
    .bc-table tbody tr { border-top:1px solid var(--color-border); }
    .dark .bc-table tbody tr { border-color:#1f2937; }
    .bc-steps { display:grid; gap:.75rem; grid-template-columns:1fr; }
    @media (min-width:768px) { .bc-steps { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    .bc-step-no { width:1.6rem; height:1.6rem; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; background:var(--color-primary); color:#fff; flex:none; }
    .bc-chip { height:2.5rem; min-width:3.25rem; border-radius:.6rem; border:1.5px solid var(--color-border); font-weight:700; font-variant-numeric:tabular-nums; }
    .dark .bc-chip { border-color:#374151; }
    .bc-chip[aria-pressed="true"] { border-color:var(--color-primary); background:color-mix(in srgb, var(--color-primary) 10%, transparent); color:var(--color-primary); }
    .bc-tnum { font-variant-numeric:tabular-nums; }
    .bc-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; }
    .bc-find { position:relative; width:11rem; }
    .bc-find .bc-input { padding-left:2.1rem; height:2.25rem; font-size:.85rem; }
    .bc-find > span { position:absolute; left:.7rem; top:50%; transform:translateY(-50%); }
    .bc-setup { padding:1.5rem; }
    @media (min-width:640px) { .bc-setup { padding:2rem; } }
    /* Utilities this page uses that are not in the precompiled stylesheet. */
    .flex-none { flex:none; }
    .mt-6 { margin-top:1.5rem; }
    .mt-auto { margin-top:auto; }
    .normal-case { text-transform:none; }
    .space-y-5 > :not(:last-child) { margin-bottom:1.25rem; }
    .bc-kbd { font-size:.65rem; font-weight:600; border:1px solid var(--color-border); border-bottom-width:2px; border-radius:.3rem; padding:0 .3rem; color:var(--color-muted); }
</style>

<div
    x-data="baggageCounter({
        tags: @js($tags),
        rates: @js($rates),
        tickets: @js($tickets),
        heldClaimTags: @js($heldClaimTags),
        old: @js(old('cubicles') ? [
            'customer_name' => old('customer_name'),
            'customer_phone' => old('customer_phone'),
            'cubicles' => array_values((array) old('cubicles')),
            'payment_type' => old('payment_type', 'cash'),
            'notes' => old('notes'),
        ] : null),
        tagCount: @js((int) ($branch?->baggage_tag_count ?? 0)),
        settingsOpen: @js($errors->has('baggage_tag_count')),
    })"
    x-init="init()"
    @keydown.window="hotkey($event)"
    class="space-y-4"
>
    {{-- ===== Header ===== --}}
    <div class="bc-card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="bc-kpi-icon bc-tone-brand bc-soft" style="width:2.75rem;height:2.75rem;">
                <span data-lucide="package" class="h-5 w-5"></span>
            </div>
            <div>
                <h1 class="text-xl font-semibold leading-tight">Baggage Counter</h1>
                <p class="text-sm bc-muted">
                    @if($branch){{ $branch->name }} · @endif
                    <span class="bc-tnum" x-text="clock"></span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($branches->isNotEmpty())
                <form method="GET">
                    <label class="sr-only" for="bc-branch">Branch</label>
                    <select id="bc-branch" name="branch_id" onchange="this.form.submit()" class="bc-input" style="height:2.5rem;width:auto;min-width:12rem;">
                        @foreach($branches as $option)
                            <option value="{{ $option->id }}" @selected($branch?->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            @if($branch && $canConfigure)
                <button type="button" @click="openSettings()" class="bc-btn bc-btn-ghost" title="Counter settings">
                    <span data-lucide="settings" class="h-4 w-4"></span>
                    <span>Settings</span>
                </button>
            @endif
            @if($branch)
                <button type="button" x-show="tags.length > 0" @click="storeNext()" :disabled="freeCount === 0" class="bc-btn bc-btn-primary" title="Store a bag in the next free cubicle (N)">
                    <span data-lucide="plus" class="h-4 w-4"></span>
                    <span>Store a bag</span>
                    <span class="bc-kbd" style="color:#fff;border-color:rgb(255 255 255 / .5);">N</span>
                </button>
            @endif
        </div>
    </div>

    @if($errors->any() && ! old('cubicles') && ! $errors->has('baggage_tag_count'))
        <div class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700" role="alert">
            <span data-lucide="alertTriangle" class="mt-0.5 h-4 w-4 flex-none"></span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if(! $branch)
        <div class="bc-card p-10 text-center">
            <p class="font-semibold">No branch assigned</p>
            <p class="mt-1 text-sm bc-muted">Your account is not assigned to a branch yet. Ask an administrator to assign one.</p>
        </div>
    @else

    {{-- ===== First-time setup ===== --}}
    <template x-if="tags.length === 0">
        <div class="bc-card bc-setup">
            <div class="mx-auto max-w-3xl text-center">
                <div class="bc-kpi-icon bc-tone-brand bc-soft mx-auto" style="width:3.5rem;height:3.5rem;border-radius:1rem;">
                    <span data-lucide="tag" class="h-7 w-7"></span>
                </div>
                <h2 class="mt-4 text-lg font-semibold">Set up the Baggage Counter for {{ $branch->name }}</h2>
                <p class="mx-auto mt-1 max-w-xl text-sm bc-muted">Customers leave bags in numbered cubicles, pay, and keep one claim tag (the number of their first cubicle). Tell the system how many cubicles this branch has to get started.</p>
            </div>

            <div class="bc-steps mx-auto mt-6 max-w-3xl">
                @foreach([
                    ['Set your cubicles', 'One numbered tag card per cubicle (e.g. 6 cubicles = 01 to 06).'],
                    ['Check the rates', 'Small, Medium, Large/XL and Overnight. Prices are editable per branch.'],
                    ['Start storing bags', 'Tap a free cubicle, enter the customer, add more cubicles if needed, collect Cash or GCash.'],
                ] as $i => [$title, $text])
                    <div class="flex gap-3 rounded-lg p-3" style="background:var(--color-smoke);">
                        <span class="bc-step-no">{{ $i + 1 }}</span>
                        <div>
                            <p class="text-sm font-semibold">{{ $title }}</p>
                            <p class="text-xs bc-muted">{{ $text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($canConfigure)
                <form method="POST" action="{{ route('admin.baggage.settings', $branchQuery) }}" class="mx-auto mt-6 max-w-3xl rounded-xl border border-border p-4 dark:border-gray-800">
                    @csrf
                    @method('PATCH')
                    <p class="text-sm font-semibold">How many cubicles (claim tags) does this branch have?</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <template x-for="preset in [6, 10, 12, 20]" :key="preset">
                            <button type="button" class="bc-chip" :aria-pressed="setupCount === preset" @click="setupCount = preset" x-text="preset"></button>
                        </template>
                        <span class="px-1 text-sm bc-muted">or</span>
                        <input type="number" name="baggage_tag_count" x-model.number="setupCount" min="1" max="{{ \App\Support\BaggageCounter::MAX_TAGS }}" class="bc-input bc-tnum" style="width:6rem;" aria-label="Number of claim tags" required>
                        <button type="submit" class="bc-btn bc-btn-primary" :disabled="!(setupCount >= 1)">
                            <span data-lucide="check" class="h-4 w-4"></span>
                            <span x-text="setupCount >= 1 ? `Create cubicles 01 to ${String(setupCount).padStart(2, '0')}` : 'Create cubicles'"></span>
                        </button>
                    </div>
                    @error('baggage_tag_count')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                </form>
            @else
                <div class="mx-auto mt-6 max-w-xl rounded-lg p-4 text-center text-sm" style="background:var(--color-smoke);">
                    Ask your <strong>branch manager</strong> to set the number of claim tags for this branch.
                </div>
            @endif

            <div class="mx-auto mt-6 max-w-3xl">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider bc-muted">Rates per bag</p>
                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                    @forelse($rates as $rate)
                        <div class="rounded-lg border border-border p-3 dark:border-gray-800">
                            <p class="text-sm font-medium">{{ $rate['label'] }}</p>
                            <p class="text-base font-bold text-primary">{{ $currency }} {{ number_format($rate['price'], 2) }}</p>
                            <p class="text-xs bc-muted">Up to {{ $rate['hours'] }} hours</p>
                        </div>
                    @empty
                        <p class="text-sm bc-muted">No active rates. Turn on the "Baggage" services on the Laundry Services page.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </template>

    {{-- ===== Working counter ===== --}}
    <template x-if="tags.length > 0">
        <div class="space-y-4">
            {{-- Overdue alert --}}
            <div x-show="overdueCount > 0" x-transition class="bc-tone-overdue flex flex-col gap-3 rounded-xl p-3 sm:flex-row sm:items-center sm:justify-between" style="background:var(--bc-soft);border:1px solid var(--bc-line);" role="alert">
                <div class="flex items-center gap-2" style="color:var(--bc);">
                    <span data-lucide="alertTriangle" class="h-5 w-5 flex-none"></span>
                    <p class="text-sm font-semibold"><span x-text="overdueCount"></span> <span x-text="overdueCount === 1 ? 'bag is' : 'bags are'"></span> past the time limit. Check with the customer.</p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="tag in overdueTags" :key="tag.number">
                        <button type="button" @click="openRelease(tag)" class="bc-pill" style="background:var(--bc);color:#fff;font-size:.75rem;padding:.3rem .7rem;" x-text="'#' + tag.label + ' · ' + overdueShort(tag.deposit)"></button>
                    </template>
                </div>
            </div>

            {{-- KPIs --}}
            <div class="bc-kpis">
                <div class="bc-card bc-kpi bc-tone-free">
                    <div class="bc-kpi-icon bc-soft"><span data-lucide="tag" class="h-4 w-4"></span></div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium bc-muted">Free cubicles</p>
                        <p class="bc-kpi-value"><span x-text="freeCount"></span><span class="text-sm font-medium bc-muted"> / <span x-text="tags.length"></span></span></p>
                        <div class="bc-bar mt-2"><span class="bc-fill" :style="`width:${tags.length ? (usedCount / tags.length) * 100 : 0}%`"></span></div>
                        <p class="mt-1 text-[11px] bc-muted"><span x-text="Math.round(tags.length ? (usedCount / tags.length) * 100 : 0)"></span>% in use</p>
                    </div>
                </div>
                <div class="bc-card bc-kpi bc-tone-stored">
                    <div class="bc-kpi-icon bc-soft"><span data-lucide="package" class="h-4 w-4"></span></div>
                    <div>
                        <p class="text-xs font-medium bc-muted">Cubicles in use</p>
                        <p class="bc-kpi-value"><span x-text="usedCount"></span><span class="text-sm font-medium bc-muted"> · <span x-text="customerCount"></span> <span x-text="customerCount === 1 ? 'customer' : 'customers'"></span></span></p>
                        <p class="text-[11px] bc-muted" x-text="nextDue ? 'Next due ' + nextDue : 'None waiting'"></p>
                    </div>
                </div>
                <div class="bc-card bc-kpi" :class="overdueCount > 0 ? 'bc-tone-overdue' : 'bc-tone-free'">
                    <div class="bc-kpi-icon bc-soft"><span data-lucide="timer" class="h-4 w-4"></span></div>
                    <div>
                        <p class="text-xs font-medium bc-muted">Overdue</p>
                        <p class="bc-kpi-value" :style="overdueCount > 0 ? 'color:var(--bc)' : ''" x-text="overdueCount"></p>
                        <p class="text-[11px] bc-muted" x-text="overdueCount > 0 ? 'Past the time limit' : 'All on time'"></p>
                    </div>
                </div>
                <div class="bc-card bc-kpi bc-tone-brand">
                    <div class="bc-kpi-icon bc-soft"><span data-lucide="wallet" class="h-4 w-4"></span></div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium bc-muted">Sales today</p>
                        <p class="bc-kpi-value text-primary">{{ $currency }} {{ number_format($todayTotal, 2) }}</p>
                        <p class="text-[11px] bc-muted">Cash {{ number_format($todayCash, 2) }} · GCash {{ number_format($todayGcash, 2) }}</p>
                        <p class="text-[11px] bc-muted">{{ $todayActive->count() }} {{ \Illuminate\Support\Str::plural('bag', $todayActive->count()) }} · {{ $todayReleased }} released</p>
                    </div>
                </div>
            </div>

            {{-- Tag board --}}
            <div class="bc-card p-4">
                <div class="bc-toolbar mb-4">
                    <div class="bc-seg" role="group" aria-label="Filter tags">
                        <template x-for="option in filters" :key="option.key">
                            <button type="button" :aria-pressed="filter === option.key" @click="filter = option.key">
                                <span x-text="option.label"></span>
                                <span class="bc-count bc-tnum" x-text="option.count()"></span>
                            </button>
                        </template>
                    </div>
                    <form class="bc-find" @submit.prevent="findTag()">
                        <span data-lucide="search" class="h-4 w-4 bc-muted"></span>
                        <input x-ref="find" x-model="findValue" class="bc-input" placeholder="Tag # or name" aria-label="Open a claim tag, cubicle or customer">
                    </form>
                </div>

                <div class="bc-grid">
                    <template x-for="tag in visibleTags" :key="tag.number">
                        <button
                            type="button"
                            @click="tag.deposit ? openRelease(tag) : openDeposit(tag)"
                            class="bc-tag"
                            :class="{
                                'bc-tone-free bc-tag-free': !tag.deposit,
                                'bc-tone-stored': tag.deposit && !isOverdue(tag.deposit),
                                'bc-tone-overdue bc-tag-overdue': tag.deposit && isOverdue(tag.deposit),
                            }"
                            :aria-label="tag.deposit ? `Cubicle ${tag.label}, ${statusOf(tag)}, claim tag ${tag.deposit.claim_label}, open to claim` : `Cubicle ${tag.label}, free, store a bag`"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span class="bc-tag-number" x-text="tag.label"></span>
                                <span class="bc-pill"><span class="bc-dot"></span><span x-text="statusOf(tag)"></span></span>
                            </div>

                            <template x-if="!tag.deposit">
                                <div class="mt-auto pt-4">
                                    <p x-show="isHeldTag(tag.number)" class="text-[11px] bc-muted" x-text="'Tag card #' + tag.label + ' is out'"></p>
                                    <div class="flex items-center gap-1.5 text-sm font-semibold" style="color:var(--bc);">
                                        <span data-lucide="plus" class="h-4 w-4"></span>
                                        Store a bag
                                    </div>
                                </div>
                            </template>

                            <template x-if="tag.deposit">
                                <div class="mt-auto pt-3">
                                    <p class="truncate text-xs font-semibold" x-text="tag.deposit.customer_name || 'Walk-in'"></p>
                                    <p class="truncate text-[11px] font-semibold" style="color:var(--bc);" x-text="'Tag #' + tag.deposit.claim_label + (ticketCount(tag) > 1 ? ' · ' + ticketCount(tag) + ' cubicles' : '')"></p>
                                    <p class="truncate text-[11px] bc-muted" x-text="tag.deposit.size_label"></p>
                                    <p class="text-[11px] bc-muted" x-text="'In ' + tag.deposit.deposited_at_label"></p>
                                    <p class="mt-1 text-sm font-bold bc-tnum" style="color:var(--bc);" x-text="countdown(tag.deposit)"></p>
                                    <div class="bc-bar mt-1.5"><span class="bc-fill" :style="`width:${progress(tag.deposit)}%`"></span></div>
                                </div>
                            </template>
                        </button>
                    </template>
                </div>

                <div x-show="visibleTags.length === 0" class="rounded-lg border border-dashed border-border p-8 text-center text-sm bc-muted dark:border-gray-800">
                    <span x-text="filter === 'overdue' ? 'No overdue bags. Everything is on time.' : (filter === 'stored' ? 'No bags stored right now.' : 'No free cubicles right now. Every cubicle is in use.')"></span>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-border pt-3 text-[11px] bc-muted dark:border-gray-800">
                    <span class="inline-flex items-center gap-1.5"><span class="bc-kbd">N</span> store in next free cubicle</span>
                    <span class="inline-flex items-center gap-1.5"><span class="bc-kbd">/</span> find a tag or customer</span>
                    <span class="inline-flex items-center gap-1.5"><span class="bc-kbd">Esc</span> close</span>
                    <span class="ml-auto">Rates:
                        @foreach($rates as $rate)
                            <span class="whitespace-nowrap">{{ $rate['label'] }} <strong class="text-primary">{{ number_format($rate['price'], 0) }}</strong>{{ $loop->last ? '' : ' ·' }}</span>
                        @endforeach
                    </span>
                </div>
            </div>
        </div>
    </template>

    {{-- ===== Today's log ===== --}}
    <div class="bc-card overflow-hidden" x-show="tags.length > 0 || {{ $today->isNotEmpty() ? 'true' : 'false' }}">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border p-4 dark:border-gray-800">
            <div>
                <h2 class="text-sm font-semibold">Today's log</h2>
                <p class="text-xs bc-muted">{{ $today->count() }} {{ \Illuminate\Support\Str::plural('entry', $today->count()) }} · {{ now()->format('l, M d') }}</p>
            </div>
            @if(auth()->user()->hasMenuAccess('job_orders'))
                <a href="{{ route('dashboard', ['tab' => 'baggage']) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                    Full history &amp; analytics <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>
        @if($today->isEmpty())
            <div class="p-10 text-center">
                <p class="text-sm font-medium">No bags stored yet today</p>
                <p class="mt-1 text-xs bc-muted">Each bag you store shows up here with its time in, time out and payment.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="bc-table w-full">
                    <thead style="background:var(--color-smoke);" class="dark:bg-gray-950">
                        <tr>
                            <th>Tag</th>
                            <th>Cubicle</th>
                            <th>Customer</th>
                            <th>Size</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>Status</th>
                            <th style="text-align:right;">Paid</th>
                            <th>Sale</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($today as $row)
                            @php
                                [$tone, $status] = match (true) {
                                    (bool) $row->voided_at => ['bc-tone-overdue', 'Voided'],
                                    (bool) $row->released_at && $row->released_at->greaterThan($row->due_at) => ['bc-tone-stored', 'Claimed late'],
                                    (bool) $row->released_at => ['bc-tone-free', 'Claimed'],
                                    $row->isOverdue() => ['bc-tone-overdue', 'Overdue'],
                                    default => ['bc-tone-stored', 'Stored'],
                                };
                            @endphp
                            <tr @if($row->voided_at) style="opacity:.6;" @endif>
                                <td class="font-bold bc-tnum">#{{ $row->claimLabel() }}</td>
                                <td class="bc-tnum">#{{ $row->tagLabel() }}</td>
                                <td>{{ $row->customer_name ?? '—' }}@if($row->customer_phone)<span class="block text-[11px] bc-muted">{{ $row->customer_phone }}</span>@endif</td>
                                <td>{{ \App\Support\BaggageCounter::label($row->size) }}</td>
                                <td class="whitespace-nowrap bc-tnum">{{ $row->deposited_at?->format('h:i A') }}<span class="block text-[11px] bc-muted">{{ $row->depositor?->name }}</span></td>
                                <td class="whitespace-nowrap bc-tnum">{{ $row->released_at?->format('h:i A') ?? '—' }}<span class="block text-[11px] bc-muted">{{ $row->releaser?->name }}</span></td>
                                <td><span class="bc-pill {{ $tone }}"><span class="bc-dot"></span>{{ $status }}</span></td>
                                <td class="whitespace-nowrap text-right bc-tnum" @if($row->voided_at) style="text-decoration:line-through;" @endif>
                                    {{ $currency }} {{ number_format((float) $row->amount, 2) }}
                                    <span class="block text-[11px] font-semibold uppercase bc-muted">{{ $row->payment_type === 'gcash' ? 'GCash' : 'Cash' }}</span>
                                </td>
                                <td class="text-xs">
                                    @if($row->jobOrder)
                                        <a href="{{ route('admin.job-orders.show', $row->job_order_id) }}" class="font-medium text-primary hover:underline">{{ $row->jobOrder->job_order_number }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ===== Store baggage (one customer, one or more cubicles) ===== --}}
    <div x-cloak x-show="depositOpen" x-transition.opacity class="bc-modal-wrap" @keydown.escape.window="depositOpen = false" role="dialog" aria-modal="true" aria-labelledby="bc-deposit-title">
        <form method="POST" action="{{ route('admin.baggage.store', $branchQuery) }}" @click.outside="depositOpen = false" @submit="submitting = true" class="bc-modal" style="max-width:34rem;" x-ref="depositForm">
            @csrf
            <div class="bc-modal-head">
                <div class="flex items-center gap-3">
                    <span class="bc-tag-badge bc-tone-free bc-soft" x-text="claimLabel || '--'"></span>
                    <div>
                        <h2 id="bc-deposit-title" class="text-base font-semibold">Store baggage</h2>
                        <p class="text-xs bc-muted" x-show="claimLabel">Give the customer claim tag <strong x-text="'#' + claimLabel"></strong></p>
                        <p class="text-xs text-red-600" x-show="!claimLabel">Every tag card of these cubicles is still out. Add a different cubicle.</p>
                    </div>
                </div>
                <button type="button" @click="depositOpen = false" class="rounded-lg p-2 hover:bg-smoke dark:hover:bg-gray-800" aria-label="Close"><span data-lucide="x" class="h-5 w-5"></span></button>
            </div>

            <div class="bc-modal-body space-y-5">
                @if($errors->any() && old('cubicles'))
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
                @endif

                <fieldset>
                    <legend class="mb-1.5 text-xs font-semibold uppercase tracking-wider bc-muted">1 · Customer</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div>
                            <label class="sr-only" for="bc-customer-name">Customer name</label>
                            <input id="bc-customer-name" x-ref="customerName" name="customer_name" x-model="customerName" maxlength="255" required placeholder="Customer name" class="bc-input" autocomplete="off">
                        </div>
                        <div>
                            <label class="sr-only" for="bc-customer-phone">Mobile number</label>
                            <input id="bc-customer-phone" name="customer_phone" x-model="customerPhone" maxlength="30" inputmode="tel" placeholder="Mobile number, e.g. 0917 123 4567" class="bc-input" autocomplete="off">
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-1.5 text-xs font-semibold uppercase tracking-wider bc-muted">2 · Cubicles &amp; payment per cubicle</legend>
                    <div class="space-y-2">
                        <template x-for="(row, index) in rows" :key="row.key">
                            <div class="rounded-xl border border-border p-3 dark:border-gray-800">
                                <div class="flex items-center gap-2">
                                    <label class="text-xs font-semibold bc-muted" :for="'bc-cubicle-' + row.key">Cubicle</label>
                                    <select :id="'bc-cubicle-' + row.key" :name="`cubicles[${index}][number]`" class="bc-input bc-tnum" style="width:6rem;height:2.25rem;" @change="row.number = Number($event.target.value)">
                                        <template x-for="option in cubicleOptions(row)" :key="option.number">
                                            <option :value="option.number" :selected="option.number === row.number" x-text="'#' + option.label"></option>
                                        </template>
                                    </select>
                                    <span x-show="row.number === claimNumber" class="bc-pill bc-tone-brand">Claim tag</span>
                                    <button type="button" x-show="rows.length > 1" @click="removeRow(index)" class="ml-auto rounded-lg p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-gray-800" :aria-label="'Remove cubicle ' + row.number">
                                        <span data-lucide="trash" class="h-4 w-4"></span>
                                    </button>
                                </div>

                                <input type="hidden" :name="`cubicles[${index}][size]`" :value="row.size">
                                <div class="mt-2 grid grid-cols-2 gap-1.5 sm:grid-cols-4" role="radiogroup" :aria-label="'Size for cubicle ' + row.number">
                                    <template x-for="rate in rates" :key="rate.size">
                                        <button type="button" class="bc-choice" style="padding:.4rem .5rem;" :data-active="row.size === rate.size" :aria-pressed="row.size === rate.size" @click="pickSize(row, rate)">
                                            <span class="text-xs font-semibold" x-text="rate.label"></span>
                                            <span class="text-[11px] bc-muted bc-tnum" x-text="money(rate.price) + ' · ' + rate.hours + 'h'"></span>
                                        </button>
                                    </template>
                                </div>

                                <div class="mt-2 flex items-center gap-2">
                                    <label class="text-xs font-semibold bc-muted" :for="'bc-amount-' + row.key">Payment</label>
                                    <div class="relative flex-1">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs bc-muted">{{ $currency }}</span>
                                        <input :id="'bc-amount-' + row.key" type="number" step="0.01" min="0" :name="`cubicles[${index}][amount]`" x-model="row.amount" required class="bc-input bc-tnum text-right" style="height:2.25rem;padding-left:3rem;">
                                    </div>
                                    <span class="whitespace-nowrap text-[11px] bc-muted" x-text="rowRate(row) ? 'Due ' + dueBy(rowRate(row).hours) : 'Pick a size'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addRow()" :disabled="!nextFreeNumber()" class="bc-btn bc-btn-ghost mt-2 w-full" style="height:2.25rem;border-style:dashed;">
                        <span data-lucide="plus" class="h-4 w-4"></span>
                        <span x-text="nextFreeNumber() ? 'Add another cubicle (#' + pad(nextFreeNumber()) + ')' : 'No other free cubicle'"></span>
                    </button>
                </fieldset>

                <fieldset>
                    <legend class="mb-1.5 text-xs font-semibold uppercase tracking-wider bc-muted">3 · Payment method</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="bc-choice" :data-active="paymentType === 'cash'" style="flex-direction:row;align-items:center;gap:.6rem;">
                            <input type="radio" name="payment_type" value="cash" x-model="paymentType" class="sr-only">
                            <span data-lucide="wallet" class="h-5 w-5 text-primary"></span>
                            <span class="text-sm font-semibold">Cash</span>
                        </label>
                        <label class="bc-choice" :data-active="paymentType === 'gcash'" style="flex-direction:row;align-items:center;gap:.6rem;">
                            <input type="radio" name="payment_type" value="gcash" x-model="paymentType" class="sr-only">
                            <span data-lucide="payments" class="h-5 w-5 text-primary"></span>
                            <span class="text-sm font-semibold">GCash</span>
                        </label>
                    </div>
                    <input x-show="paymentType === 'gcash'" name="payment_reference_no" :required="paymentType === 'gcash'" :disabled="paymentType !== 'gcash'" placeholder="GCash reference number" class="bc-input mt-2" autocomplete="off">
                </fieldset>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider bc-muted" for="bc-note">Note <span class="font-normal normal-case">(optional)</span></label>
                    <input id="bc-note" name="notes" x-model="notes" maxlength="255" placeholder="e.g. black trolley, red ribbon" class="bc-input" autocomplete="off">
                </div>

                <div class="rounded-xl p-3" style="background:var(--color-smoke);">
                    <template x-for="row in rows" :key="'sum-' + row.key">
                        <div class="flex items-center justify-between text-xs">
                            <span class="bc-muted" x-text="'Cubicle #' + pad(row.number) + (rowRate(row) ? ' · ' + rowRate(row).label : '')"></span>
                            <span class="font-semibold bc-tnum" x-text="money(row.amount)"></span>
                        </div>
                    </template>
                    <div class="mt-2 flex items-center justify-between border-t border-border pt-2 dark:border-gray-700">
                        <span class="text-sm bc-muted" x-text="'Collect from ' + (customerName.trim() || 'customer')"></span>
                        <span class="text-xl font-bold text-primary bc-tnum">{{ $currency }} <span x-text="money(depositTotal)"></span></span>
                    </div>
                </div>
            </div>

            <div class="bc-modal-foot">
                <button type="button" @click="depositOpen = false" class="bc-btn bc-btn-ghost">Cancel</button>
                <button type="submit" :disabled="!canSubmit" class="bc-btn bc-btn-primary">
                    <span data-lucide="check" class="h-4 w-4"></span>
                    <span x-text="submitting ? 'Saving…' : (rows.length > 1 ? `Paid · Store ${rows.length} cubicles` : 'Paid · Store bag')"></span>
                </button>
            </div>
        </form>
    </div>

    {{-- ===== Claim baggage (one cubicle or the whole ticket) ===== --}}
    <div x-cloak x-show="releaseTag" x-transition.opacity class="bc-modal-wrap" @keydown.escape.window="releaseTag = null" role="dialog" aria-modal="true" aria-labelledby="bc-release-title">
        <div @click.outside="releaseTag = null" class="bc-modal" style="max-width:32rem;">
            <div class="bc-modal-head">
                <div class="flex items-center gap-3">
                    <span class="bc-tag-badge bc-soft" :class="releaseTag?.deposit && isOverdue(releaseTag.deposit) ? 'bc-tone-overdue' : 'bc-tone-stored'" x-text="releaseTag?.deposit?.claim_label"></span>
                    <div class="min-w-0">
                        <h2 id="bc-release-title" class="truncate text-base font-semibold" x-text="currentTicket?.customer_name || 'Claim baggage'"></h2>
                        <p class="text-xs bc-muted">
                            <span x-show="currentTicket?.customer_phone" x-text="currentTicket?.customer_phone + ' · '"></span>
                            Claim tag <strong x-text="'#' + (releaseTag?.deposit?.claim_label || '')"></strong>
                        </p>
                    </div>
                </div>
                <button type="button" @click="releaseTag = null" class="rounded-lg p-2 hover:bg-smoke dark:hover:bg-gray-800" aria-label="Close"><span data-lucide="x" class="h-5 w-5"></span></button>
            </div>

            <template x-if="releaseTag?.deposit">
                <div class="bc-modal-body space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-wider bc-muted" x-text="ticketRows.length > 1 ? ticketActiveCount + ' of ' + ticketRows.length + ' cubicles still at the counter' : 'Cubicle'"></p>
                    <div class="space-y-2">
                        <template x-for="row in ticketRows" :key="'ticket-' + row.number">
                            <div class="flex items-center gap-3 rounded-xl border p-2.5"
                                :class="!row.deposit ? 'bc-tone-free' : (isOverdue(row.deposit) ? 'bc-tone-overdue' : 'bc-tone-stored')"
                                :style="`border-color:var(--bc-line);${row.number === releaseTag.number ? 'box-shadow:0 0 0 2px var(--bc) inset;' : ''}${row.deposit ? '' : 'opacity:.65;'}`">
                                <span class="bc-tag-badge bc-soft" style="min-width:2.5rem;height:2.5rem;font-size:1.05rem;" x-text="row.label"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold" x-text="row.size_label + ' · {{ $currency }} ' + money(row.amount)"></p>
                                    <p class="text-xs font-semibold bc-tnum" style="color:var(--bc);" x-text="row.deposit ? countdown(row.deposit) : 'Claimed ' + (row.released_at_label || '') + (row.released_by ? ' by ' + row.released_by : '')"></p>
                                </div>
                                <template x-if="row.deposit">
                                    <form method="POST" :action="row.deposit.release_url">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="bc-btn bc-btn-ghost" style="height:2.25rem;padding:0 .8rem;">
                                            <span data-lucide="package-check" class="h-4 w-4"></span>
                                            Claim
                                        </button>
                                    </form>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div>
                        <div class="bc-kv"><span class="bc-muted">Total paid</span><span class="font-medium bc-tnum">{{ $currency }} <span x-text="money(currentTicket?.total ?? releaseTag.deposit.amount)"></span> · <span x-text="releaseTag.deposit.payment_type === 'gcash' ? 'GCash' : 'Cash'"></span></span></div>
                        <div class="bc-kv"><span class="bc-muted">Stored at</span><span class="bc-tnum" x-text="releaseTag.deposit.deposited_at_label"></span></div>
                        <div class="bc-kv"><span class="bc-muted">Stored for</span><span class="font-medium bc-tnum" x-text="elapsed(releaseTag.deposit)"></span></div>
                        <div class="bc-kv" x-show="currentTicket?.job_order_number"><span class="bc-muted">Sale</span><a :href="currentTicket?.job_order_url" class="font-medium text-primary hover:underline" x-text="currentTicket?.job_order_number"></a></div>
                        <div class="bc-kv" x-show="releaseTag.deposit.notes"><span class="bc-muted">Note</span><span class="text-right" x-text="releaseTag.deposit.notes"></span></div>
                    </div>
                </div>
            </template>

            <form method="POST" :action="releaseTag?.deposit?.release_all_url" class="bc-modal-foot">
                @csrf
                @method('PATCH')
                <button type="button" @click="releaseTag = null" class="bc-btn bc-btn-ghost">Close</button>
                <button type="submit" class="bc-btn bc-btn-primary">
                    <span data-lucide="package-check" class="h-4 w-4"></span>
                    <span x-text="ticketActiveCount > 1 ? `Claim all ${ticketActiveCount} cubicles` : 'Claim bag'"></span>
                </button>
            </form>
            <form x-show="currentTicket ? currentTicket.can_void : true" method="POST" :action="releaseTag?.deposit?.void_url" class="border-t border-border px-5 py-3 text-center dark:border-gray-800">
                @csrf
                @method('PATCH')
                <button
                    type="submit"
                    x-on:click.prevent="Swal.fire({ title: 'Void this ticket?', text: 'Only for a mistake. The whole sale is cancelled and every cubicle of this tag becomes free.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Void ticket' }).then((r) => { if (r.isConfirmed) $el.closest('form').submit(); })"
                    class="text-xs font-medium text-red-600 hover:underline"
                >Entered by mistake? Void this ticket</button>
            </form>
        </div>
    </div>

    {{-- ===== Settings ===== --}}
    @if($canConfigure)
        <div x-cloak x-show="settingsOpen" x-transition.opacity class="bc-modal-wrap" @keydown.escape.window="settingsOpen = false" role="dialog" aria-modal="true" aria-labelledby="bc-settings-title">
            <form method="POST" action="{{ route('admin.baggage.settings', $branchQuery) }}" @click.outside="settingsOpen = false" class="bc-modal">
                @csrf
                @method('PATCH')
                <div class="bc-modal-head">
                    <div>
                        <h2 id="bc-settings-title" class="text-base font-semibold">Counter settings</h2>
                        <p class="text-xs bc-muted">{{ $branch->name }}</p>
                    </div>
                    <button type="button" @click="settingsOpen = false" class="rounded-lg p-2 hover:bg-smoke dark:hover:bg-gray-800" aria-label="Close"><span data-lucide="x" class="h-5 w-5"></span></button>
                </div>
                <div class="bc-modal-body space-y-5">
                    <div>
                        <label class="text-sm font-semibold" for="bc-tag-count">Number of cubicles (claim tags)</label>
                        <p class="text-xs bc-muted">One tag card per cubicle, numbered 01 to N.</p>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" class="bc-chip" @click="settingsCount = Math.max(0, (settingsCount || 0) - 1)" aria-label="One less tag">−</button>
                            <input id="bc-tag-count" type="number" name="baggage_tag_count" x-model.number="settingsCount" min="0" max="{{ \App\Support\BaggageCounter::MAX_TAGS }}" class="bc-input bc-tnum text-center" style="width:6rem;" required>
                            <button type="button" class="bc-chip" @click="settingsCount = Math.min({{ \App\Support\BaggageCounter::MAX_TAGS }}, (settingsCount || 0) + 1)" aria-label="One more tag">+</button>
                        </div>
                        @error('baggage_tag_count')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p x-show="settingsCount < tagCount && usedAbove(settingsCount) > 0" class="mt-2 rounded-lg px-3 py-2 text-xs bc-tone-stored" style="background:var(--bc-soft);color:var(--bc);">
                            <span x-text="usedAbove(settingsCount)"></span> stored bag(s) are in cubicles above #<span x-text="String(settingsCount).padStart(2, '0')"></span>. They stay on the board until released.
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold">Rates</p>
                            @if($servicesUrl)
                                <a href="{{ $servicesUrl }}" class="text-xs font-semibold text-primary hover:underline">Edit on Laundry Services</a>
                            @endif
                        </div>
                        <p class="text-xs bc-muted">Category "Baggage Storage". Changing a price applies to new deposits only.</p>
                        <div class="mt-2 divide-y divide-border rounded-lg border border-border dark:divide-gray-800 dark:border-gray-800">
                            @forelse($rates as $rate)
                                <div class="flex items-center justify-between px-3 py-2 text-sm">
                                    <span>{{ $rate['label'] }} <span class="text-xs bc-muted">· {{ $rate['hours'] }}h</span></span>
                                    <span class="font-semibold bc-tnum">{{ $currency }} {{ number_format($rate['price'], 2) }}</span>
                                </div>
                            @empty
                                <p class="px-3 py-2 text-sm bc-muted">No active rates.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="bc-modal-foot">
                    <button type="button" @click="settingsOpen = false" class="bc-btn bc-btn-ghost">Cancel</button>
                    <button type="submit" class="bc-btn bc-btn-primary">Save settings</button>
                </div>
            </form>
        </div>
    @endif
    @endif
</div>

<script>
function baggageCounter(config) {
    return {
        tags: config.tags || [],
        rates: config.rates || [],
        tickets: config.tickets || {},
        heldClaimTags: (config.heldClaimTags || []).map(Number),
        tagCount: config.tagCount || 0,
        now: Date.now(),
        filter: 'all',
        findValue: '',
        depositOpen: false,
        releaseTag: null,
        settingsOpen: Boolean(config.settingsOpen),
        settingsCount: config.tagCount || 0,
        setupCount: 6,
        rows: [],
        rowSeq: 0,
        customerName: '',
        customerPhone: '',
        notes: '',
        paymentType: 'cash',
        submitting: false,
        init() {
            setInterval(() => { this.now = Date.now(); }, 1000);
            if (config.old) this.reopenDeposit(config.old);
            this.$watch('depositOpen', () => this.refreshIcons());
            this.$watch('rows', () => this.refreshIcons());
            this.$watch('releaseTag', () => this.refreshIcons());
            this.$watch('filter', () => this.refreshIcons());
            this.$watch('settingsOpen', () => this.refreshIcons());
            this.refreshIcons();
        },
        get filters() {
            return [
                { key: 'all', label: 'All', count: () => this.tags.length },
                { key: 'free', label: 'Free', count: () => this.freeCount },
                { key: 'stored', label: 'Stored', count: () => this.usedCount },
                { key: 'overdue', label: 'Overdue', count: () => this.overdueCount },
            ];
        },
        get freeTags() { return this.tags.filter(t => !t.deposit); },
        get freeCount() { return this.freeTags.length; },
        get usedCount() { return this.tags.filter(t => t.deposit).length; },
        get customerCount() { return new Set(this.tags.filter(t => t.deposit).map(t => t.deposit.ticket_code || t.number)).size; },
        get overdueTags() { return this.tags.filter(t => t.deposit && this.isOverdue(t.deposit)); },
        get overdueCount() { return this.overdueTags.length; },
        get visibleTags() {
            if (this.filter === 'free') return this.freeTags;
            if (this.filter === 'stored') return this.tags.filter(t => t.deposit);
            if (this.filter === 'overdue') return this.overdueTags;
            return this.tags;
        },
        get nextDue() {
            const upcoming = this.tags
                .filter(t => t.deposit && !this.isOverdue(t.deposit))
                .sort((a, b) => new Date(a.deposit.due_at) - new Date(b.deposit.due_at))[0];
            return upcoming ? `#${upcoming.label} at ${this.time(new Date(upcoming.deposit.due_at))}` : '';
        },
        get clock() {
            return new Date(this.now).toLocaleString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit' });
        },

        // ----- Store baggage -----
        // Claim tag = the first cubicle whose tag card is not still out with another customer (same rule as the server).
        get claimNumber() {
            const row = this.rows.find(r => !this.isHeldTag(r.number));
            return row ? row.number : null;
        },
        get claimLabel() { return this.claimNumber ? this.pad(this.claimNumber) : ''; },
        get depositTotal() { return this.rows.reduce((sum, r) => sum + (Number(r.amount) || 0), 0); },
        get canSubmit() {
            return !this.submitting
                && this.customerName.trim() !== ''
                && this.rows.length > 0
                && Boolean(this.claimNumber)
                && this.rows.every(r => r.size && r.amount !== '' && r.amount !== null && Number(r.amount) >= 0);
        },
        newRow(number, size = null, amount = null) {
            const rate = size ? this.rates.find(r => r.size === size) : (this.rates.length === 1 ? this.rates[0] : null);
            return {
                key: ++this.rowSeq,
                number: Number(number),
                size: rate ? rate.size : '',
                amount: amount !== null && amount !== undefined && amount !== '' ? amount : (rate ? rate.price : ''),
            };
        },
        rowRate(row) { return this.rates.find(r => r.size === row.size); },
        pickSize(row, rate) {
            row.size = rate.size;
            row.amount = rate.price;
        },
        isHeldTag(number) { return this.heldClaimTags.includes(Number(number)); },
        cubicleOptions(row) {
            const used = this.rows.filter(r => r !== row).map(r => r.number);
            return this.freeTags.filter(t => !used.includes(Number(t.number)));
        },
        nextFreeNumber() {
            const used = this.rows.map(r => r.number);
            const tag = this.freeTags.find(t => !used.includes(Number(t.number)));
            return tag ? Number(tag.number) : null;
        },
        addRow() {
            const number = this.nextFreeNumber();
            if (number) this.rows.push(this.newRow(number));
        },
        removeRow(index) {
            if (this.rows.length > 1) this.rows.splice(index, 1);
        },
        storeNext() {
            const tag = this.freeTags.find(t => !this.isHeldTag(t.number)) || this.freeTags[0];
            if (tag) this.openDeposit(tag);
        },
        openDeposit(tag) {
            this.releaseTag = null;
            this.settingsOpen = false;
            this.rows = [this.newRow(tag.number)];
            this.customerName = '';
            this.customerPhone = '';
            this.notes = '';
            this.paymentType = 'cash';
            this.submitting = false;
            this.depositOpen = true;
            this.$nextTick(() => this.$refs.customerName?.focus());
        },
        reopenDeposit(old) {
            const rows = (old.cubicles || [])
                .filter(c => this.freeTags.some(t => Number(t.number) === Number(c.number)))
                .map(c => this.newRow(c.number, c.size, c.amount));
            if (!rows.length) {
                const tag = this.freeTags[0];
                if (!tag) return;
                rows.push(this.newRow(tag.number));
            }
            this.releaseTag = null;
            this.rows = rows;
            this.customerName = old.customer_name || '';
            this.customerPhone = old.customer_phone || '';
            this.notes = old.notes || '';
            this.paymentType = old.payment_type || 'cash';
            this.submitting = false;
            this.depositOpen = true;
        },

        // ----- Claim baggage -----
        ticketOf(tag) {
            const code = tag?.deposit?.ticket_code;
            return code ? this.tickets[code] || null : null;
        },
        ticketCount(tag) {
            const ticket = this.ticketOf(tag);
            return ticket ? ticket.cubicles.filter(c => c.active).length : 1;
        },
        get currentTicket() { return this.ticketOf(this.releaseTag); },
        // The ticket's cubicles; active ones carry the live deposit (countdown + claim URL).
        get ticketRows() {
            const tag = this.releaseTag;
            if (!tag?.deposit) return [];
            const ticket = this.currentTicket;
            if (!ticket) {
                return [{ number: tag.number, label: tag.label, size_label: tag.deposit.size_label, amount: tag.deposit.amount, deposit: tag.deposit }];
            }
            return ticket.cubicles.map(c => {
                const live = c.active ? this.tags.find(t => Number(t.number) === Number(c.number) && t.deposit?.ticket_code === ticket.code) : null;
                return { ...c, deposit: live ? live.deposit : null };
            });
        },
        get ticketActiveCount() { return this.ticketRows.filter(r => r.deposit).length; },
        openRelease(tag) {
            this.depositOpen = false;
            this.settingsOpen = false;
            this.releaseTag = tag;
        },
        openSettings() {
            this.depositOpen = false;
            this.releaseTag = null;
            this.settingsCount = this.tagCount;
            this.settingsOpen = true;
        },
        statusOf(tag) {
            if (!tag.deposit) return 'Free';
            return this.isOverdue(tag.deposit) ? 'Overdue' : 'Stored';
        },
        // A number opens that claim tag's ticket (or that cubicle); text searches customer name / mobile.
        findTag() {
            const value = String(this.findValue).trim();
            if (!value) return;
            const stored = this.tags.filter(t => t.deposit);
            let tag = null;
            if (/^\d+$/.test(value)) {
                const number = parseInt(value, 10);
                tag = stored.find(t => Number(t.deposit.claim_tag) === number)
                    || this.tags.find(t => Number(t.number) === number);
            } else {
                const needle = value.toLowerCase();
                const digits = value.replace(/\D/g, '');
                tag = stored.find(t => (t.deposit.customer_name || '').toLowerCase().includes(needle)
                    || (digits.length >= 3 && (t.deposit.customer_phone || '').replace(/\D/g, '').includes(digits)));
            }
            if (!tag) {
                window.Swal?.fire({ toast: true, position: 'top-end', timer: 2200, showConfirmButton: false, icon: 'info', title: `No tag or customer matches "${value}"` });
                return;
            }
            this.findValue = '';
            tag.deposit ? this.openRelease(tag) : this.openDeposit(tag);
        },
        usedAbove(count) {
            return this.tags.filter(t => t.deposit && Number(t.number) > Number(count || 0)).length;
        },
        modalOpen() { return Boolean(this.depositOpen || this.releaseTag || this.settingsOpen); },
        hotkey(event) {
            const typing = ['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target.tagName);
            if (event.ctrlKey || event.metaKey || event.altKey) return;
            // 1-9 picks the size of the last cubicle row.
            if (this.depositOpen && !typing && /^[1-9]$/.test(event.key)) {
                const rate = this.rates[Number(event.key) - 1];
                const row = this.rows[this.rows.length - 1];
                if (rate && row) { this.pickSize(row, rate); event.preventDefault(); }
                return;
            }
            if (typing || this.modalOpen() || !this.tags.length) return;
            if (event.key === 'n' || event.key === 'N') { event.preventDefault(); this.storeNext(); }
            if (event.key === '/') { event.preventDefault(); this.$refs.find?.focus(); }
        },
        isOverdue(deposit) {
            return Boolean(deposit?.due_at) && this.now > new Date(deposit.due_at).getTime();
        },
        progress(deposit) {
            const start = new Date(deposit.deposited_at).getTime();
            const end = new Date(deposit.due_at).getTime();
            if (!(end > start)) return 100;
            return Math.min(100, Math.max(0, ((this.now - start) / (end - start)) * 100));
        },
        duration(ms) {
            const total = Math.max(Math.floor(ms / 1000), 0);
            const h = Math.floor(total / 3600);
            const m = Math.floor((total % 3600) / 60);
            const s = total % 60;
            return h > 0 ? `${h}h ${String(m).padStart(2, '0')}m` : `${m}m ${String(s).padStart(2, '0')}s`;
        },
        countdown(deposit) {
            if (!deposit?.due_at) return '';
            const diff = new Date(deposit.due_at).getTime() - this.now;
            return diff >= 0 ? `${this.duration(diff)} left` : `${this.duration(-diff)} over`;
        },
        overdueShort(deposit) {
            return `${this.duration(this.now - new Date(deposit.due_at).getTime())} over`;
        },
        elapsed(deposit) {
            if (!deposit?.deposited_at) return '';
            return this.duration(this.now - new Date(deposit.deposited_at).getTime());
        },
        time(date) {
            return date.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
        },
        dueBy(hours) {
            const due = new Date(this.now + hours * 3600 * 1000);
            const sameDay = due.toDateString() === new Date(this.now).toDateString();
            return sameDay ? this.time(due) : due.toLocaleString('en-PH', { weekday: 'short', hour: 'numeric', minute: '2-digit' });
        },
        pad(number) { return String(number ?? '').padStart(2, '0'); },
        money(value) {
            return Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
