<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\SystemSetting;
use App\Support\Activity;
use App\Support\PublicUpload;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemSettingController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $settings = SystemSetting::current();
        $canManageGlobal = $user->isAdmin();
        $canManageSms = $user->isSuperAdmin();
        $canChooseBranch = $user->isAdmin();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $branch = $canChooseBranch
            ? Branch::query()->find($request->integer('branch_id')) ?? $branches->first()
            : $user->branch;

        if (! $branch) {
            $branch = Branch::firstOrCreate(
                ['code' => 'MAIN'],
                ['name' => 'Main Branch', 'is_active' => true]
            );
        }

        $branchSetting = BranchSetting::firstOrCreate(
            ['branch_id' => $branch->id],
            [
                'receipt_header' => $settings->receipt_header,
                'receipt_footer' => $settings->receipt_footer,
                'operating_hours' => $settings->operating_hours,
                'job_order_prefix' => $settings->job_order_prefix,
                'invoice_prefix' => $settings->invoice_prefix,
            ]
        );

        return view('admin.settings.edit', compact('settings', 'branch', 'branches', 'branchSetting', 'canManageGlobal', 'canManageSms', 'canChooseBranch'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $settings = SystemSetting::current();
        $canManageGlobal = $user->isAdmin();
        $canManageSms = $user->isSuperAdmin();
        $canChooseBranch = $user->isAdmin();
        $branch = $canChooseBranch
            ? Branch::query()->findOrFail($request->integer('branch_id'))
            : $user->branch;

        abort_unless($branch, 403);

        $rules = [
            'branch_id' => [$canChooseBranch ? 'required' : 'nullable', 'exists:branches,id'],
            'branch_name' => ['required', 'string', 'max:255'],
            'branch_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('branches', 'code')->ignore($branch->id),
            ],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'branch_contact' => ['nullable', 'string', 'max:50'],
            'branch_type' => ['nullable', Rule::in(['full_service', 'pickup_dropoff'])],
            'machine_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'receipt_header' => ['nullable', 'string'],
            'receipt_footer' => ['nullable', 'string'],
            'operating_hours' => ['nullable', 'array'],
            'operating_hours.*.open' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.close' => ['nullable', 'date_format:H:i'],
            'job_order_prefix' => ['nullable', 'string', 'max:20'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
        ];

        if ($canManageGlobal) {
            $rules = array_merge($rules, [
                'business_name' => ['required', 'string', 'max:255'],
                'business_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'business_email' => ['nullable', 'email', 'max:255'],
                'contact_number' => ['required', 'string', 'max:50'],
                'business_address' => ['required', 'string'],
                'currency' => ['required', 'string', 'max:10'],
                'vat_enabled' => ['nullable', 'boolean'],
                'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'primary_color' => ['required', 'string', 'max:20'],
                'dark_mode_default' => ['nullable', 'boolean'],
            ]);

            if ($canManageSms) {
                $rules = array_merge($rules, [
                    'sms_provider' => ['nullable', Rule::in(['unisms'])],
                    'sms_api_key' => ['nullable', 'string'],
                    'unisms_sender_id' => ['nullable', 'string', 'max:50'],
                    'sms_enabled' => ['nullable', 'boolean'],
                    'sms_template_order_received' => ['nullable', 'string', 'max:1000'],
                    'sms_template_delivery_received' => ['nullable', 'string', 'max:1000'],
                    'sms_template_ready_for_pickup' => ['nullable', 'string', 'max:1000'],
                    'sms_template_ready_for_delivery' => ['nullable', 'string', 'max:1000'],
                    'sms_template_completed' => ['nullable', 'string', 'max:1000'],
                ]);
            }
        }

        $validated = $request->validate($rules);

        if ($canManageGlobal && $request->hasFile('business_logo')) {
            if ($settings->business_logo) {
                PublicUpload::delete($settings->business_logo);
            }

            $validated['business_logo'] = PublicUpload::store($request->file('business_logo'), 'settings');
        }

        $branch->update([
            'name' => $validated['branch_name'],
            'code' => $validated['branch_code'],
            'address' => $validated['branch_address'] ?? null,
            'contact_number' => $validated['branch_contact'] ?? null,
            'branch_type' => $validated['branch_type'] ?? 'full_service',
            'latitude' => null,
            'longitude' => null,
            'attendance_radius_meters' => null,
            'machine_count' => $validated['machine_count'] ?? 0,
        ]);

        BranchSetting::updateOrCreate(
            ['branch_id' => $branch->id],
            [
                'receipt_header' => $validated['receipt_header'] ?? null,
                'receipt_footer' => $validated['receipt_footer'] ?? null,
                'operating_hours' => $request->input('operating_hours', []),
                'job_order_prefix' => $validated['job_order_prefix'] ?? null,
                'invoice_prefix' => $validated['invoice_prefix'] ?? null,
            ]
        );

        unset(
            $validated['branch_id'],
            $validated['branch_name'],
            $validated['branch_code'],
            $validated['branch_address'],
            $validated['branch_contact'],
            $validated['branch_type'],
            $validated['machine_count'],
            $validated['receipt_header'],
            $validated['receipt_footer'],
            $validated['operating_hours'],
            $validated['job_order_prefix'],
            $validated['invoice_prefix'],
        );

        if ($canManageGlobal) {
            $validated['vat_enabled'] = $request->boolean('vat_enabled');
            $validated['dark_mode_default'] = $request->boolean('dark_mode_default');
            if ($canManageSms) {
                $validated['sms_enabled'] = $request->boolean('sms_enabled');
            }

            $settings->fill($validated);
            $settings->is_completed = $settings->isComplete();
            $settings->save();

            // Clear view and application cache so updated logo and settings reflect immediately
            try {
                Artisan::call('view:clear');
                Artisan::call('cache:clear');
            } catch (\Throwable $e) {
                // Ignore failures here; cache clearing is best-effort
            }
        }

        Activity::log($request, $canManageGlobal ? 'global_settings_updated' : 'branch_settings_updated', $branch, [
            'branch' => $branch->name,
            'global' => $canManageGlobal ? 'yes' : 'no',
        ], $branch->id);

        return redirect()
            ->route('admin.settings.edit', ['branch_id' => $branch->id])
            ->with('success', 'System settings saved successfully.');
    }
}
