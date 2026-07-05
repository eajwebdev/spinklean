@if(session('success') || session('error'))
    <div
        x-data="{
            show: false,
            init() {
                this.$nextTick(() => {
                    this.show = true;
                    setTimeout(() => this.show = false, 5200);
                });
            }
        }"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-3 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-1000"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-3 opacity-0"
        class="fixed bottom-4 right-4 z-[70] w-[min(24rem,calc(100vw-2rem))] rounded-md border bg-white p-3 shadow-lg dark:bg-gray-900 {{ session('success') ? 'border-green-200 text-green-800 dark:border-green-900' : 'border-red-200 text-red-800 dark:border-red-900' }}"
    >
        <div class="flex items-start gap-2">
            <span data-lucide="{{ session('success') ? 'sparkles' : 'bell' }}" class="mt-0.5 h-4 w-4 shrink-0"></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">{{ session('success') ? 'Success' : 'Attention' }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ session('success') ?: session('error') }}</p>
            </div>
            <button type="button" @click="show = false" class="rounded-sm p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                <span data-lucide="x" class="h-4 w-4"></span>
            </button>
        </div>
    </div>
@endif
