<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Support\DefaultServiceInventoryUsages;
use Illuminate\Database\Seeder;

class ServiceInventoryUsageSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()
            ->where('is_active', true)
            ->each(fn (Branch $branch) => DefaultServiceInventoryUsages::seedForBranch($branch));
    }
}
