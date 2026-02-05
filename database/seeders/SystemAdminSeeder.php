<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\User;

class SystemAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Use environment variable for password, or generate a random one
        $password = env('ADMIN_DEFAULT_PASSWORD', Str::random(32));

        User::create([
            'name' => 'Gino',
            'email' => 'gino@americanlandandlakes.com',
            'password' => $password,
            'role' => 'system_admin',
            'is_active' => true,
        ]);

        // Output password if it was randomly generated (for initial setup)
        if (!env('ADMIN_DEFAULT_PASSWORD')) {
            $this->command->info("Generated admin password: {$password}");
            $this->command->warn("Save this password! It won't be shown again.");
        }
    }
}
