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
            'email' => 'gino@americanlandandlakes.com',
            'password' => 'password',
            'role' => 'system_admin',
            'is_active' => true,
        ]);
    }
}
