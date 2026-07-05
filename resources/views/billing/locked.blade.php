<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expired</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-surface text-dark dark:text-gray-100">
    <main class="flex min-h-screen items-center justify-center p-4">
        <section class="w-full max-w-md rounded-lg border border-border bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                <span data-lucide="lock" class="h-6 w-6"></span>
            </div>
            <h1 class="text-xl font-semibold">Subscription Expired</h1>
            <p class="mt-2 text-sm text-muted">{{ $message }}</p>
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
