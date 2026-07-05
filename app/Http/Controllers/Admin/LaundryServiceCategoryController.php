<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\LaundryServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaundryServiceCategoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeServiceCategoriesAccess();

        $categories = LaundryServiceCategory::with('branch')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.service-categories.index', compact('categories', 'branches'));
    }

    public function store(Request $request)
    {
        $this->authorizeServiceCategoriesAccess();

        $validated = $request->validate($this->rules());
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['branch_id'] = $validated['visibility'] === 'branch' ? $validated['branch_id'] : null;

        LaundryServiceCategory::create($validated);

        return redirect()->route('admin.service-categories.index')->with('success', 'Category created successfully.');
    }

    public function update(Request $request, LaundryServiceCategory $serviceCategory)
    {
        $this->authorizeServiceCategoriesAccess();

        $validated = $request->validate($this->rules());
        $validated['is_active'] = $request->boolean('is_active');
        $validated['branch_id'] = $validated['visibility'] === 'branch' ? $validated['branch_id'] : null;

        $serviceCategory->update($validated);

        return redirect()->route('admin.service-categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(LaundryServiceCategory $serviceCategory)
    {
        $this->authorizeServiceCategoriesAccess();

        $serviceCategory->services()->update(['service_category_id' => null]);
        $serviceCategory->delete();

        return redirect()->route('admin.service-categories.index')->with('success', 'Category deleted.');
    }

    private function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:100'],
            'visibility' => ['required', Rule::in(['all', 'branch'])],
            'branch_id'  => ['nullable', 'exists:branches,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    private function authorizeServiceCategoriesAccess(): void
    {
        $user = auth()->user();
        abort_unless($user?->hasMenuAccess('service_categories'), 403);
    }
}
