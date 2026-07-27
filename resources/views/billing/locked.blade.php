<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Payment Required</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-surface text-dark dark:text-gray-100">
    <main class="flex min-h-screen items-center justify-center p-4">
        <section class="w-full max-w-md rounded-lg border border-border bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-300">
                <span data-lucide="lock" class="h-6 w-6"></span>
            </div>
            <h1 class="text-xl font-semibold">Subscription Payment Required</h1>
            <p class="mt-2 text-sm text-muted">{{ $message ?? 'Your subscription is overdue. Please pay to continue using the system.' }}</p>

            @if(! empty($record))
                <div class="mt-5 rounded-lg border border-border bg-smoke p-4 text-left text-sm dark:border-gray-800 dark:bg-gray-950">
                    <div class="flex items-center justify-between">
                        <span class="text-muted">Branch</span>
                        <span class="font-medium">{{ $branch->name ?? '—' }}</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-muted">Period</span>
                        <span class="font-medium">{{ $record->periodLabel() }}</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-muted">Due date</span>
                        <span class="font-medium">{{ $record->due_date?->format('M d, Y') ?? '—' }}</span>
                    </div>
                    @if(($daysPastDue ?? 0) > 0)
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-muted">Overdue by</span>
                            <span class="font-medium text-red-600 dark:text-red-400">{{ $daysPastDue }} day{{ $daysPastDue === 1 ? '' : 's' }}</span>
                        </div>
                    @endif
                    <div class="mt-3 flex items-center justify-between border-t border-border pt-3 dark:border-gray-800">
                        <span class="text-muted">Amount due</span>
                        <span class="text-lg font-semibold">{{ $currency ?? 'PHP' }} {{ number_format((float) $record->amount, 2) }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.billing.pay.qr', $record) }}" class="mt-5">
                    @csrf
                    <button type="submit" class="flex h-11 w-full items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-white hover:opacity-90">
                        <span data-lucide="qr-code" class="h-4 w-4"></span>
                        Scan QR to Pay
                    </button>
                </form>
                <p class="mt-2 text-xs text-muted">Secured by PayMongo — scan with GCash, Maya, or any InstaPay bank app.</p>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="h-9 rounded-md border border-border px-4 text-sm font-medium hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                    Logout
                </button>
            </form>
        </section>
    </main>
</body>
</html>
