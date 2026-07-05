<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->job_order_number }} Receipt</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .receipt { box-shadow: none !important; border: 0 !important; }
        }
    </style>
</head>
<body class="bg-smoke text-dark">
    <main class="mx-auto min-h-screen max-w-md p-4">
        <div class="no-print mb-3 flex justify-end gap-2">
            <button onclick="window.print()" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white">Print</button>
        </div>

        @include('admin.job-orders.partials.receipt-card', compact('order', 'settings', 'branchSetting'))
    </main>
</body>
</html>
