@php
    $menuItems = auth()->user()?->accessibleMenuItems() ?? [];
    $menuSections = [
        'dashboard' => 'Operations',
        'payments' => 'Cash Management',
        'employees' => 'Staff',
        'reports' => 'Management',
    ];
    $currentSection = null;
@endphp

<div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/45 xl:hidden" @click="sidebarOpen = false"></div>

<aside
    class="app-sidebar fixed inset-y-0 left-0 z-50 flex h-screen w-64 -translate-x-full flex-col overflow-hidden border-r shadow-xl transition-transform duration-200 xl:shadow-none {{ $sidebarAutoCollapsed ? 'xl:-translate-x-full' : 'xl:translate-x-0' }}"
    :class="sidebarVisible ? '!translate-x-0' : '!-translate-x-full'"
>
    <div class="app-sidebar-header relative flex h-14 items-center justify-between border-b px-3">
        <div class="flex min-w-0 items-center gap-2">
            <div class="app-sidebar-logo flex h-8 w-8 shrink-0 items-center justify-center rounded-md border">
                <img src="{{ $appBusinessLogo }}" alt="{{ $appBusinessName }} logo" class="h-6 w-6 object-contain">
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-semibold leading-tight text-white">{{ $appSystemName }}</h1>
                <p class="app-sidebar-subtle truncate text-[11px]">{{ $appBusinessName }}</p>
            </div>
        </div>

        <button type="button" class="app-sidebar-close rounded-md p-1.5 transition" @click="closeSidebar()">
            <span data-lucide="x" class="h-4 w-4"></span>
        </button>
    </div>

    <div class="app-sidebar-branch relative border-b px-3 py-3">
        <div class="app-sidebar-branch-card rounded-lg border px-3 py-2 shadow-sm">
            <p class="truncate text-xs font-medium text-slate-100">{{ auth()->user()->branch?->name ?? 'All Branches' }}</p>
            <p class="app-sidebar-subtle truncate text-[11px]">{{ str_replace('_', ' ', auth()->user()->role ?? '') }}</p>
        </div>
    </div>

    <nav class="relative flex-1 space-y-0.5 overflow-y-auto p-2">
        @foreach($menuItems as $key => $item)
            @continue(! Route::has($item['route']))

            @php
                $active = request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route']));
                $section = $menuSections[$key] ?? null;
            @endphp

            @if($section && $section !== $currentSection)
                @php($currentSection = $section)
                <p class="app-sidebar-section px-2.5 pb-1 pt-3 text-[10px] font-semibold uppercase tracking-wider first:pt-1">{{ $section }}</p>
            @endif

            <a
                href="{{ route($item['route']) }}"
                class="group flex h-9 items-center gap-2 rounded-md px-2.5 text-sm font-medium transition {{ $active ? 'app-sidebar-menu-link-active' : 'app-sidebar-menu-link' }}"
            >
                <span data-lucide="{{ $item['icon'] ?? 'dashboard' }}" class="h-4 w-4 shrink-0"></span>
                <span class="min-w-0 truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
</aside>
