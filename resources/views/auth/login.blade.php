<!DOCTYPE html>
<html lang="en" x-data x-init="$store.theme.init()" class="scroll-smooth" style="--color-primary: {{ $appPrimaryColor }};">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ $appBusinessName }}</title>
    <link rel="icon" href="{{ $appBusinessLogo }}">
    <link rel="apple-touch-icon" href="{{ $appBusinessLogo }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        window.appDarkModeDefault = @js($appDarkModeDefault);
        window.appPrimaryColor = @js($appPrimaryColor);
    </script>
</head>

<body
    class="min-h-screen overflow-hidden bg-smoke text-dark dark:bg-gray-950 dark:text-gray-100"
    x-data="{ mx: 0, my: 0 }"
    x-on:mousemove.window="mx = (($event.clientX / window.innerWidth) - 0.5) * 2; my = (($event.clientY / window.innerHeight) - 0.5) * 2"
    x-on:mouseleave.window="mx = 0; my = 0"
    style="--bubble-x: 0; --bubble-y: 0;"
    x-bind:style="`--bubble-x: ${mx}; --bubble-y: ${my}; --color-primary: ${window.appPrimaryColor || '#2E7D32'};`"
>
    <div class="laundry-login-bg fixed inset-0 overflow-hidden">
        <div class="laundry-login-wash absolute inset-0"></div>
        <div class="laundry-wave laundry-wave-one"></div>
        <div class="laundry-wave laundry-wave-two"></div>
        <div class="laundry-wave laundry-wave-three"></div>

        @foreach([
            ['x' => 5, 'y' => 14, 's' => 4.8, 'd' => .1, 'p' => 11],
            ['x' => 9, 'y' => 48, 's' => 2.4, 'd' => 1.1, 'p' => 18],
            ['x' => 14, 'y' => 76, 's' => 3.1, 'd' => 2.2, 'p' => 9],
            ['x' => 18, 'y' => 25, 's' => 1.7, 'd' => .6, 'p' => 24],
            ['x' => 24, 'y' => 62, 's' => 5.6, 'd' => 1.8, 'p' => 13],
            ['x' => 31, 'y' => 12, 's' => 2.1, 'd' => 2.6, 'p' => 20],
            ['x' => 38, 'y' => 83, 's' => 1.5, 'd' => .4, 'p' => 28],
            ['x' => 45, 'y' => 18, 's' => 3.8, 'd' => 1.5, 'p' => 15],
            ['x' => 51, 'y' => 68, 's' => 2.7, 'd' => 2.9, 'p' => 22],
            ['x' => 58, 'y' => 9, 's' => 1.4, 'd' => 3.5, 'p' => 30],
            ['x' => 63, 'y' => 43, 's' => 6.2, 'd' => .8, 'p' => 10],
            ['x' => 69, 'y' => 78, 's' => 2.2, 'd' => 2.1, 'p' => 25],
            ['x' => 74, 'y' => 19, 's' => 3.2, 'd' => 1.2, 'p' => 17],
            ['x' => 80, 'y' => 56, 's' => 1.8, 'd' => 3.1, 'p' => 29],
            ['x' => 86, 'y' => 11, 's' => 4.2, 'd' => .3, 'p' => 14],
            ['x' => 91, 'y' => 71, 's' => 5.4, 'd' => 2.7, 'p' => 12],
            ['x' => 94, 'y' => 35, 's' => 2.5, 'd' => 1.7, 'p' => 26],
            ['x' => 35, 'y' => 47, 's' => 1.2, 'd' => 3.9, 'p' => 34],
            ['x' => 72, 'y' => 88, 's' => 1.3, 'd' => 4.4, 'p' => 32],
            ['x' => 22, 'y' => 88, 's' => 1.1, 'd' => 3.4, 'p' => 36],
        ] as $bubble)
            <div
                class="laundry-bubble"
                style="left: {{ $bubble['x'] }}%; top: {{ $bubble['y'] }}%; width: {{ $bubble['s'] }}rem; height: {{ $bubble['s'] }}rem; --delay: {{ $bubble['d'] }}s; --parallax: {{ $bubble['p'] }}px;"
            ></div>
        @endforeach
    </div>

    <main class="relative z-10 flex min-h-screen items-center justify-center px-4 py-8">
        <div
            x-data="{ showPassword: false, loading: false, remember: false }"
            class="w-full max-w-md rounded-lg border border-white/70 bg-white/90 p-6 shadow-2xl backdrop-blur-xl dark:border-gray-700/70 dark:bg-gray-900/88"
        >
            <div class="mb-6 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <img
                        src="{{ $appBusinessLogo }}"
                        alt="{{ $appBusinessName }} logo"
                        class="h-12 w-12 object-contain"
                    >
                </div>
                <div class="mb-3 inline-flex items-center gap-2 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-primary dark:border-gray-800 dark:bg-gray-950">
                    <span data-lucide="laundry" class="h-3.5 w-3.5"></span>
                    Laundry operations login
                </div>
                <!-- <h1 class="text-xl font-semibold tracking-normal">{{ $appBusinessName }}</h1> -->
                <p class="mt-1 text-sm text-muted">Fresh loads, clean records, and branch work in one place.</p>
            </div>

            <form method="POST" action="{{ route('login.submit') }}" class="space-y-4" x-on:submit="loading = true">
                @csrf

                <div>
                    <label for="login" class="mb-1.5 block text-sm font-medium">Username or Email</label>
                    <input
                        id="login"
                        name="login"
                        type="text"
                        value="{{ old('login') }}"
                        autocomplete="username"
                        autofocus
                        required
                        class="h-10 w-full rounded-md border border-border bg-white px-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-gray-700 dark:bg-gray-950"
                    >
                    @error('login')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium">Password</label>
                    <div class="relative">
                        <input
                            id="password"
                            name="password"
                            x-bind:type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            class="h-10 w-full rounded-md border border-border bg-white px-3 pr-16 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-gray-700 dark:bg-gray-950"
                        >
                        <button
                            type="button"
                            x-on:click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 flex items-center gap-1 px-3 text-xs font-medium text-primary"
                        >
                            <span x-show="!showPassword" data-lucide="eye" class="h-4 w-4"></span>
                            <span x-show="showPassword" data-lucide="eyeOff" class="h-4 w-4"></span>
                            <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-muted">
                        <input type="checkbox" name="remember" value="1" x-model="remember" class="sr-only">
                        <span class="flex h-5 w-9 items-center rounded-full p-0.5 transition" :class="remember ? 'bg-primary' : 'bg-gray-300'">
                            <span class="h-4 w-4 rounded-full bg-white transition" :class="remember ? 'translate-x-4' : 'translate-x-0'"></span>
                        </span>
                        Remember me
                    </label>

                    <button type="button" x-on:click="$store.theme.toggle()" class="text-sm font-medium text-primary">
                        Theme
                    </button>
                </div>

                <button
                    type="submit"
                    x-bind:disabled="loading"
                    class="flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-white shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/25 disabled:cursor-wait disabled:opacity-80"
                >
                    <span x-show="!loading" class="inline-flex items-center gap-2">
                        <span data-lucide="login" class="h-4 w-4"></span>
                        Login
                    </span>
                    <span x-cloak x-show="loading" class="inline-flex items-center gap-2">
                        <span data-lucide="loader" class="h-4 w-4 animate-spin"></span>
                        Signing in...
                    </span>
                </button>
            </form>

            <div class="mt-5 text-center">
                <a href="{{ route('attendance.login') }}" class="text-sm font-medium text-primary">Employee attendance login</a>
            </div>

        </div>
    </main>

    @include('partials.alerts')
</body>
</html>

