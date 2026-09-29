<div
    x-data="{
        open: false,
        loading: false,
        submitting: false,
        search: '',
        transfers: [],
        selectedIds: [],
        count: 0,
        init() {
            window.addEventListener('open-incoming-tags-modal', () => {
                this.open = true;
                this.loadTransfers();
            });

            this.$watch('open', (val) => {
                if (val && this.transfers.length === 0) {
                    this.loadTransfers();
                }
            });
        },
        async loadTransfers() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.search) params.append('search', this.search);
                const res = await fetch(`{{ route('admin.transfers.pending') }}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success) {
                    this.transfers = data.transfers || [];
                    this.count = data.count || 0;
                    // update any badges on page
                    document.querySelectorAll('.js-tag-count').forEach(el => el.textContent = this.count);
                    // Filter selectedIds to only those still present
                    const validIds = new Set(this.transfers.map(t => t.id));
                    this.selectedIds = this.selectedIds.filter(id => validIds.has(id));
                }
            } catch (e) {
                console.error('Failed to load transfers:', e);
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            }
        },
        get filteredTransfers() {
            if (!this.search.trim()) return this.transfers;
            const q = this.search.toLowerCase();
            return this.transfers.filter(t => 
                (t.tag_number && t.tag_number.toLowerCase().includes(q)) ||
                (t.job_order_number && t.job_order_number.toLowerCase().includes(q)) ||
                (t.customer_name && t.customer_name.toLowerCase().includes(q)) ||
                (t.customer_phone && t.customer_phone.toLowerCase().includes(q)) ||
                (t.origin_branch_name && t.origin_branch_name.toLowerCase().includes(q))
            );
        },
        get isAllSelected() {
            const list = this.filteredTransfers;
            return list.length > 0 && list.every(t => this.selectedIds.includes(t.id));
        },
        toggleSelectAll() {
            const list = this.filteredTransfers;
            if (this.isAllSelected) {
                const currentFilterIds = new Set(list.map(t => t.id));
                this.selectedIds = this.selectedIds.filter(id => !currentFilterIds.has(id));
            } else {
                const combined = new Set([...this.selectedIds, ...list.map(t => t.id)]);
                this.selectedIds = Array.from(combined);
            }
        },
        toggleItem(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx > -1) {
                this.selectedIds.splice(idx, 1);
            } else {
                this.selectedIds.push(id);
            }
        },
        async confirmAndReceive() {
            if (this.selectedIds.length === 0) {
                if (window.Swal) {
                    Swal.fire({ title: 'No tags selected', text: 'Please select at least one incoming laundry tag to receive.', icon: 'info' });
                } else {
                    alert('Please select at least one incoming laundry tag.');
                }
                return;
            }

            const count = this.selectedIds.length;
            const doReceive = async () => {
                this.submitting = true;
                try {
                    const res = await fetch(`{{ route('admin.transfers.receive') }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ transfer_ids: this.selectedIds })
                    });
                    const result = await res.json();
                    if (!res.ok || !result.success) {
                        const errMsg = (result.errors && Object.values(result.errors).flat().join('\n')) || result.message || 'Failed to receive tags.';
                        if (window.Swal) {
                            Swal.fire({ title: 'Error', text: errMsg, icon: 'error' });
                        } else {
                            alert(errMsg);
                        }
                        return;
                    }

                    if (window.Swal) {
                        await Swal.fire({
                            title: 'Success!',
                            text: result.message || `${count} laundry orders received and added to Cycle Monitoring.`,
                            icon: 'success',
                            confirmButtonColor: '#0284c7'
                        });
                    }

                    this.selectedIds = [];
                    await this.loadTransfers();

                    // If currently on cycles index page, reload to refresh cycle list
                    if (window.location.pathname.includes('/admin/cycles')) {
                        window.location.reload();
                    }
                } catch (err) {
                    console.error('Receive error:', err);
                    alert('An unexpected error occurred while receiving transfers.');
                } finally {
                    this.submitting = false;
                }
            };

            if (count > 1 && window.Swal) {
                Swal.fire({
                    title: `Receive ${count} Laundry Tags?`,
                    text: 'This will confirm arrival at this branch and add them to Cycle Monitoring with status RECEIVED.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Receive & Add',
                    confirmButtonColor: '#0284c7',
                    cancelButtonColor: '#64748b'
                }).then((r) => {
                    if (r.isConfirmed) doReceive();
                });
            } else {
                doReceive();
            }
        }
    }"
    x-cloak
    x-show="open"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
>
    <!-- Backdrop -->
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="open = false"></div>

    <div class="flex min-h-screen items-center justify-center p-3 sm:p-4">
        <div
            x-show="open"
            x-transition
            class="relative flex max-h-[90vh] w-full max-w-4xl flex-col rounded-xl border border-border bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900"
            @click.outside="open = false"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-border px-5 py-4 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20">
                        <span data-lucide="tag" class="h-5 w-5"></span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-dark dark:text-white sm:text-lg">Incoming Laundry Tags</h2>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300" x-text="`${transfers.length} pending`"></span>
                        </div>
                        <p class="text-xs text-muted">Laundry transferred from drop-off branches waiting to be received into production</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="open = false"
                    class="rounded-lg p-2 text-muted transition hover:bg-smoke hover:text-dark dark:hover:bg-gray-800 dark:hover:text-white"
                >
                    <span data-lucide="x" class="h-5 w-5"></span>
                </button>
            </div>

            <!-- Toolbar / Search -->
            <div class="flex flex-col gap-3 border-b border-border bg-slate-50/50 p-4 dark:border-gray-800 dark:bg-gray-950/50 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative flex-1">
                    <span data-lucide="search" class="absolute left-3 top-2.5 h-4 w-4 text-muted"></span>
                    <input
                        type="search"
                        x-model="search"
                        @input.debounce.300ms="loadTransfers()"
                        placeholder="Search by Tag #, JO #, Customer, Phone, Origin Branch..."
                        class="h-9 w-full rounded-lg border border-border bg-white pl-9 pr-3 text-sm outline-none transition focus:border-primary dark:border-gray-800 dark:bg-gray-900"
                    >
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="toggleSelectAll()"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-semibold text-dark shadow-sm transition hover:bg-smoke dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200"
                    >
                        <span data-lucide="check-square" class="h-3.5 w-3.5"></span>
                        <span x-text="isAllSelected ? 'Deselect All' : 'Select All'"></span>
                    </button>
                    <button
                        type="button"
                        @click="loadTransfers()"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-white text-muted shadow-sm transition hover:bg-smoke hover:text-dark dark:border-gray-800 dark:bg-gray-900"
                        title="Refresh"
                    >
                        <span data-lucide="rotate-cw" class="h-4 w-4" :class="loading ? 'animate-spin' : ''"></span>
                    </button>
                </div>
            </div>

            <!-- Body / Table -->
            <div class="flex-1 overflow-y-auto p-4">
                <!-- Loading State -->
                <div x-show="loading" class="py-12 text-center text-sm text-muted">
                    <span data-lucide="loader-2" class="mx-auto mb-2 h-6 w-6 animate-spin text-primary"></span>
                    Loading incoming transfers...
                </div>

                <!-- Empty State -->
                <div x-show="!loading && filteredTransfers.length === 0" class="py-14 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-gray-800 dark:text-gray-500">
                        <span data-lucide="inbox" class="h-6 w-6"></span>
                    </div>
                    <p class="text-sm font-medium text-dark dark:text-white">No incoming laundry tags are waiting to be received.</p>
                    <p class="mt-1 text-xs text-muted">When a drop-off branch assigns laundry to this branch, it will appear here.</p>
                </div>

                <!-- Items Table -->
                <div x-show="!loading && filteredTransfers.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-border text-[11px] font-semibold uppercase tracking-wider text-muted dark:border-gray-800">
                                <th class="w-10 pb-2 pl-2">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll()"
                                        class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                    >
                                </th>
                                <th class="pb-2">Tag #</th>
                                <th class="pb-2">JO #</th>
                                <th class="pb-2">Customer & Contact</th>
                                <th class="pb-2">Origin Branch</th>
                                <th class="pb-2">Services</th>
                                <th class="pb-2">Payment</th>
                                <th class="pb-2">Order Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border dark:divide-gray-800">
                            <template x-for="item in filteredTransfers" :key="item.id">
                                <tr
                                    @click="toggleItem(item.id)"
                                    class="cursor-pointer transition hover:bg-sky-50/40 dark:hover:bg-gray-800/50"
                                    :class="selectedIds.includes(item.id) ? 'bg-sky-50/80 dark:bg-sky-950/20' : ''"
                                >
                                    <td class="py-3 pl-2" @click.stop>
                                        <input
                                            type="checkbox"
                                            :value="item.id"
                                            :checked="selectedIds.includes(item.id)"
                                            @change="toggleItem(item.id)"
                                            class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                        >
                                    </td>
                                    <td class="py-3 font-mono font-bold text-primary">
                                        <span class="inline-flex items-center gap-1 rounded bg-sky-100 px-2 py-0.5 text-xs text-sky-800 dark:bg-sky-950 dark:text-sky-300">
                                            <span data-lucide="tag" class="h-3 w-3"></span>
                                            <span x-text="item.tag_number"></span>
                                        </span>
                                    </td>
                                    <td class="py-3 font-semibold text-dark dark:text-white" x-text="item.job_order_number"></td>
                                    <td class="py-3">
                                        <p class="font-medium text-dark dark:text-white" x-text="item.customer_name"></p>
                                        <p class="text-[11px] text-muted" x-text="item.customer_phone"></p>
                                    </td>
                                    <td class="py-3">
                                        <span class="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                            <span data-lucide="store" class="h-3 w-3"></span>
                                            <span x-text="item.origin_branch_name"></span>
                                        </span>
                                    </td>
                                    <td class="py-3 max-w-[12rem] truncate text-muted" :title="item.services" x-text="item.services"></td>
                                    <td class="py-3">
                                        <span
                                            class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase"
                                            :class="item.balance <= 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : (item.payment_status.startsWith('Partial') ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300')"
                                            x-text="item.payment_status"
                                        ></span>
                                    </td>
                                    <td class="py-3 text-muted" x-text="item.order_date"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex flex-col gap-3 border-t border-border bg-slate-50/50 px-5 py-4 dark:border-gray-800 dark:bg-gray-950/50 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-xs text-muted">
                    <span class="font-semibold text-dark dark:text-white" x-text="selectedIds.length"></span>
                    <span> of </span>
                    <span class="font-semibold text-dark dark:text-white" x-text="filteredTransfers.length"></span>
                    <span> orders selected</span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="open = false"
                        class="h-9 rounded-lg border border-border px-4 text-xs font-semibold text-muted transition hover:bg-smoke hover:text-dark dark:border-gray-800 dark:hover:bg-gray-900 dark:hover:text-white"
                    >
                        Close
                    </button>
                    <button
                        type="button"
                        @click="confirmAndReceive()"
                        :disabled="selectedIds.length === 0 || submitting"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-primary px-4 text-xs font-semibold text-white shadow transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span x-show="!submitting" data-lucide="check-circle-2" class="h-4 w-4"></span>
                        <span x-show="submitting" data-lucide="loader-2" class="h-4 w-4 animate-spin"></span>
                        <span>Receive & Add to Cycle Monitoring</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
