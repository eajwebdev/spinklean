@extends('layouts.app')

@section('page_title', 'Service Categories')

@section('content')
<div x-data="{ createOpen: false, editOpen: null }" class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-900">
                <span data-lucide="tag" class="h-3.5 w-3.5"></span>
                Services
            </div>
            <h1 class="text-xl font-semibold">Service Categories</h1>
            <p class="text-sm text-muted">Manage POS categories and their branch visibility.</p>
        </div>
        <button type="button" @click="createOpen = true" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white shadow-sm hover:opacity-90">
            <span data-lucide="plus" class="h-4 w-4"></span>
            Add Category
        </button>
    </div>

    @if(session('success'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Category Name</th>
                        <th class="px-4 py-3">Visibility</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Services</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($categories as $category)
                        <tr>
                            <td class="px-4 py-3 text-muted">{{ $category->sort_order }}</td>
                            <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                            <td class="px-4 py-3">
                                @if($category->visibility === 'all')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        <span data-lucide="globe" class="h-3 w-3"></span> Both Branches
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                        <span data-lucide="git-branch" class="h-3 w-3"></span> Branch Only
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-muted">{{ $category->branch?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted">{{ $category->services()->count() }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ $category->is_active ? 'inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="editOpen = {{ $category->id }}" title="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="settings" class="h-4 w-4"></span>
                                </button>
                                <form method="POST" action="{{ route('admin.service-categories.destroy', $category) }}" class="inline" x-data>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" x-on:click.prevent="Swal.fire({ title: 'Delete category?', text: 'Services in this category will become uncategorized.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Delete' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50">
                                        <span data-lucide="trash" class="h-4 w-4"></span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-muted">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create Modal --}}
    <div x-cloak x-show="createOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.outside="createOpen = false" class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add Category</h2>
                <button type="button" @click="createOpen = false" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            @include('admin.service-categories.partials.form', ['action' => route('admin.service-categories.store'), 'method' => 'POST', 'category' => new \App\Models\LaundryServiceCategory(['visibility' => 'all', 'sort_order' => 0, 'is_active' => true])])
        </div>
    </div>

    {{-- Edit Modals --}}
    @foreach($categories as $category)
        <div x-cloak x-show="editOpen === {{ $category->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="editOpen = null" class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Edit Category</h2>
                    <button type="button" @click="editOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
                @include('admin.service-categories.partials.form', ['action' => route('admin.service-categories.update', $category), 'method' => 'PUT', 'category' => $category])
            </div>
        </div>
    @endforeach
</div>
@endsection
