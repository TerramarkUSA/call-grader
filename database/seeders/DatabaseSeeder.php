<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RubricSeeder::class,
            SettingsSeeder::class,
            SystemAdminSeeder::class,
        ]);
    }
}
