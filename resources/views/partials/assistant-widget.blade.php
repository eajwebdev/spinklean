@php
    $assistantUser = auth()->user();
    $assistantAllowed = in_array($assistantUser?->role, ['super_admin', 'admin', 'branch_manager'], true);
@endphp

@if($assistantAllowed)
    @php
        $assistantCanChooseBranch = $assistantUser->canManageAllBranches();
        $assistantPresets = [
            ['key' => 'daily_sales', 'label' => 'Daily sales', 'icon' => 'payments', 'question' => 'How are daily sales today?'],
            ['key' => 'payment_mix', 'label' => 'Payment mix', 'icon' => 'dollar', 'question' => 'What is the payment method breakdown?'],
            ['key' => 'expenses', 'label' => 'Expenses', 'icon' => 'expense', 'question' => 'Summarize expenses for this period.'],
            ['key' => 'cash_drawer', 'label' => 'Cash drawer', 'icon' => 'wallet', 'question' => 'What should be in the cash drawer?'],
            ['key' => 'petty_cash', 'label' => 'Petty cash', 'icon' => 'wallet', 'question' => 'Show petty cash movement.'],
            ['key' => 'receivables', 'label' => 'Receivables', 'icon' => 'receivables', 'question' => 'How much receivables are still open?'],
            ['key' => 'unpaid_orders', 'label' => 'Unpaid orders', 'icon' => 'file-text', 'question' => 'Which job orders are unpaid?'],
            ['key' => 'active_cycles', 'label' => 'Active cycles', 'icon' => 'cycles', 'question' => 'What cycles are active right now?'],
            ['key' => 'ready_pickup', 'label' => 'Ready orders', 'icon' => 'laundry', 'question' => 'How many orders are ready for pickup or delivery?'],
            ['key' => 'low_stock', 'label' => 'Low stock', 'icon' => 'inventory', 'question' => 'Which inventory items are low stock?'],
            ['key' => 'top_customers', 'label' => 'Top customers', 'icon' => 'customers', 'question' => 'Who are the top customers?'],
            ['key' => 'branch_compare', 'label' => 'Branch compare', 'icon' => 'branches', 'question' => 'Compare branch sales.'],
            ['key' => 'attendance_today', 'label' => 'Attendance', 'icon' => 'attendance', 'question' => 'Show attendance today.'],
            ['key' => 'eod_tasks', 'label' => 'Daily tasks', 'icon' => 'check', 'question' => 'What daily tasks are completed?'],
            ['key' => 'z_reading', 'label' => 'Z Reading', 'icon' => 'receipt', 'question' => 'Show the latest Z Reading variance.'],
        ];
    @endphp

    <div
        x-data="systemAssistant({
            endpoint: @js(route('dashboard.assistant')),
            optionsEndpoint: @js(route('dashboard.assistant.options')),
            csrf: @js(csrf_token()),
            canChooseBranch: @js($assistantCanChooseBranch),
            defaultBranchId: @js($assistantCanChooseBranch ? '' : (string) $assistantUser->branch_id),
            assignedBranchName: @js($assistantUser->branch?->name ?? 'Assigned Branch'),
            defaultDateRange: @js(today()->toDateString().' to '.today()->toDateString()),
            presets: @js($assistantPresets),
        })"
        x-cloak
        class="fixed bottom-4 right-4 z-50 print:hidden"
    >
        <button
            type="button"
            @click="open = !open; $nextTick(() => { if (open) { initDateRange(); loadOptions(); scrollThread(); } window.renderLucideIcons(); })"
            title="System assistant"
            aria-label="Open system assistant"
            class="flex h-12 w-12 items-center justify-center rounded-full border border-emerald-300 bg-emerald-50 text-emerald-700 shadow-lg shadow-emerald-500/20 ring-4 ring-emerald-400/20 transition hover:scale-105 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200"
        >
            <span data-lucide="bot" class="h-5 w-5"></span>
        </button>

        <div
            x-show="open"
            x-transition
            @click.outside="open = false"
            class="absolute bottom-14 right-0 flex max-h-[min(44rem,calc(100vh-7rem))] w-[min(28rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-lg border border-border bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950"
        >
            <div class="border-b border-border p-4 dark:border-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="inline-flex items-center gap-2 text-sm font-semibold">
                            <span data-lucide="bot" class="h-4 w-4 text-primary"></span>
                            AI Assistant
                        </p>
                        <p class="mt-1 text-xs text-muted">Chat-style answers from live system data.</p>
                    </div>
                    <button type="button" @click="open = false" title="Close" aria-label="Close system assistant" class="rounded-md p-1.5 hover:bg-smoke dark:hover:bg-gray-900">
                        <span data-lucide="x" class="h-4 w-4"></span>
                    </button>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @if($assistantCanChooseBranch)
                        <select x-model="branchId" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-900">
                            <option value="">All branches</option>
                            <template x-for="branch in branches" :key="branch.id">
                                <option :value="branch.id" x-text="branch.name"></option>
                            </template>
                        </select>
                    @else
                        <div class="flex h-9 items-center rounded-md border border-border bg-smoke px-3 text-sm font-medium dark:border-gray-800 dark:bg-gray-900" x-text="assignedBranchName"></div>
                    @endif

                    <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-900">
                        <span data-lucide="calendar" class="h-4 w-4 text-muted"></span>
                        <input x-ref="dateRange" x-model="dateRange" type="text" class="w-full bg-transparent text-sm outline-none" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="flex gap-2 overflow-x-auto border-b border-border p-3 dark:border-gray-800">
                <template x-for="preset in presets" :key="preset.key">
                    <button type="button" @click="askPreset(preset)" class="flex h-9 shrink-0 items-center gap-2 rounded-full border border-border px-3 text-left text-xs font-medium hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-900" :class="activePreset === preset.key ? 'border-primary text-primary' : ''">
                        <span :data-lucide="preset.icon" class="h-3.5 w-3.5 shrink-0"></span>
                        <span class="min-w-0 truncate" x-text="preset.label"></span>
                    </button>
                </template>
            </div>

            <div x-ref="thread" class="min-h-72 flex-1 space-y-4 overflow-y-auto bg-smoke/50 p-4 dark:bg-gray-950">
                <template x-for="message in messages" :key="message.id">
                    <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[86%] rounded-lg px-3 py-2 text-sm shadow-sm" :class="message.role === 'user' ? 'bg-primary text-white' : 'border border-border bg-white dark:border-gray-800 dark:bg-gray-900'">
                            <template x-if="message.role === 'assistant'">
                                <div class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-primary">
                                    <span data-lucide="bot" class="h-3.5 w-3.5"></span>
                                    Assistant
                                </div>
                            </template>

                            <p class="leading-6" x-text="message.text"></p>

                            <template x-if="message.answer">
                                <div class="mt-3 space-y-2">
                                    <div class="rounded-md bg-smoke px-2.5 py-2 text-xs dark:bg-gray-950">
                                        <p class="font-semibold" x-text="message.answer.title"></p>
                                        <p class="mt-0.5 text-muted">
                                            <span x-text="message.answer.scope"></span>
                                            <span> | </span>
                                            <span x-text="message.answer.period"></span>
                                        </p>
                                    </div>

                                    <div class="grid gap-1.5">
                                        <template x-for="metric in message.answer.metrics" :key="metric.label">
                                            <div class="flex items-center justify-between gap-3 rounded-md border border-border bg-white px-2.5 py-1.5 text-xs dark:border-gray-800 dark:bg-gray-900">
                                                <span class="min-w-0 truncate text-muted" x-text="metric.label"></span>
                                                <span class="shrink-0 font-semibold" x-text="metric.value"></span>
                                            </div>
                                        </template>
                                        <p x-show="message.answer.metrics && message.answer.metrics.length === 0" class="rounded-md border border-border px-3 py-4 text-center text-xs text-muted dark:border-gray-800">No records found for this question.</p>
                                    </div>

                                    <p class="text-right text-[11px] text-muted" x-text="message.answer.generated_at"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div x-show="loading" class="flex justify-start">
                    <div class="inline-flex items-center rounded-lg border border-border bg-white px-3 py-2 text-sm text-muted shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <span data-lucide="loader" class="mr-2 h-4 w-4 animate-spin"></span>
                        Checking live system data...
                    </div>
                </div>
            </div>

            <form @submit.prevent="askText()" class="border-t border-border p-3 dark:border-gray-800">
                <div class="flex gap-2">
                    <input x-model="draft" type="text" placeholder="Ask about sales, expenses, cash drawer..." class="h-10 min-w-0 flex-1 rounded-md border border-border bg-white px-3 text-sm outline-none focus:border-primary dark:border-gray-800 dark:bg-gray-900">
                    <button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-primary text-white hover:opacity-90" title="Send" aria-label="Send assistant question">
                        <span data-lucide="search" class="h-4 w-4"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function systemAssistant(config) {
            return {
                open: false,
                loading: false,
                messages: [{
                    id: 1,
                    role: 'assistant',
                    text: 'Hi, I can help you check live sales, expenses, cash drawer, receivables, attendance, daily tasks, and branch performance. Choose a quick question or type your own.',
                }],
                draft: '',
                activePreset: null,
                branchId: config.defaultBranchId,
                branches: [],
                optionsLoaded: false,
                assignedBranchName: config.assignedBranchName,
                dateRange: config.defaultDateRange,
                presets: config.presets,
                dateRangeReady: false,
                loadOptions() {
                    if (this.optionsLoaded) return;

                    fetch(config.optionsEndpoint, { headers: { 'Accept': 'application/json' } })
                        .then(response => response.json())
                        .then(payload => {
                            this.branches = payload.branches || [];
                            if (!config.canChooseBranch && this.branches[0]) {
                                this.assignedBranchName = this.branches[0].name;
                            }
                            this.optionsLoaded = true;
                        })
                        .finally(() => this.$nextTick(() => window.renderLucideIcons()));
                },
                initDateRange() {
                    if (this.dateRangeReady || !window.flatpickr || !this.$refs.dateRange) return;

                    window.flatpickr(this.$refs.dateRange, {
                        mode: 'range',
                        dateFormat: 'Y-m-d',
                        defaultDate: this.dateRange ? this.dateRange.split(' to ') : null,
                        onClose: (dates, value) => this.dateRange = value,
                    });
                    this.dateRangeReady = true;
                },
                scrollThread() {
                    this.$nextTick(() => {
                        if (this.$refs.thread) {
                            this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight;
                        }
                    });
                },
                pushMessage(role, text, answer = null) {
                    this.messages.push({
                        id: Date.now() + Math.random(),
                        role,
                        text,
                        answer,
                    });
                    this.scrollThread();
                    this.$nextTick(() => window.renderLucideIcons());
                },
                askPreset(preset) {
                    this.ask(preset.key, preset.question || preset.label);
                },
                askText() {
                    const question = this.draft.trim();
                    if (!question) return;

                    this.draft = '';
                    this.ask(null, question);
                },
                assistantText(payload) {
                    return `${payload.summary} I scoped this to ${payload.scope} for ${payload.period}.`;
                },
                ask(preset, question) {
                    this.loading = true;
                    this.activePreset = preset;
                    this.pushMessage('user', question);

                    fetch(config.endpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config.csrf,
                        },
                        body: JSON.stringify({
                            preset,
                            question,
                            branch_id: this.branchId || null,
                            date_range: this.dateRange,
                        }),
                    })
                        .then(response => {
                            if (!response.ok) throw new Error('Assistant request failed.');
                            return response.json();
                        })
                        .then(payload => {
                            this.activePreset = payload.preset;
                            this.pushMessage('assistant', this.assistantText(payload), payload);
                        })
                        .catch(() => window.toast && window.toast.fire({ icon: 'error', title: 'Assistant could not load data.' }))
                        .finally(() => {
                            this.loading = false;
                            this.scrollThread();
                        });
                },
            };
        }
    </script>
@endif
