<header class="sticky top-0 z-40 border-b border-border bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-950/95">
    <div class="flex h-14 items-center justify-between gap-3 px-3 lg:px-5">
        <div class="flex min-w-0 items-center gap-2">
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-border bg-white text-dark shadow-sm transition hover:bg-smoke dark:border-gray-800 dark:bg-gray-900 dark:text-white dark:hover:bg-gray-800" @click="toggleSidebar()" aria-label="Toggle sidebar menu">
                <span data-lucide="menu" class="h-5 w-5"></span>
            </button>

            <div class="min-w-0">
                <h2 class="truncate text-base font-semibold">@yield('page_title', 'Dashboard')</h2>
                @if(in_array(auth()->user()->role, ['branch_manager', 'cashier'], true))
                    <p class="hidden truncate text-xs text-muted sm:block">Welcome back, {{ auth()->user()->name ?? 'User' }}</p>
                @else
                    <p class="hidden truncate text-xs text-muted sm:block">Administration workspace</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            @php($billingNotices = collect($billingNotifications ?? []))
            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="relative flex h-9 w-9 items-center justify-center rounded-md border border-border bg-white transition hover:bg-smoke dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800"
                    aria-label="Subscription notifications"
                >
                    <span data-lucide="bell" class="h-4 w-4"></span>
                    @if($billingNotices->isNotEmpty())
                        <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-none text-white">{{ $billingNotices->count() }}</span>
                    @endif
                </button>

                <div
                    x-cloak
                    x-show="open"
                    x-transition
                    @click.outside="open = false"
                    class="absolute right-0 mt-2 w-80 overflow-hidden rounded-md border border-border bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex items-center justify-between border-b border-border px-3 py-2 dark:border-gray-800">
                        <p class="text-sm font-semibold">Subscription Notifications</p>
                        @if(Route::has('admin.billing.index') && auth()->user()?->role === 'super_admin')
                            <a href="{{ route('admin.billing.index') }}" class="text-xs font-medium text-primary hover:underline">Open</a>
                        @endif
                    </div>
                    <div class="max-h-80 overflow-y-auto p-2">
                        @forelse($billingNotices as $notice)
                            @php($noticeType = $notice['type'] ?? 'billing')
                            <div class="mb-1 rounded-md border px-3 py-2 text-sm last:mb-0 {{ $noticeType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-500/10 dark:text-emerald-200' : ($noticeType === 'danger' ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-200' : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-200') }}">
                                <div class="flex gap-2">
                                    <span data-lucide="{{ $noticeType === 'success' ? 'check' : ($noticeType === 'danger' ? 'alertTriangle' : 'bell') }}" class="mt-0.5 h-4 w-4 shrink-0"></span>
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ $notice['title'] ?? 'Subscription notice' }}</p>
                                        <p class="mt-0.5 text-xs leading-5 opacity-85">{{ $notice['message'] ?? '' }}</p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-3 py-8 text-center text-sm text-muted">No subscription notifications.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <button
                type="button"
                @click="$store.theme.toggle()"
                class="flex h-9 w-9 items-center justify-center rounded-md border border-border bg-white transition hover:bg-smoke dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800"
                aria-label="Toggle theme"
            >
                <span x-show="!$store.theme.dark" data-lucide="moon" class="h-4 w-4"></span>
                <span x-show="$store.theme.dark" data-lucide="sun" class="h-4 w-4"></span>
            </button>

            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-1.5 pr-2 transition hover:bg-smoke dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800">
                    <span class="flex h-6 w-6 items-center justify-center rounded bg-primary text-xs font-semibold text-white">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </span>
                    <span class="hidden max-w-28 truncate text-sm font-medium sm:block">{{ auth()->user()->name ?? 'User' }}</span>
                    <span data-lucide="chevronDown" class="hidden h-4 w-4 text-muted sm:block"></span>
                </button>

                <div
                    x-cloak
                    x-show="open"
                    x-transition
                    @click.outside="open = false"
                    class="absolute right-0 mt-2 w-56 rounded-md border border-border bg-white p-1 shadow-lg dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="px-2 py-2">
                        <p class="truncate text-sm font-medium">{{ auth()->user()->name ?? 'User' }}</p>
                        <p class="truncate text-xs text-muted">{{ auth()->user()->email ?? '' }}</p>
                    </div>

                    <div class="my-1 h-px bg-border dark:bg-gray-800"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex h-8 w-full items-center gap-2 rounded-sm px-2 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30">
                            <span data-lucide="logout" class="h-4 w-4"></span>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
