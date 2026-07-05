<!DOCTYPE html>
<html lang="en" x-data x-init="$store.theme.init()" class="scroll-smooth" style="--color-primary: {{ $appPrimaryColor }};">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ $appPrimaryColor }}">
    <title>@yield('page_title', 'Dashboard') - {{ $appSystemName }}</title>
    <link rel="icon" href="{{ $appBusinessLogo }}">
    <link rel="apple-touch-icon" href="{{ $appBusinessLogo }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        window.appDarkModeDefault = @js($appDarkModeDefault);
        window.appPrimaryColor = @js($appPrimaryColor);
    </script>
</head>

<body class="app-surface text-dark dark:text-gray-100">
    @php($sidebarAutoCollapsed = request()->routeIs('admin.job-orders.create'))

    <div
        x-data="{
            sidebarOpen: false,
            desktopSidebarOpen: @js(! $sidebarAutoCollapsed),
            isDesktop: window.matchMedia('(min-width: 1280px)').matches,
            init() {
                const query = window.matchMedia('(min-width: 1280px)');
                const sync = () => {
                    this.isDesktop = query.matches;
                    if (this.isDesktop) {
                        this.sidebarOpen = false;
                    }
                };

                sync();
                query.addEventListener('change', sync);
            },
            toggleSidebar() {
                if (this.isDesktop) {
                    this.desktopSidebarOpen = ! this.desktopSidebarOpen;
                    return;
                }

                this.sidebarOpen = true;
            },
            closeSidebar() {
                if (this.isDesktop) {
                    this.desktopSidebarOpen = false;
                    return;
                }

                this.sidebarOpen = false;
            },
            get sidebarVisible() {
                return this.sidebarOpen || (this.isDesktop && this.desktopSidebarOpen);
            }
        }"
        class="min-h-screen flex"
    >

        @include('partials.sidebar')

        <div
            class="flex-1 flex min-w-0 flex-col transition-[padding] duration-200 {{ $sidebarAutoCollapsed ? 'xl:pl-0' : 'xl:pl-64' }}"
            :class="desktopSidebarOpen ? 'xl:!pl-64' : 'xl:!pl-0'"
        >
            @include('partials.topbar')

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                @include('partials.alerts')
                @include('partials.billing-banner')
                @unless(request()->routeIs('admin.job-orders.create'))
                    @include('partials.assistant-widget')
                @endunless

                {{ $slot ?? '' }}

                @yield('content')
            </main>

            @unless(trim($__env->yieldContent('hide_footer')))
                @include('partials.footer')
            @endunless
        </div>
    </div>
</body>
</html>
