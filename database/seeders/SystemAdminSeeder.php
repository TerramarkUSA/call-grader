<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class SystemAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Gino',
            'email' => 'gino@example.com', // CHANGE THIS to your real email
            'role' => 'system_admin',
            'is_active' => true,
        ]);
    }
}
