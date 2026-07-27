@extends('layouts.app')

@section('page_title', 'Compose SMS')

@section('content')
@php
    $customerMap = $customers->mapWithKeys(fn ($c) => [$c->id => ['name' => $c->name, 'phone' => $c->phone]]);
@endphp

<div
    class="mx-auto max-w-2xl space-y-4"
    x-data="{
        customers: @js($customerMap),
        customerId: @js(old('customer_id', '')),
        recipient: @js(old('recipient', '')),
        message: @js(old('message', '')),
        pick() {
            const c = this.customers[this.customerId];
            if (c) this.recipient = c.phone || '';
        },
        get chars() { return this.message.length; },
        get unicode() { return [...this.message].some(c => c.charCodeAt(0) > 127); },
        get segments() {
            if (this.chars === 0) return 0;
            const per = this.unicode ? 70 : 160;
            return Math.ceil(this.chars / per);
        }
    }"
>
    <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-1 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
            <span data-lucide="smsLogs" class="h-3.5 w-3.5"></span>
            Send a real SMS
        </div>
        <h1 class="text-xl font-semibold tracking-normal">Compose SMS</h1>
        <p class="text-sm text-muted">Select a customer (or type a number), write your message, and send. This sends a real SMS through the saved provider settings.</p>
    </div>

    <form method="POST" action="{{ route('admin.sms.send') }}" class="space-y-4 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        @csrf

        <div>
            <label class="mb-1.5 block text-sm font-medium">Customer</label>
            <select name="customer_id" x-model="customerId" @change="pick()" class="h-10 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                <option value="">Select a customer (optional)</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} - {{ $c->phone }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-muted">Choosing a customer fills in their number. You can also just type a number below.</p>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Recipient Number</label>
            <div class="flex h-10 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-700 dark:bg-gray-950">
                <span data-lucide="user" class="h-4 w-4 text-muted"></span>
                <input name="recipient" x-model="recipient" type="text" inputmode="tel" placeholder="09171234567" required class="w-full bg-transparent text-sm outline-none">
            </div>
            @error('recipient') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label class="block text-sm font-medium">Message</label>
                <span class="text-xs text-muted">
                    <span x-text="chars"></span> chars ·
                    <span x-text="segments"></span> SMS<span x-show="unicode" class="text-amber-600 dark:text-amber-400"> · Unicode</span>
                </span>
            </div>
            <textarea name="message" x-model="message" rows="6" maxlength="670" required placeholder="Type your message..." class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"></textarea>
            @error('message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('admin.sms-logs.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-border bg-white px-4 text-sm font-medium hover:bg-smoke dark:border-gray-700 dark:bg-gray-950 dark:hover:bg-gray-900">
                <span data-lucide="smsLogs" class="h-4 w-4"></span>
                View SMS Logs
            </a>
            <button type="submit" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-5 text-sm font-medium text-white hover:opacity-90">
                <span data-lucide="send" class="h-4 w-4"></span>
                Send SMS
            </button>
        </div>
    </form>
</div>
@endsection
