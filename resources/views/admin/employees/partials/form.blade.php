<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @if(auth()->user()->isAdmin())
            <label class="text-sm font-medium">Branch
                <select name="branch_id" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', $employee->branch_id) == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
        @endif

        <label class="text-sm font-medium">First Name
            <input name="first_name" value="{{ old('first_name', $employee->first_name) }}" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </label>

        <label class="text-sm font-medium">Last Name
            <input name="last_name" value="{{ old('last_name', $employee->last_name) }}" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </label>

        <label class="text-sm font-medium">Phone Number
            <input name="phone" value="{{ old('phone', $employee->phone) }}" class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </label>

        <label class="text-sm font-medium">Username
            <input name="username" value="{{ old('username', $employee->username) }}" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </label>

        <label class="text-sm font-medium">Password
            <input type="password" name="password" autocomplete="new-password" placeholder="{{ $method === 'POST' ? 'Default: password123' : 'Leave blank to keep current password' }}" class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </label>

        <label class="inline-flex h-9 items-center gap-2 text-sm text-muted md:col-span-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $employee->status !== 'inactive')) class="rounded border-border text-primary">
            Active employee
        </label>
    </div>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="flex justify-end">
        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">
            Save Employee
        </button>
    </div>
</form>
