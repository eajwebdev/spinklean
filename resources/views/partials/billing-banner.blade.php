@isset($billingBanner)
    @php
        $noticeType = $billingBanner['type'] ?? 'billing';
        $isTrialNotice = $noticeType === 'trial';
        $isDangerNotice = $noticeType === 'danger';
        $isSuccessNotice = $noticeType === 'success';
        $panelClasses = match (true) {
            $isTrialNotice => 'border-blue-200 bg-white text-blue-900 dark:border-blue-900/70 dark:bg-gray-950 dark:text-blue-100',
            $isDangerNotice => 'border-red-300 bg-white text-red-950 ring-2 ring-red-100 dark:border-red-900/80 dark:bg-gray-950 dark:text-red-100 dark:ring-red-950/50',
            $isSuccessNotice => 'border-green-200 bg-white text-green-950 dark:border-green-900/70 dark:bg-gray-950 dark:text-green-100',
            default => 'border-orange-200 bg-white text-orange-950 dark:border-orange-900/70 dark:bg-gray-950 dark:text-orange-100',
        };
        $iconClasses = match (true) {
            $isTrialNotice => 'border-blue-300 bg-blue-50 text-blue-700 shadow-blue-500/20 ring-blue-400/30 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-200',
            $isDangerNotice => 'border-red-400 bg-red-50 text-red-700 shadow-red-500/40 ring-red-500/40 dark:border-red-800 dark:bg-red-950/60 dark:text-red-200',
            $isSuccessNotice => 'border-green-300 bg-green-50 text-green-700 shadow-green-500/20 ring-green-400/30 dark:border-green-800 dark:bg-green-950/50 dark:text-green-200',
            default => 'border-orange-300 bg-orange-50 text-orange-700 shadow-orange-500/30 ring-orange-400/40 dark:border-orange-800 dark:bg-orange-950/50 dark:text-orange-200',
        };
        $noticeIcon = $isTrialNotice ? 'sparkles' : ($isDangerNotice ? 'alertTriangle' : ($isSuccessNotice ? 'check' : 'bell'));
        $noticeTitle = $isTrialNotice ? 'Free Trial Active' : ($isDangerNotice ? 'Subscription Warning' : ($isSuccessNotice ? 'Billing Paid' : 'Upcoming Billing'));
    @endphp

    <div
        x-data="{
            key: @js($billingBanner['key'] ?? null),
            dismissible: @js((bool) ($billingBanner['dismissible'] ?? false)),
            autoOpen: @js((bool) ($billingBanner['autoOpen'] ?? false)),
            visible: true,
            open: false,
            init() {
                if (this.dismissible && this.key) {
                    this.visible = localStorage.getItem(this.key) !== 'dismissed';
                }
                this.open = this.autoOpen && this.visible;
            },
            dismiss() {
                if (this.key) localStorage.setItem(this.key, 'dismissed');
                this.visible = false;
                this.open = false;
            }
        }"
        x-show="visible"
        x-transition
        class="fixed bottom-24 right-4 z-[55] print:hidden"
    >
        <div class="relative">
            <button
                type="button"
                @click="open = !open"
                title="Subscription notice"
                aria-label="Open subscription notice"
                class="relative flex h-12 w-12 items-center justify-center rounded-full border shadow-lg ring-4 transition hover:scale-105 {{ $iconClasses }}"
            >
                <span class="absolute inset-0 rounded-full border-2 {{ $isTrialNotice ? 'border-blue-400/50' : ($isDangerNotice ? 'border-red-500/80' : ($isSuccessNotice ? 'border-green-500/50' : 'border-orange-500/60')) }} animate-ping"></span>
                <span data-lucide="{{ $noticeIcon }}" class="relative h-5 w-5"></span>
            </button>

            <div
                x-cloak
                x-show="open"
                x-transition
                @click.outside="open = false"
                class="absolute bottom-14 right-0 w-[min(20rem,calc(100vw-2rem))] rounded-lg border p-4 shadow-xl {{ $panelClasses }}"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md {{ $isTrialNotice ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200' : ($isDangerNotice ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200' : ($isSuccessNotice ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-200')) }}">
                        <span data-lucide="{{ $noticeIcon }}" class="h-4 w-4"></span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ $noticeTitle }}</p>
                        <p class="mt-1 text-sm leading-5">{{ $billingBanner['message'] }}</p>

                        @if(Route::has('admin.billing.index') && auth()->user()?->role === 'super_admin')
                            <a href="{{ route('admin.billing.index') }}" class="mt-3 inline-flex h-8 items-center gap-2 rounded-md border border-current/20 px-2.5 text-xs font-medium hover:bg-black/5 dark:hover:bg-white/5">
                                <span data-lucide="payments" class="h-3.5 w-3.5"></span>
                                Open Billing
                            </a>
                        @endif
                    </div>

                    <button type="button" @click="open = false" title="Close" aria-label="Close subscription notice" class="shrink-0 rounded-md p-1 hover:bg-black/5 dark:hover:bg-white/10">
                        <span data-lucide="x" class="h-4 w-4"></span>
                    </button>
                </div>

                @if(! empty($billingBanner['dismissible']))
                    <div class="mt-3 border-t border-current/10 pt-3 text-right">
                        <button type="button" @click="dismiss()" aria-label="Dismiss billing notice" class="h-8 rounded-md px-2.5 text-xs font-medium hover:bg-black/5 dark:hover:bg-white/10">
                            Dismiss for now
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endisset
