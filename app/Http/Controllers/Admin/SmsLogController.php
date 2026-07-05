<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SmsLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SmsLogController extends Controller
{
    private const STATUSES = ['queued', 'sent', 'failed'];

    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->isAdmin();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $logs = SmsLog::query()
            ->with(['branch', 'customer'])
            ->when(! $canChooseBranch, fn ($query) => $query->where('branch_id', $user->branch_id))
            ->when($request->filled('branch_id') && $canChooseBranch, fn ($query) => $query->where('branch_id', $request->branch_id))
            ->when(in_array($request->status, self::STATUSES, true), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(fn ($query) => $query
                    ->where('recipient', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%")));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.sms-logs.index', [
            'branches' => $branches,
            'canChooseBranch' => $canChooseBranch,
            'logs' => $logs,
            'settings' => SystemSetting::current(),
            'statuses' => self::STATUSES,
        ]);
    }
}
