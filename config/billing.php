<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription billing cycle
    |--------------------------------------------------------------------------
    |
    | Number of months each subscription cycle covers. When a branch pays, the
    | next billing record is auto-generated for the following cycle and its due
    | date is derived from the last paid period end (no manual due dates).
    |
    */
    'cycle_months' => (int) env('BILLING_CYCLE_MONTHS', 1),

    /*
    | How many days before the due date the branch starts getting the payment
    | prompt / notification. Advance payment is always allowed.
    */
    'notify_days_before' => (int) env('BILLING_NOTIFY_DAYS', 3),

    /*
    | How many days a branch may keep using the system after the due date
    | before the system is hard-locked behind the payment gateway.
    | "Overdue by 2 days" => locked once today is >= due_date + this value.
    */
    'lock_grace_days' => (int) env('BILLING_LOCK_GRACE_DAYS', 2),

    /*
    | PayMongo minimum chargeable amount (PHP). Amounts below this cannot be
    | processed through the gateway.
    */
    'min_amount' => (float) env('BILLING_MIN_AMOUNT', 20),

];
