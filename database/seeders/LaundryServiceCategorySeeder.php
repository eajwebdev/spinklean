<?php

namespace Database\Seeders;

use App\Models\LaundryServiceCategory;
use Illuminate\Database\Seeder;

class LaundryServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Small Machine',  'visibility' => 'all', 'sort_order' => 1],
            ['name' => 'Big Machine',    'visibility' => 'all', 'sort_order' => 2],
            ['name' => 'Delivery',       'visibility' => 'all', 'sort_order' => 3],
            ['name' => 'Extra Services', 'visibility' => 'all', 'sort_order' => 4],
            ['name' => 'Special Items',  'visibility' => 'all', 'sort_order' => 5],
            ['name' => 'Establishment',  'visibility' => 'all', 'sort_order' => 6],
            ['name' => 'For Sale Items', 'visibility' => 'all', 'sort_order' => 7],
        ];

        foreach ($categories as $category) {
            LaundryServiceCategory::updateOrCreate(
                ['name' => $category['name']],
                ['visibility' => $category['visibility'], 'sort_order' => $category['sort_order'], 'is_active' => true]
            );
        }
    }
}
