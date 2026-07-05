<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchSetting;
use Illuminate\Database\Seeder;

class BranchSettingSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()->each(function (Branch $branch) {
            BranchSetting::firstOrCreate(
                ['branch_id' => $branch->id],
                [
                    'job_order_prefix' => $branch->code,
                    'invoice_prefix' => 'INV-'.$branch->code,
                ]
            );
        });
    }
}
