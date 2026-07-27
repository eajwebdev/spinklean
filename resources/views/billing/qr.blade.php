<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan to Pay — Subscription</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-surface text-dark dark:text-gray-100">
    <main class="flex min-h-screen items-center justify-center p-4">
        <section class="w-full max-w-md rounded-lg border border-border bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                <span data-lucide="qr-code" class="h-6 w-6"></span>
            </div>
            <h1 class="text-lg font-semibold">Scan to Pay</h1>
            <p class="mt-1 text-sm text-muted">Open GCash, Maya, or any bank app, then scan this QR Ph code.</p>

            <div class="mt-4 rounded-xl border border-border bg-white p-3 dark:border-gray-700">
                <img src="{{ $qrImage }}" alt="QR Ph payment code" class="mx-auto h-64 w-64 object-contain">
            </div>

            <div class="mt-4 rounded-lg border border-border bg-smoke p-4 text-left text-sm dark:border-gray-800 dark:bg-gray-950">
                <div class="flex items-center justify-between">
                    <span class="text-muted">Branch</span>
                    <span class="font-medium">{{ $branch->name ?? '—' }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-muted">Period</span>
                    <span class="font-medium">{{ $record->periodLabel() }}</span>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-border pt-3 dark:border-gray-800">
                    <span class="text-muted">Amount</span>
                    <span class="text-lg font-semibold">{{ $currency ?? 'PHP' }} {{ number_format((float) $record->amount, 2) }}</span>
                </div>
            </div>

            <div id="pay-status" class="mt-4 flex items-center justify-center gap-2 text-sm text-muted">
                <span class="inline-block h-2.5 w-2.5 animate-ping rounded-full bg-amber-500"></span>
                <span id="pay-status-text">Waiting for your payment…</span>
            </div>

            <p class="mt-2 text-xs text-muted">This screen updates automatically once your payment goes through. The QR expires in ~30 minutes.</p>

            <div class="mt-5 flex items-center justify-center gap-2">
                <a href="{{ route('dashboard') }}" class="h-9 rounded-md border border-border px-4 text-sm font-medium hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                    Back
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="h-9 rounded-md border border-border px-4 text-sm font-medium hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                        Logout
                    </button>
                </form>
            </div>
        </section>
    </main>

    <script>
        (function () {
            const statusUrl = @json(route('admin.billing.pay.status', $record));
            const statusText = document.getElementById('pay-status-text');
            const statusWrap = document.getElementById('pay-status');
            let stopped = false;

            async function poll() {
                if (stopped) return;
                try {
                    const res = await fetch(statusUrl, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (data.paid) {
                            stopped = true;
                            statusWrap.innerHTML =
                                '<span class="inline-flex items-center gap-2 font-semibold text-green-600 dark:text-green-400">'
                                + '<span data-lucide="check-circle" class="h-5 w-5"></span> Payment received! Redirecting…</span>';
                            if (window.lucide) window.lucide.createIcons();
                            setTimeout(function () {
                                window.location = data.redirect || '{{ route('dashboard') }}';
                            }, 1500);
                            return;
                        }
                    }
                } catch (e) {
                    // Network hiccup — keep polling.
                }
                setTimeout(poll, 4000);
            }

            setTimeout(poll, 4000);
        })();
    </script>
</body>
</html>
