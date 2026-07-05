<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Activity
{
    public static function log(Request $request, string $action, ?Model $subject = null, array $properties = [], ?int $branchId = null): void
    {
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'branch_id' => $branchId ?? ($subject?->branch_id ?? $request->user()?->branch_id),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip_address' => $request->ip(),
        ]);
    }
}
