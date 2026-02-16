<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\User;

class CallPolicy
{
    /**
     * Determine if the user can view the call.
     * System admins and site admins can view all calls.
     * Managers can only view calls from their assigned accounts.
     */
    public function view(User $user, Call $call): bool
    {
        // System admin and site admin can view all
        if (in_array($user->role, ['system_admin', 'site_admin'])) {
            return true;
        }

        // Ensure accounts are loaded to avoid N+1 queries
        $user->loadMissing('accounts');

        // Manager can only view calls from their assigned accounts
        return $user->accounts->contains('id', $call->account_id);
    }

    /**
     * Determine if the user can grade the call.
     */
    public function grade(User $user, Call $call): bool
    {
        return $this->view($user, $call);
    }

    /**
     * Determine if the user can transcribe the call.
     */
    public function transcribe(User $user, Call $call): bool
    {
        return $this->view($user, $call);
    }

    /**
     * Determine if the user can update the call.
     */
    public function update(User $user, Call $call): bool
    {
        return $this->view($user, $call);
    }

    /**
     * Determine if the user can skip a call.
     */
    public function skip(User $user, Call $call): bool
    {
        return $this->view($user, $call);
    }

    /**
     * Determine if the user can clear/ungrade a call.
     * Only system admins and site admins.
     */
    public function ungrade(User $user, Call $call): bool
    {
        return in_array($user->role, ['system_admin', 'site_admin']);
    }
}
