<?php

namespace App\Support;

class StatusBadge
{
    private const MAP = [
        'pending' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-300',
        'washing' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/60 dark:bg-blue-500/10 dark:text-blue-300',
        'drying' => 'border-cyan-200 bg-cyan-50 text-cyan-700 dark:border-cyan-900/60 dark:bg-cyan-500/10 dark:text-cyan-300',
        'folding' => 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-900/60 dark:bg-purple-500/10 dark:text-purple-300',
        'ironing' => 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700 dark:border-fuchsia-900/60 dark:bg-fuchsia-500/10 dark:text-fuchsia-300',
        'ready_for_pickup' => 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900/60 dark:bg-teal-500/10 dark:text-teal-300',
        'ready_for_delivery' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/60 dark:bg-orange-500/10 dark:text-orange-300',
        'completed' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300',
        'paid' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300',
        'active' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300',
        'sent' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300',
        'unpaid' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/60 dark:bg-orange-500/10 dark:text-orange-300',
        'partial' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-300',
        'billed' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/60 dark:bg-blue-500/10 dark:text-blue-300',
        'partially_paid' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-300',
        'overdue' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-300',
        'cancelled' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-300',
        'inactive' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-300',
        'failed' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-300',
        'suspended' => 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
        'queued' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-500/10 dark:text-sky-300',
        'scheduled' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-500/10 dark:text-sky-300',
        'expired' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-500/10 dark:text-red-300',
        'low' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/60 dark:bg-orange-500/10 dark:text-orange-300',
        'ok' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300',
        'cash' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-500/10 dark:text-emerald-300',
        'gcash' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-500/10 dark:text-sky-300',
        'bank' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/60 dark:bg-blue-500/10 dark:text-blue-300',
        'cheque' => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900/60 dark:bg-indigo-500/10 dark:text-indigo-300',
        'po' => 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900/60 dark:bg-violet-500/10 dark:text-violet-300',
        'monthly_billing' => 'border-pink-200 bg-pink-50 text-pink-700 dark:border-pink-900/60 dark:bg-pink-500/10 dark:text-pink-300',
        'regular' => 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-500/10 dark:text-slate-300',
        'delivery' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/60 dark:bg-orange-500/10 dark:text-orange-300',
    ];

    public static function classes(?string $status): string
    {
        $key = strtolower((string) $status);

        return 'inline-flex rounded-md border px-2 py-1 text-xs font-medium '.(self::MAP[$key] ?? 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-500/10 dark:text-slate-300');
    }

    public static function label(?string $status): string
    {
        $legacyLabel = match (strtolower((string) $status)) {
            'bank' => 'Legacy payment',
            'monthly_billing' => 'Legacy billing',
            default => null,
        };

        if ($legacyLabel) {
            return $legacyLabel;
        }

        return str_replace('_', ' ', ucfirst((string) $status));
    }
}
