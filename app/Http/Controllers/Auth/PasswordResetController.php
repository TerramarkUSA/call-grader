<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\PasswordResetMail;

class PasswordResetController extends Controller
{
    /**
     * Show forgot password form
     */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if ($user) {
            // Delete any existing tokens for this email
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            // Create new token
            $token = Str::random(64);
            DB::table('password_reset_tokens')->insert([
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            // Send email
            Mail::to($user->email)->send(new PasswordResetMail($user, $token));
        }

        // Always show success message (don't reveal if email exists)
        return back()->with('status', 'If an account exists with that email, you will receive a password reset link shortly.');
    }

    /**
     * Show reset password form
     */
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Reset the password
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Find the token
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return back()->withErrors(['email' => 'Invalid password reset request.']);
        }

        // Check if token is valid
        if (!Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'Invalid password reset token.']);
        }

        // Check if token is expired (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors(['email' => 'This password reset link has expired. Please request a new one.']);
        }

        // Update user password
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'User not found.']);
        }

        $user->update([
            'password' => $request->password, // Will be hashed by cast
        ]);

        // Delete the token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('status', 'Your password has been reset. You can now log in.');
    }

    /**
     * Show set password form (for new users after magic link invite)
     */
    public function showSetPasswordForm(Request $request)
    {
        // User should be temporarily authenticated via session
        $userId = session('set_password_user_id');
        if (!$userId) {
            return redirect()->route('login')->with('error', 'Invalid session. Please log in again.');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login')->with('error', 'User not found.');
        }

        return view('auth.set-password', ['user' => $user]);
    }

    /**
     * Set password for new user
     */
    public function setPassword(Request $request)
    {
        $userId = session('set_password_user_id');
        if (!$userId) {
            return redirect()->route('login')->with('error', 'Invalid session. Please log in again.');
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login')->with('error', 'User not found.');
        }

        $user->update([
            'password' => $request->password,
        ]);

        // Clear the session flag
        session()->forget('set_password_user_id');

        // Log the user in
        auth()->login($user, true);

        return redirect($this->redirectPath($user))->with('status', 'Password set successfully. Welcome!');
    }

    /**
     * Get redirect path based on user role
     */
    protected function redirectPath($user): string
    {
        return match ($user->role) {
            'system_admin', 'site_admin' => '/admin/accounts',
            default => '/manager/calls',
        };
    }
}
