@php
    // Each popup (Add User, Edit <user>) has its own key, so a failed save only refills
    // and shows errors in the form that was actually submitted.
    $formKey = $method === 'POST' ? 'user-create' : 'user-edit-'.$user->id;
    $isSubmitted = old('_form') === $formKey;
    $value = fn (string $field, $default = null) => $isSubmitted ? old($field, $default) : $default;
@endphp
<form
    method="POST"
    action="{{ $action }}"
    autocomplete="off"
    x-data="userAccessForm({
        role: @js($value('role', $user->role ?: 'cashier')),
        access: @js(array_values($value('access', $user->access ?? []) ?? [])),
        presets: @js($roleAccessPresets),
    })"
    class="space-y-4"
>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    <input type="hidden" name="_form" value="{{ $formKey }}">

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-sm font-medium">Name</label>
            <input name="name" value="{{ $value('name', $user->name) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Username</label>
            <input name="username" value="{{ $value('username', $user->username) }}" required autocomplete="off" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Email <span class="font-normal text-muted">(optional)</span></label>
            <input type="email" name="email" value="{{ $value('email', $user->email) }}" autocomplete="off" placeholder="Optional email address" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Branch</label>
            <select name="branch_id" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                <option value="">All branches</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($value('branch_id', $user->branch_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">{{ $method !== 'POST' ? 'New Password' : 'Password' }} <span class="font-normal text-muted">{{ $method !== 'POST' ? '(leave blank to keep current)' : '(at least 6 characters)' }}</span></label>
            <input type="password" name="password" minlength="6" autocomplete="new-password" {{ $method === 'POST' ? 'required' : '' }} class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
            @if($isSubmitted)
                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @endif
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Confirm {{ $method !== 'POST' ? 'New ' : '' }}Password</label>
            <input type="password" name="password_confirmation" minlength="6" autocomplete="new-password" {{ $method === 'POST' ? 'required' : '' }} class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Role</label>
            <select name="role" x-model="role" @change="applyPreset(role)" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                @foreach($roles as $role)
                    <option value="{{ $role }}" @selected($value('role', $user->role) === $role)>{{ str_replace('_', ' ', ucfirst($role)) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Status</label>
            <select name="status" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                @foreach(['active', 'inactive', 'suspended'] as $status)
                    <option value="{{ $status }}" @selected($value('status', $user->status) === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-medium">Menu Access</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($roles as $presetRole)
                    <button type="button" @click="role = @js($presetRole); applyPreset(@js($presetRole)); refreshIcons()" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-2 text-xs font-medium hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-950">
                        <span data-lucide="check" class="h-3.5 w-3.5"></span>
                        {{ \App\Support\StatusBadge::label($presetRole) }}
                    </button>
                @endforeach
            </div>
        </div>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($menuItems as $key => $item)
                @continue(! empty($item['super_admin']) && ! auth()->user()->isSuperAdmin())
                <label class="flex items-center gap-2 rounded-md border border-border px-2.5 py-2 text-sm dark:border-gray-700">
                    <input
                        type="checkbox"
                        name="access[]"
                        value="{{ $key }}"
                        x-model="access"
                        class="rounded border-border text-primary"
                    >
                    <span>
                        {{ $item['label'] }}
                        @if(! empty($item['super_admin']))
                            <span class="text-xs text-muted">(Superadmin only)</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    @if($isSubmitted && $errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            <p class="font-semibold">Not saved. Please fix:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex justify-end">
        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">
            Save User
        </button>
    </div>
</form>

<script>
    function userAccessForm(config) {
        return {
            role: config.role,
            access: config.access,
            presets: config.presets,
            applyPreset(role) {
                this.access = [...(this.presets[role] || [])];
            },
            refreshIcons() {
                this.$nextTick(() => window.renderLucideIcons());
            },
        };
    }
</script>
