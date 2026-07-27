<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Under Maintenance</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-surface text-dark dark:text-gray-100">
    <main class="flex min-h-screen items-center justify-center p-4">
        <section class="w-full max-w-md rounded-lg border border-border bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-300">
                <span data-lucide="wrench" class="h-6 w-6"></span>
            </div>
            <h1 class="text-xl font-semibold">System Under Maintenance</h1>
            <p class="mt-2 text-sm text-muted">{{ $message }}</p>

            @if(! empty($since))
                <p class="mt-3 text-xs text-muted">In maintenance since {{ $since->format('M d, Y g:i A') }}.</p>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="mt-5">
                @csrf
                <button type="submit" class="h-9 rounded-md border border-border px-4 text-sm font-medium hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                    Logout
                </button>
            </form>
        </section>
    </main>
</body>
</html>
