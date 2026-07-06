@extends('layouts.app')

@section('page_title', 'System Settings')

@section('content')
<div 
    x-data="{ 
        tab: @js($canManageGlobal ? 'business' : 'branch'),
        logoPreview: null,
        vatEnabled: @js($settings->vat_enabled),
        smsEnabled: @js($settings->sms_enabled),
        smsProvider: @js(old('sms_provider', $settings->sms_provider ?: 'unisms')),
        darkDefault: @js($settings->dark_mode_default)
    }"
    class="space-y-4"
>
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-900">
                <span data-lucide="settings" class="h-3.5 w-3.5"></span>
                Business control panel
            </div>
            <h1 class="text-xl font-semibold tracking-normal">System Settings</h1>
            <p class="text-muted text-sm">{{ $canManageGlobal ? 'Configure global system settings and branch defaults.' : 'Configure branch settings for receipts and operations.' }}</p>
        </div>

        @if(!$settings->is_completed)
            <div class="rounded-md bg-amber-50 border border-amber-200 text-amber-700 px-3 py-2 text-sm font-medium">
                Business setup is incomplete.
            </div>
        @else
            <div class="rounded-md bg-green-50 border border-green-200 text-green-700 px-3 py-2 text-sm font-medium">
                Business setup completed.
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="bg-white dark:bg-gray-900 border border-border dark:border-gray-800 rounded-lg shadow-sm overflow-hidden">
            <div class="flex gap-1 overflow-x-auto border-b border-border bg-smoke p-2 dark:border-gray-800 dark:bg-gray-950">
                @if($canManageGlobal)
                    <button type="button" @click="tab = 'business'" class="inline-flex h-9 items-center gap-2 rounded-md px-3 text-sm font-medium transition" :class="tab === 'business' ? 'bg-white text-primary shadow-sm dark:bg-gray-900' : 'text-muted hover:bg-white/70 dark:hover:bg-gray-900'">
                        <span data-lucide="store" class="h-4 w-4"></span> Global
                    </button>
                @endif
                
                {{-- Branch tab - visible to both Admin and Super Admin --}}
                <button type="button" @click="tab = 'branch'" class="inline-flex h-9 items-center gap-2 rounded-md px-3 text-sm font-medium transition" :class="tab === 'branch' ? 'bg-white text-primary shadow-sm dark:bg-gray-900' : 'text-muted hover:bg-white/70 dark:hover:bg-gray-900'">
                    <span data-lucide="building" class="h-4 w-4"></span> Branch
                </button>
                
                {{-- Receipt tab - visible to both Admin and Super Admin --}}
                <button type="button" @click="tab = 'receipt'" class="inline-flex h-9 items-center gap-2 rounded-md px-3 text-sm font-medium transition" :class="tab === 'receipt' ? 'bg-white text-primary shadow-sm dark:bg-gray-900' : 'text-muted hover:bg-white/70 dark:hover:bg-gray-900'">
                    <span data-lucide="receipt" class="h-4 w-4"></span> Receipt
                </button>
                
                {{-- SMS tab - visible to both Admin and Super Admin --}}
                @if($canManageSms)
                    <button type="button" @click="tab = 'sms'" class="inline-flex h-9 items-center gap-2 rounded-md px-3 text-sm font-medium transition" :class="tab === 'sms' ? 'bg-white text-primary shadow-sm dark:bg-gray-900' : 'text-muted hover:bg-white/70 dark:hover:bg-gray-900'">
                        <span data-lucide="sms" class="h-4 w-4"></span> SMS/API
                    </button>
                @endif
                
                @if($canManageGlobal)
                    <button type="button" @click="tab = 'theme'" class="inline-flex h-9 items-center gap-2 rounded-md px-3 text-sm font-medium transition" :class="tab === 'theme' ? 'bg-white text-primary shadow-sm dark:bg-gray-900' : 'text-muted hover:bg-white/70 dark:hover:bg-gray-900'">
                        <span data-lucide="sparkles" class="h-4 w-4"></span> Theme
                    </button>
                @endif
            </div>

            <div class="p-4">
                @if($canChooseBranch)
                    <div class="mb-4 rounded-md border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                        <label class="mb-1.5 block text-sm font-medium">Editing Branch Settings</label>
                        <select onchange="window.location='{{ route('admin.settings.edit') }}?branch_id=' + this.value" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                            @foreach($branches as $optionBranch)
                                <option value="{{ $optionBranch->id }}" @selected($branch->id === $optionBranch->id)>{{ $optionBranch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                @endif

                @if($canChooseBranch)
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                @endif

                @if($canManageGlobal)
                <div x-show="tab === 'business'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Business Name</label>
                        <input name="business_name" value="{{ old('business_name', $settings->business_name) }}" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm" required>
                        @error('business_name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Business Email</label>
                        <input type="email" name="business_email" value="{{ old('business_email', $settings->business_email) }}" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Contact Number</label>
                        <input name="contact_number" value="{{ old('contact_number', $settings->contact_number) }}" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm" required>
                        @error('contact_number') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Currency</label>
                        <input name="currency" value="{{ old('currency', $settings->currency ?? 'PHP') }}" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm" required>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium mb-2">Business Address</label>
                        <textarea name="business_address" rows="3" class="w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm" required>{{ old('business_address', $settings->business_address) }}</textarea>
                        @error('business_address') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium mb-2">Business Logo</label>
                        <input 
                            type="file" 
                            name="business_logo" 
                            accept="image/*"
                            @change="logoPreview = URL.createObjectURL($event.target.files[0])"
                            class="w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm"
                        >

                        <div class="mt-4">
                            <img 
                                x-show="logoPreview"
                                :src="logoPreview"
                                class="h-16 w-16 object-contain rounded-md border border-border"
                            >

                            <img
                                x-show="!logoPreview"
                                src="{{ $appBusinessLogo }}"
                                class="h-16 w-16 object-contain rounded-md border border-border"
                            >
                        </div>
                    </div>

                    <div class="lg:col-span-2 rounded-md border border-border p-3 dark:border-gray-700">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="vat_enabled" value="1" x-model="vatEnabled" class="rounded border-border text-primary">
                            <span class="text-sm font-medium">Enable VAT / Tax</span>
                        </label>
                        <div x-show="vatEnabled" class="mt-3 max-w-xs">
                            <label class="block text-sm font-medium mb-2">VAT Rate (%)</label>
                            <input type="number" step="0.01" name="vat_rate" value="{{ old('vat_rate', $settings->vat_rate) }}" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm">
                        </div>
                    </div>
                </div>
                @endif

                {{-- Branch Tab - Visible to both Admin and Super Admin --}}
                <div x-show="tab === 'branch'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Branch Name</label>
                        <input name="branch_name" value="{{ old('branch_name', $branch->name) }}" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Branch Code</label>
                        <input name="branch_code" value="{{ old('branch_code', $branch->code) }}" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Branch Contact</label>
                        <input name="branch_contact" value="{{ old('branch_contact', $branch->contact_number) }}" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm">
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium mb-2">Branch Type</label>
                        <select name="branch_type" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm" required>
                            <option value="full_service" @selected(old('branch_type', $branch->branch_type ?? 'full_service') === 'full_service')>Full Service - with production/machines</option>
                            <option value="pickup_dropoff" @selected(old('branch_type', $branch->branch_type ?? 'full_service') === 'pickup_dropoff')>Pickup & Drop-off Only - no machines</option>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium mb-2">Branch Address</label>
                        <textarea name="branch_address" rows="3" class="w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">{{ old('branch_address', $branch->address) }}</textarea>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium mb-2">Washing Machines</label>
                        <input type="number" min="0" max="100" name="machine_count" value="{{ old('machine_count', $branch->machine_count ?? 0) }}" placeholder="5" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm">
                        <p class="mt-1 text-xs text-muted">Cycle Monitoring will show this many machine choices and prevent assigning an active machine twice.</p>
                    </div>

                    <div class="lg:col-span-2">
                        <p class="mb-3 text-sm font-medium">Operating Hours</p>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                                @php($openValue = old("operating_hours.$day.open", data_get($branchSetting->operating_hours, "$day.open", '08:00')))
                                @php($closeValue = old("operating_hours.$day.close", data_get($branchSetting->operating_hours, "$day.close", '18:00')))
                                <div class="rounded-md border border-border p-3 dark:border-gray-700">
                                    <p class="mb-2 text-sm font-semibold">{{ ucfirst($day) }}</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="operating_hours[{{ $day }}][open]" aria-label="{{ ucfirst($day) }} opening time" class="h-10 rounded-lg border border-border bg-white px-2 text-sm dark:border-gray-700 dark:bg-gray-950">
                                            @for($minutes = 0; $minutes < 1440; $minutes += 30)
                                                @php($value = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60))
                                                <option value="{{ $value }}" @selected($openValue === $value)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $value)->format('h:i A') }}</option>
                                            @endfor
                                        </select>
                                        <select name="operating_hours[{{ $day }}][close]" aria-label="{{ ucfirst($day) }} closing time" class="h-10 rounded-lg border border-border bg-white px-2 text-sm dark:border-gray-700 dark:bg-gray-950">
                                            @for($minutes = 0; $minutes < 1440; $minutes += 30)
                                                @php($value = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60))
                                                <option value="{{ $value }}" @selected($closeValue === $value)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $value)->format('h:i A') }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Receipt Tab - Visible to both Admin and Super Admin --}}
                <div x-show="tab === 'receipt'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Receipt Header</label>
                        <textarea name="receipt_header" rows="5" class="w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">{{ old('receipt_header', $branchSetting->receipt_header) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Receipt Footer</label>
                        <textarea name="receipt_footer" rows="5" class="w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">{{ old('receipt_footer', $branchSetting->receipt_footer) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Job Order Prefix</label>
                        <input name="job_order_prefix" value="{{ old('job_order_prefix', $branchSetting->job_order_prefix) }}" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Invoice Prefix</label>
                        <input name="invoice_prefix" value="{{ old('invoice_prefix', $branchSetting->invoice_prefix) }}" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm">
                    </div>
                </div>

                {{-- SMS Tab - visible to both Admin and Super Admin --}}
                @if($canManageSms)
                @php($smsTemplateDefaults = \App\Models\SystemSetting::defaultSmsTemplates())
                @php($smsLimit = 160)
                <div x-show="tab === 'sms'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="lg:col-span-2">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="sms_enabled" value="1" x-model="smsEnabled" class="rounded border-border text-primary">
                            <span class="text-sm font-medium">Enable SMS Notifications</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">SMS Provider</label>
                        <select name="sms_provider" x-model="smsProvider" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm">
                            <option value="unisms">UniSMS</option>
                        </select>
                    </div>

                    <div x-show="smsProvider === 'unisms'">
                        <label class="block text-sm font-medium mb-2">UniSMS API Secret Key</label>
                        <input type="password" name="sms_api_key" value="{{ old('sms_api_key', $settings->sms_api_key) }}" autocomplete="new-password" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm">
                    </div>

                    <div x-show="smsProvider === 'unisms'">
                        <label class="block text-sm font-medium mb-2">UniSMS Sender ID</label>
                        <input name="unisms_sender_id" value="{{ old('unisms_sender_id', $settings->unisms_sender_id) }}" placeholder="Optional approved sender ID" class="w-full h-9 rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 text-sm">
                        <p class="mt-1 text-xs text-muted">Optional. Use only after UniSMS approves your business sender ID.</p>
                    </div>

                    <div class="lg:col-span-2 rounded-md border border-border p-3 text-xs text-muted dark:border-gray-700">
                        Available placeholders: <span class="font-medium text-foreground">{customer_name}</span>, <span class="font-medium text-foreground">{customer_phone}</span>, <span class="font-medium text-foreground">{job_order_number}</span>, <span class="font-medium text-foreground">{store_name}</span>, <span class="font-medium text-foreground">{branch_name}</span>, <span class="font-medium text-foreground">{status}</span>, <span class="font-medium text-foreground">{total}</span>, <span class="font-medium text-foreground">{balance}</span>.
                        <span class="mt-1 block">Each template is limited to <span class="font-medium text-foreground">{{ $smsLimit }} characters</span> to fit one SMS (1 credit). Placeholders expand when sent, so keep some room.</span>
                    </div>

                    @php($v = old('sms_template_order_received', $settings->sms_template_order_received ?: $smsTemplateDefaults['sms_template_order_received']))
                    <div class="lg:col-span-2" x-data="{ len: {{ mb_strlen($v) }} }">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-sm font-medium">Order Received Template</label>
                            <span class="text-[11px] tabular-nums" x-text="len + '/{{ $smsLimit }}'"
                                :class="len >= {{ $smsLimit }} ? 'text-red-600 dark:text-red-400 font-medium' : (len > {{ $smsLimit - 20 }} ? 'text-amber-600 dark:text-amber-400' : 'text-muted')"></span>
                        </div>
                        <textarea name="sms_template_order_received" rows="3" maxlength="{{ $smsLimit }}" x-on:input="len = $el.value.length" class="w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">{{ $v }}</textarea>
                    </div>

                    @php($v = old('sms_template_delivery_received', $settings->sms_template_delivery_received ?: $smsTemplateDefaults['sms_template_delivery_received']))
                    <div class="lg:col-span-2" x-data="{ len: {{ mb_strlen($v) }} }">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-sm font-medium">Delivery/Pickup Received Template</label>
                            <span class="text-[11px] tabular-nums" x-text="len + '/{{ $smsLimit }}'"
                                :class="len >= {{ $smsLimit }} ? 'text-red-600 dark:text-red-400 font-medium' : (len > {{ $smsLimit - 20 }} ? 'text-amber-600 dark:text-amber-400' : 'text-muted')"></span>
                        </div>
                        <textarea name="sms_template_delivery_received" rows="3" maxlength="{{ $smsLimit }}" x-on:input="len = $el.value.length" class="w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">{{ $v }}</textarea>
                    </div>

                    @php($v = old('sms_template_ready_for_pickup', $settings->sms_template_ready_for_pickup ?: $smsTemplateDefaults['sms_template_ready_for_pickup']))
                    <div x-data="{ len: {{ mb_strlen($v) }} }">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-sm font-medium">Ready for Pickup Template</label>
                            <span class="text-[11px] tabular-nums" x-text="len + '/{{ $smsLimit }}'"
                                :class="len >= {{ $smsLimit }} ? 'text-red-600 dark:text-red-400 font-medium' : (len > {{ $smsLimit - 20 }} ? 'text-amber-600 dark:text-amber-400' : 'text-muted')"></span>
                        </div>
                        <textarea name="sms_template_ready_for_pickup" rows="3" maxlength="{{ $smsLimit }}" x-on:input="len = $el.value.length" class="w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">{{ $v }}</textarea>
                    </div>

                    @php($v = old('sms_template_ready_for_delivery', $settings->sms_template_ready_for_delivery ?: $smsTemplateDefaults['sms_template_ready_for_delivery']))
                    <div x-data="{ len: {{ mb_strlen($v) }} }">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-sm font-medium">Ready for Delivery Template</label>
                            <span class="text-[11px] tabular-nums" x-text="len + '/{{ $smsLimit }}'"
                                :class="len >= {{ $smsLimit }} ? 'text-red-600 dark:text-red-400 font-medium' : (len > {{ $smsLimit - 20 }} ? 'text-amber-600 dark:text-amber-400' : 'text-muted')"></span>
                        </div>
                        <textarea name="sms_template_ready_for_delivery" rows="3" maxlength="{{ $smsLimit }}" x-on:input="len = $el.value.length" class="w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">{{ $v }}</textarea>
                    </div>

                    @php($v = old('sms_template_completed', $settings->sms_template_completed ?: $smsTemplateDefaults['sms_template_completed']))
                    <div class="lg:col-span-2" x-data="{ len: {{ mb_strlen($v) }} }">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-sm font-medium">Completed Template</label>
                            <span class="text-[11px] tabular-nums" x-text="len + '/{{ $smsLimit }}'"
                                :class="len >= {{ $smsLimit }} ? 'text-red-600 dark:text-red-400 font-medium' : (len > {{ $smsLimit - 20 }} ? 'text-amber-600 dark:text-amber-400' : 'text-muted')"></span>
                        </div>
                        <textarea name="sms_template_completed" rows="3" maxlength="{{ $smsLimit }}" x-on:input="len = $el.value.length" class="w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">{{ $v }}</textarea>
                    </div>

                    {{-- SMS Tester --}}
                    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-border bg-smoke dark:border-gray-700 dark:bg-gray-950"
                        x-data="smsTester(@js(route('admin.settings.sms-test')))">
                        <div class="flex items-start gap-3 border-b border-border px-4 py-3 dark:border-gray-800">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <span data-lucide="zap" class="h-4 w-4"></span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">Send Test SMS</p>
                                <p class="mt-0.5 text-xs text-muted">Sends a real message through the saved provider settings above. If you just changed the API key or sender ID, save first.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-3">
                            <div class="sm:col-span-1">
                                <label class="mb-1.5 block text-xs font-medium text-muted">Recipient Number</label>
                                <div class="relative">
                                    <span data-lucide="user" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"></span>
                                    <input type="text" x-model="phone" placeholder="09171234567" maxlength="20"
                                        @keydown.enter.prevent="send()"
                                        class="h-9 w-full rounded-md border border-border bg-white pl-9 pr-3 text-sm dark:border-gray-700 dark:bg-gray-900">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label class="block text-xs font-medium text-muted">Message</label>
                                    <span class="text-[11px] tabular-nums" x-text="`${message.length}/{{ $smsLimit }}`"
                                        :class="message.length >= {{ $smsLimit }} ? 'text-red-600 dark:text-red-400 font-medium' : (message.length > {{ $smsLimit - 20 }} ? 'text-amber-600 dark:text-amber-400' : 'text-muted')"></span>
                                </div>
                                <textarea x-model="message" rows="3" maxlength="{{ $smsLimit }}" placeholder="Type a test message…"
                                    class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900"></textarea>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-border px-4 py-3 dark:border-gray-800">
                            <p class="text-[11px] text-muted">A copy is saved to SMS logs.</p>
                            <button type="button" @click="send()" :disabled="sending"
                                class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">
                                <span data-lucide="zap" class="h-4 w-4" x-show="!sending"></span>
                                <span x-show="sending" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                <span x-text="sending ? 'Sending…' : 'Send Test SMS'"></span>
                            </button>
                        </div>
                    </div>
                </div>
                @endif

                @if($canManageGlobal)
                <div x-show="tab === 'theme'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Primary Color</label>
                        <input type="color" name="primary_color" value="{{ old('primary_color', $settings->primary_color) }}" class="h-9 w-full rounded-md border border-border dark:border-gray-700 bg-white dark:bg-gray-950 px-2">
                    </div>

                    <div>
                        <label class="flex items-center gap-3 mt-8">
                            <input type="checkbox" name="dark_mode_default" value="1" x-model="darkDefault" class="rounded border-border text-primary">
                            <span class="text-sm font-medium">Use Dark Mode by Default</span>
                        </label>
                    </div>
                </div>
                @endif
            </div>

            <div class="px-4 py-3 border-t border-border dark:border-gray-800 flex justify-end">
                <button type="submit" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white shadow-sm transition hover:opacity-90">
                    <span data-lucide="settings" class="h-4 w-4"></span>
                    Save Settings
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    // Registered before Alpine.start() (module scripts are deferred), so the
    // inline x-data="smsTester(...)" resolves this global on initialization.
    window.smsTester = (endpoint) => ({
        endpoint,
        phone: '',
        message: '',
        sending: false,

        async send() {
            if (this.sending) return;

            if (!this.phone.trim() || !this.message.trim()) {
                this.notify(false, 'Enter a phone number and a message first.');
                return;
            }

            this.sending = true;

            try {
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                            || '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ test_phone: this.phone, test_message: this.message }),
                });

                const data = await response.json().catch(() => ({}));
                const ok = response.ok && data.ok === true;

                this.notify(ok, data.message || (ok ? 'Test SMS sent.' : 'Failed to send test SMS.'));
            } catch (error) {
                this.notify(false, 'Network error while sending the test SMS.');
            } finally {
                this.sending = false;
            }
        },

        notify(ok, text) {
            window.toast.fire({ icon: ok ? 'success' : 'error', title: text });
        },
    });
</script>
@endsection
