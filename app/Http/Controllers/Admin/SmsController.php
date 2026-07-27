<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Support\Activity;
use App\Support\SmsNotifier;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function compose(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->isAdmin();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $branchId = $canChooseBranch ? ($request->integer('branch_id') ?: null) : $user->branch_id;

        $customers = Customer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->when(! $canChooseBranch, fn ($query) => $query->where('branch_id', $user->branch_id))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'branch_id']);

        return view('admin.sms.compose', compact('customers', 'branches', 'canChooseBranch', 'branchId'));
    }

    public function send(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'recipient' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:670'],
        ]);

        $customer = ! empty($validated['customer_id']) ? Customer::find($validated['customer_id']) : null;

        // Branch staff may only message customers of their own branch.
        if ($customer && ! $user->isAdmin() && (int) $customer->branch_id !== (int) $user->branch_id) {
            abort(403);
        }

        $branchId = $customer?->branch_id ?? $user->branch_id;

        $log = SmsNotifier::sendManual($validated['recipient'], $validated['message'], $customer?->id, $branchId);
        $ok = $log->status === 'sent';

        Activity::log($request, 'sms_composed', null, [
            'recipient' => $log->recipient,
            'customer_id' => $customer?->id,
            'status' => $log->status,
        ]);

        return back()
            ->with($ok ? 'success' : 'error', $ok
                ? 'SMS sent to '.$log->recipient.'.'
                : ($log->response ?: 'The SMS provider did not accept the message.'))
            ->withInput();
    }
}
