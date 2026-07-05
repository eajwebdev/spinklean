<!DOCTYPE html>
<html lang="en" x-data x-init="$store.theme.init()" class="scroll-smooth" style="--color-primary: {{ $appPrimaryColor }};">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Login - {{ $appBusinessName }}</title>
    <link rel="icon" href="{{ $appBusinessLogo }}">
    <link rel="apple-touch-icon" href="{{ $appBusinessLogo }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        window.appDarkModeDefault = @js($appDarkModeDefault);
        window.appPrimaryColor = @js($appPrimaryColor);
    </script>
</head>

<body class="min-h-screen bg-smoke text-dark dark:bg-gray-950 dark:text-gray-100">
    <main class="flex min-h-screen items-center justify-center px-4 py-8">
        <div x-data="{ showPassword: false, loading: false }" class="w-full max-w-md rounded-lg border border-border bg-white p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <img src="{{ $appBusinessLogo }}" alt="{{ $appBusinessName }} logo" class="h-12 w-12 object-contain">
                </div>
                <div class="mb-3 inline-flex items-center gap-2 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-primary dark:border-gray-800 dark:bg-gray-950">
                    <span data-lucide="attendance" class="h-3.5 w-3.5"></span>
                    Attendance tracking
                </div>
                <h1 class="text-xl font-semibold tracking-normal">Employee Time Clock</h1>
                <p class="mt-1 text-sm text-muted">Login to clock in or clock out with a proof photo.</p>
            </div>

            <form method="POST" action="{{ route('attendance.login.submit') }}" class="space-y-4" x-on:submit="loading = true">
                @csrf

                <div>
                    <label for="login" class="mb-1.5 block text-sm font-medium">Employee Username</label>
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
                        <button type="button" x-on:click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex items-center gap-1 px-3 text-xs font-medium text-primary">
                            <span x-show="!showPassword" data-lucide="eye" class="h-4 w-4"></span>
                            <span x-show="showPassword" data-lucide="eyeOff" class="h-4 w-4"></span>
                            <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" x-bind:disabled="loading" class="flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-white shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/25 disabled:cursor-wait disabled:opacity-80">
                    <span x-show="!loading" class="inline-flex items-center gap-2">
                        <span data-lucide="login" class="h-4 w-4"></span>
                        Continue to Kiosk
                    </span>
                    <span x-cloak x-show="loading" class="inline-flex items-center gap-2">
                        <span data-lucide="loader" class="h-4 w-4 animate-spin"></span>
                        Signing in...
                    </span>
                </button>
            </form>
        </div>
    </main>

    @include('partials.alerts')
</body>
</html>
