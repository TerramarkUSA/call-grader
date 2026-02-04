<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password {--password=password}';
    protected $description = 'Reset the system admin password (temporary command)';

    public function handle(): int
    {
        $password = $this->option('password');
        
        $user = User::where('email', 'gino@americanlandandlakes.com')->first();
        
        if (!$user) {
            $this->error('User not found!');
            return 1;
        }
        
        $user->password = Hash::make($password);
        $user->save();
        
        $this->info("Password reset successfully for {$user->email}");
        $this->info("New password: {$password}");
        
        return 0;
    }
}
