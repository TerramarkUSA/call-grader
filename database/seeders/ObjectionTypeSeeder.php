<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ObjectionType;

class ObjectionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Price/Budget', 'sort_order' => 1],
            ['name' => 'Timing/Not Ready', 'sort_order' => 2],
            ['name' => 'Need to Talk to Spouse', 'sort_order' => 3],
            ['name' => 'Distance/Location', 'sort_order' => 4],
            ['name' => 'Already Own Property', 'sort_order' => 5],
            ['name' => 'Bad Experience Before', 'sort_order' => 6],
            ['name' => 'Just Looking/Not Serious', 'sort_order' => 7],
            ['name' => 'Health/Age Concerns', 'sort_order' => 8],
            ['name' => 'Scheduling Conflict', 'sort_order' => 9],
            ['name' => 'Want More Information First', 'sort_order' => 10],
            ['name' => 'Other', 'sort_order' => 99],
        ];

        foreach ($types as $type) {
            ObjectionType::updateOrCreate(
                ['name' => $type['name']],
                ['sort_order' => $type['sort_order'], 'is_active' => true]
            );
        }
    }
}
