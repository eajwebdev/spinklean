@props(['title'])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900']) }}>
    <div class="border-b border-border px-4 py-3 dark:border-gray-800">
        <h2 class="text-base font-semibold">{{ $title }}</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                <tr>{{ $head }}</tr>
            </thead>
            <tbody class="divide-y divide-border dark:divide-gray-800">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
