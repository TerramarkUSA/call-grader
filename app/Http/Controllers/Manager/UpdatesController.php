<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class UpdatesController extends Controller
{
    // Bump this date each time you add a new entry to the view
    public const LATEST_UPDATE = '2026-01-31';

    public function index()
    {
        try {
            Auth::user()->update(['last_seen_updates_at' => now()]);
        } catch (\Throwable $e) {
            // Column may not exist yet if migration hasn't run
        }

        return view('manager.updates.index');
    }

    public static function hasUnseenUpdates(): bool
    {
        try {
            $user = Auth::user();
            if (!$user) return false;

            $lastSeen = $user->last_seen_updates_at;
            if (!$lastSeen) return true;

            return $lastSeen->lt(self::LATEST_UPDATE);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
