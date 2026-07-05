<?php

namespace App\Http\Middleware;

use App\Models\BranchBillingRecord;
use App\Models\SystemTrialSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchBillingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        View::share('billingBanner', null);
        View::share('billingNotifications', []);

        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs('logout')) {
            return $next($request);
        }

        if (! Schema::hasTable('system_trial_settings') || ! Schema::hasTable('branch_billing_records')) {
            return $next($request);
        }

        $today = now();
        View::share('billingNotifications', $this->billingNotifications($user, $today));

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $trial = SystemTrialSetting::current();

        if ($trial->isActive()) {
            View::share('billingBanner', [
                'type' => 'trial',
                'message' => 'System Free Trial Active Until '.$trial->trial_end_date->format('M d, Y'),
            ]);

            return $next($request);
        }

        if (! $trial->shouldEnforceBilling()) {
            return $next($request);
        }

        if (! $user->branch_id) {
            if ($user->canManageAllBranches()) {
                return $next($request);
            }

            View::share('billingBanner', [
                'type' => 'danger',
                'dismissible' => false,
                'key' => 'billing-missing-branch-'.$user->id,
                'message' => 'Branch subscription has expired. Please contact your administrator to continue using the system.',
            ]);

            return $next($request);
        }

        $matchingRecords = BranchBillingRecord::query()
            ->where('branch_id', $user->branch_id)
            ->where(function ($query) use ($today) {
                $query
                    ->where(function ($query) use ($today) {
                        $query
                            ->whereDate('subscription_start_date', '<=', $today->toDateString())
                            ->whereDate('subscription_end_date', '>=', $today->toDateString());
                    })
                    ->orWhere(function ($query) use ($today) {
                        $query
                            ->whereNull('subscription_start_date')
                            ->whereNull('subscription_end_date')
                            ->where('billing_month', (int) $today->month)
                            ->where('billing_year', (int) $today->year);
                    });
            })
            ->orderByRaw("CASE status WHEN 'paid' THEN 0 WHEN 'unpaid' THEN 1 WHEN 'overdue' THEN 2 WHEN 'suspended' THEN 3 ELSE 4 END")
            ->latest('subscription_end_date')
            ->latest('due_date');

        $paidRecord = (clone $matchingRecords)->where('status', 'paid')->first();
        if ($paidRecord) {
            $daysUntilEnd = $paidRecord->subscription_end_date
                ? $today->copy()->startOfDay()->diffInDays($paidRecord->subscription_end_date->copy()->startOfDay(), false)
                : null;

            if ($daysUntilEnd !== null && $daysUntilEnd >= 0 && $daysUntilEnd <= 5) {
                View::share('billingBanner', [
                    'type' => 'billing',
                    'dismissible' => true,
                    'autoOpen' => true,
                    'key' => 'billing-upcoming-'.$paidRecord->id.'-'.$paidRecord->subscription_end_date->toDateString(),
                    'message' => 'System billing is paid for '.$paidRecord->periodLabel().'. Next billing is coming up in '.$daysUntilEnd.' day'.($daysUntilEnd === 1 ? '' : 's').'.',
                ]);

                return $next($request);
            }

            // Hide "Billing Paid" banner - only show overdue notices
            return $next($request);
        }

        $record = $matchingRecords->first();

        if (! $record) {
            View::share('billingBanner', [
                'type' => 'danger',
                'dismissible' => false,
                'key' => 'billing-missing-'.$user->branch_id.'-'.$today->year.'-'.$today->month,
                'message' => 'No active branch subscription was found for '.$today->format('F Y').'. Please contact your administrator to continue using the system.',
            ]);

            return $next($request);
        }

        if ($record->status === 'paid') {
            return $next($request);
        }

        if ($record->status === 'suspended') {
            View::share('billingBanner', [
                'type' => 'danger',
                'dismissible' => false,
                'key' => 'billing-'.$record->id.'-'.$record->status,
                'message' => 'Branch subscription has been suspended. Please contact your administrator to continue using the system.',
            ]);

            return $next($request);
        }

        if ($record->status === 'unpaid' && $record->due_date->toDateString() < $today->toDateString()) {
            $record->update(['status' => 'overdue']);
            $record->status = 'overdue';
        }

        $daysUntilDue = $record->due_date
            ? $today->copy()->startOfDay()->diffInDays($record->due_date->copy()->startOfDay(), false)
            : null;
        $isDueSoon = $record->status === 'unpaid' && $daysUntilDue !== null && $daysUntilDue >= 0 && $daysUntilDue <= 5;

        View::share('billingBanner', [
            'type' => $isDueSoon ? 'billing' : 'danger',
            'dismissible' => true,
            'autoOpen' => $isDueSoon,
            'key' => 'billing-'.$record->id.'-'.$record->status,
            'message' => $isDueSoon
                ? 'Your branch subscription for '.$record->periodLabel().' is due in '.$daysUntilDue.' day'.($daysUntilDue === 1 ? '' : 's').'. Please prepare your system billing payment.'
                : 'Your branch subscription for '.$record->periodLabel().' is '.str_replace('_', ' ', $record->status).'. Please contact your administrator.',
        ]);

        return $next($request);
    }

    private function billingNotifications($user, $today): array
    {
        if (! $user->branch_id && ! $user->canManageAllBranches()) {
            return [];
        }

        $query = BranchBillingRecord::query()
            ->with('branch:id,name')
            ->when(! $user->canManageAllBranches(), fn ($query) => $query->where('branch_id', $user->branch_id));

        $upcoming = (clone $query)
            ->whereIn('status', ['unpaid', 'overdue', 'suspended'])
            ->whereDate('due_date', '<=', $today->copy()->addDays(5)->toDateString())
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn (BranchBillingRecord $record) => [
                'type' => $record->due_date?->isPast() && ! $record->due_date?->isToday() ? 'danger' : 'billing',
                'title' => ($record->branch?->name ? $record->branch->name.' - ' : '').'Billing due',
                'message' => $record->periodLabel().' is '.str_replace('_', ' ', $record->status).' and due on '.$record->due_date?->format('M d, Y').'.',
                'key' => 'billing-due-'.$record->id.'-'.$record->status,
            ]);

        $paid = (clone $query)
            ->where('status', 'paid')
            ->where(function ($query) use ($today) {
                $query
                    ->where(function ($query) use ($today) {
                        $query
                            ->whereDate('subscription_start_date', '<=', $today->toDateString())
                            ->whereDate('subscription_end_date', '>=', $today->toDateString());
                    })
                    ->orWhereDate('payment_date', '>=', $today->copy()->subDays(5)->toDateString());
            })
            ->latest('payment_date')
            ->latest('subscription_end_date')
            ->limit(5)
            ->get()
            ->map(function (BranchBillingRecord $record) use ($today) {
                $daysUntilEnd = $record->subscription_end_date
                    ? $today->copy()->startOfDay()->diffInDays($record->subscription_end_date->copy()->startOfDay(), false)
                    : null;

                // Only show if billing is coming up (within 5 days), hide already paid notices
                if ($daysUntilEnd !== null && $daysUntilEnd >= 0 && $daysUntilEnd <= 5) {
                    return [
                        'type' => 'billing',
                        'title' => ($record->branch?->name ? $record->branch->name.' - ' : '').'Billing paid',
                        'message' => 'Paid for '.$record->periodLabel().'. Next billing is coming up in '.$daysUntilEnd.' day'.($daysUntilEnd === 1 ? '' : 's').'.',
                        'key' => 'billing-paid-'.$record->id,
                    ];
                }

                return null;
            })
            ->filter();

        return collect($upcoming->all())
            ->merge($paid->all())
            ->unique('key')
            ->take(8)
            ->values()
            ->all();
    }
}
