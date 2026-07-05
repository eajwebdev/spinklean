<?php

namespace App\Support;

class Menu
{
    public static function items(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard'],
            'job_orders' => ['label' => 'Job Orders', 'route' => 'admin.job-orders.index', 'icon' => 'jobOrders'],
            'cycles' => ['label' => 'Cycle Monitoring', 'route' => 'admin.cycles.index', 'icon' => 'cycles'],
            'customers' => ['label' => 'Customers', 'route' => 'admin.customers.index', 'icon' => 'customers'],
            'services' => ['label' => 'Laundry Services', 'route' => 'admin.services.index', 'icon' => 'services'],
            'service_categories' => ['label' => 'Service Categories', 'route' => 'admin.service-categories.index', 'icon' => 'tag', 'super_admin' => false],
            'inventory' => ['label' => 'Inventory', 'route' => 'admin.inventory.index', 'icon' => 'inventory'],
            'payments' => ['label' => 'Payments', 'route' => 'admin.payments.index', 'icon' => 'payments'],
            'receivables' => ['label' => 'Receivables', 'route' => 'admin.receivables.index', 'icon' => 'receivables'],
            'po_transactions' => ['label' => 'PO Transactions', 'route' => 'admin.po-transactions.index', 'icon' => 'file-text'],
            'expenses' => ['label' => 'Expenses', 'route' => 'admin.expenses.index', 'icon' => 'expense'],
            'accounts_payable' => ['label' => 'Accounts Payable', 'route' => 'admin.accounts-payable.index', 'icon' => 'receivables'],
            'petty_cash' => ['label' => 'Petty Cash', 'route' => 'admin.petty-cash.index', 'icon' => 'wallet'],
            'z_readings' => ['label' => 'Z Reading', 'route' => 'admin.z-readings.index', 'icon' => 'receipt'],
            'employees' => ['label' => 'Employees', 'route' => 'admin.employees.index', 'icon' => 'employees'],
            'attendance' => ['label' => 'Attendance Logs', 'route' => 'admin.attendance.index', 'icon' => 'attendance'],
            'daily_tasks' => ['label' => 'End-of-Day Tasks', 'route' => 'admin.daily-tasks.index', 'icon' => 'check'],
            'reports' => ['label' => 'Reports', 'route' => 'admin.reports.index', 'icon' => 'reports'],
            'branches' => ['label' => 'Branches', 'route' => 'admin.branches.index', 'icon' => 'branches'],
            'users' => ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'users'],
            'billing' => ['label' => 'Billing', 'route' => 'admin.billing.index', 'icon' => 'receipt', 'super_admin' => true],
            'sms_logs' => ['label' => 'SMS Logs', 'route' => 'admin.sms-logs.index', 'icon' => 'smsLogs'],
            'settings' => ['label' => 'System Settings', 'route' => 'admin.settings.edit', 'icon' => 'settings'],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::items());
    }

    public static function assignableKeysForRole(string $role): array
    {
        if ($role === 'super_admin') {
            return self::keys();
        }

        return array_keys(array_filter(
            self::items(),
            fn (array $item): bool => empty($item['super_admin'])
        ));
    }
}
