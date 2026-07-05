<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\LaundryService;
use App\Support\DefaultLaundryServices;
use Illuminate\Database\Seeder;

class LaundryServiceSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()
            ->where('is_active', true)
            ->each(fn (Branch $branch) => DefaultLaundryServices::seedForBranch($branch));

        LaundryService::query()->whereNull('branch_id')->delete();
    }
}
