<?php

namespace App\Support;

use App\Models\JobOrder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TagValidator
{
    /**
     * Validate tag uniqueness according to business rules:
     * 1. Cannot be reused on the same calendar day even if completed/released.
     * 2. Cannot be duplicated among active laundry orders.
     *
     * @throws ValidationException
     */
    public static function validate(string $tagNumber, ?int $ignoreJobOrderId = null, ?Carbon $date = null): string
    {
        $tag = trim($tagNumber);

        if ($tag === '') {
            throw ValidationException::withMessages([
                'tag_number' => 'The Tag Number field is required.',
            ]);
        }

        $targetDate = ($date ?: Carbon::now(config('app.timezone', 'Asia/Manila')))->toDateString();

        // Rule 1: Check same calendar day reuse across ALL job orders (created, completed, or released today)
        $sameDayJobOrder = JobOrder::query()
            ->when($ignoreJobOrderId, fn ($query) => $query->where('id', '!=', $ignoreJobOrderId))
            ->where('tag_number', $tag)
            ->where(function ($q) use ($targetDate) {
                $q->whereDate('created_at', $targetDate)
                    ->orWhereDate('completed_at', $targetDate)
                    ->orWhereDate('released_at', $targetDate);
            })
            ->first();

        if ($sameDayJobOrder) {
            throw ValidationException::withMessages([
                'tag_number' => "Tag #{$tag} has already been used today. Please use another Tag Number.",
            ]);
        }

        // Rule 2: Check active laundry duplication across any day
        // Active means status != cancelled AND (status != completed OR released_at IS NULL)
        $activeJobOrder = JobOrder::query()
            ->when($ignoreJobOrderId, fn ($query) => $query->where('id', '!=', $ignoreJobOrderId))
            ->where('tag_number', $tag)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) {
                $query->where('status', '!=', 'completed')
                    ->orWhereNull('released_at');
            })
            ->first();

        if ($activeJobOrder) {
            throw ValidationException::withMessages([
                'tag_number' => "Tag #{$tag} is currently assigned to an active laundry order ({$activeJobOrder->job_order_number}). Please use another Tag Number.",
            ]);
        }

        return $tag;
    }

    /**
     * Next auto-generated tag for no-machine branches: "001", "002", ... restarting
     * every day, skipping any number still unavailable under the rules above.
     */
    public static function nextDailyTag(?Carbon $date = null): string
    {
        $targetDate = ($date ?: Carbon::now(config('app.timezone', 'Asia/Manila')))->toDateString();

        $taken = JobOrder::query()
            ->whereNotNull('tag_number')
            ->where(function ($query) use ($targetDate) {
                $query->whereDate('created_at', $targetDate)
                    ->orWhereDate('completed_at', $targetDate)
                    ->orWhereDate('released_at', $targetDate)
                    ->orWhere(function ($active) {
                        $active->where('status', '!=', 'cancelled')
                            ->where(function ($q) {
                                $q->where('status', '!=', 'completed')
                                    ->orWhereNull('released_at');
                            });
                    });
            })
            ->pluck('tag_number')
            ->map(fn ($tag) => trim((string) $tag))
            ->flip();

        $number = 1;
        while ($taken->has($tag = str_pad((string) $number, 3, '0', STR_PAD_LEFT))) {
            $number++;
        }

        return $tag;
    }

    /**
     * Check if a tag is available (returns null if available, or error string if unavailable).
     */
    public static function checkAvailability(string $tagNumber, ?int $ignoreJobOrderId = null, ?Carbon $date = null): ?string
    {
        try {
            self::validate($tagNumber, $ignoreJobOrderId, $date);

            return null;
        } catch (ValidationException $e) {
            return $e->errors()['tag_number'][0] ?? 'Tag Number is not available.';
        }
    }
}
