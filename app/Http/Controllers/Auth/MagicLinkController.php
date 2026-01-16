<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use App\Models\User;
use App\Mail\MagicLinkMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MagicLinkController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Send magic link to email
     */
    public function sendLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            // Don't reveal if email exists or not
            return back()->with('status', 'If an account exists with that email, you will receive a login link shortly.');
        }

        // Invalidate any existing unused links for this user
        MagicLink::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        // Create new magic link (2 hour expiry)
        $magicLink = MagicLink::generateFor($user, 2);

        // Send email with logging
        try {
            Log::info('Attempting to send magic link email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'from' => config('mail.from.address'),
            ]);

            Mail::to($user->email)->send(new MagicLinkMail($magicLink));

            Log::info('Magic link email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send magic link email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return back()->with('status', 'If an account exists with that email, you will receive a login link shortly.');
    }

    /**
     * Verify magic link and log user in
     */
    public function verify(string $token)
    {
        $magicLink = MagicLink::where('token', $token)->first();

        if (!$magicLink) {
            return redirect()->route('login')
                ->with('error', 'Invalid login link.');
        }

        if (!$magicLink->isValid()) {
            return redirect()->route('login')
                ->with('error', 'This login link has expired. Please request a new one.');
        }

        // Mark link as used
        $magicLink->markAsUsed();

        // Log user in
        Auth::login($magicLink->user, true); // Remember = true

        // Redirect based on role
        return redirect()->intended($this->redirectPath($magicLink->user));
    }

    /**
     * Log user out
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Get redirect path based on user role
     */
    protected function redirectPath(User $user): string
    {
        return match ($user->role) {
            'system_admin' => '/admin/dashboard',
            'site_admin' => '/admin/dashboard',
            'manager' => '/manager/dashboard',
            default => '/dashboard',
        };
    }
}
