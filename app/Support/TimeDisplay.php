<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class TimeDisplay
{
    public static function attendance(?string $time): string
    {
        if (! filled($time)) {
            return '-';
        }

        try {
            return Carbon::createFromFormat('H:i:s', $time)->format('h:i A');
        } catch (\Throwable) {
            try {
                return Carbon::parse($time)->format('h:i A');
            } catch (\Throwable) {
                return $time;
            }
        }
    }

    public static function attendanceList(?array $times): string
    {
        return collect($times ?? [])
            ->map(fn ($time) => self::attendance((string) $time))
            ->filter()
            ->implode(', ') ?: '-';
    }
}
