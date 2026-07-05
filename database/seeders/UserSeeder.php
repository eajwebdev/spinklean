<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use App\Support\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $branchId = null;

        if (Schema::hasTable('branches')) {
            $branchId = Branch::updateOrCreate(
                ['code' => 'MAIN'],
                [
                    'name' => 'Main Branch',
                    'address' => 'Main Office',
                    'contact_number' => null,
                    'is_active' => true,
                ]
            )->id;
        }

        $users = [
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'email' => 'superadmin@laundry.test',
                'role' => 'super_admin',
                'branch_id' => null,
                'access' => Menu::keys(),
            ],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'email' => 'admin@laundry.test',
                'role' => 'admin',
                'branch_id' => null,
                'access' => Menu::keys(),
            ],
            [
                'name' => 'Branch Manager',
                'username' => 'manager',
                'email' => 'manager@laundry.test',
                'role' => 'branch_manager',
                'branch_id' => $branchId,
                'access' => ['dashboard', 'customers', 'services', 'job_orders', 'cycles', 'employees', 'payments', 'receivables', 'po_transactions', 'expenses', 'accounts_payable', 'petty_cash', 'inventory', 'attendance', 'reports', 'sms_logs', 'settings'],
            ],
            [
                'name' => 'Cashier User',
                'username' => 'cashier',
                'email' => 'cashier@laundry.test',
                'role' => 'cashier',
                'branch_id' => $branchId,
                'access' => ['dashboard', 'customers', 'job_orders', 'cycles', 'payments', 'receivables', 'po_transactions'],
            ],
            [
                'name' => 'Staff User',
                'username' => 'staff',
                'email' => 'staff@laundry.test',
                'role' => 'staff',
                'branch_id' => $branchId,
                'access' => ['dashboard', 'job_orders', 'cycles'],
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['username' => $user['username']],
                [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'email_verified_at' => now(),
                    'password' => 'admin123',
                    'role' => $user['role'],
                    'branch_id' => $user['branch_id'],
                    'access' => $user['access'],
                    'status' => 'active',
                ]
            );
        }
    }
}
