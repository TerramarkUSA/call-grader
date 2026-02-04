<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Account;
use App\Models\MagicLink;
use App\Mail\UserInvitationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    /**
     * Show user management page
     */
    public function index(Request $request)
    {
        $query = User::with('accounts')->where('role', '!=', 'system_admin');

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by office
        if ($request->filled('office')) {
            $query->whereHas('accounts', fn($q) => $q->where('accounts.id', $request->office));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate(25)->withQueryString();
        $accounts = Account::where('is_active', true)->orderBy('name')->get();

        $stats = [
            'total' => User::where('role', '!=', 'system_admin')->count(),
            'active' => User::where('role', '!=', 'system_admin')->where('is_active', true)->count(),
            'managers' => User::where('role', 'manager')->count(),
            'admins' => User::where('role', 'site_admin')->count(),
        ];

        return view('admin.users.index', compact('users', 'accounts', 'stats'));
    }

    /**
     * Show invite user form
     */
    public function create()
    {
        $accounts = Account::where('is_active', true)->get();

        return view('admin.users.create', compact('accounts'));
    }

    /**
     * Invite a new user
     */
    public function store(Request $request)
    {
        // Site admins see all offices, so account assignment is optional
        // Managers must be assigned to at least one office
        $accountRules = $request->role === 'site_admin'
            ? 'nullable|array'
            : 'required|array|min:1';

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:site_admin,manager',
            'account_ids' => $accountRules,
            'account_ids.*' => 'exists:accounts,id',
        ]);

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'is_active' => true,
        ]);

        // Attach accounts (if any selected)
        if (!empty($request->account_ids)) {
            $user->accounts()->attach($request->account_ids);
        }

        // Create magic link for initial login (24 hour expiry for invites)
        $magicLink = MagicLink::generateFor($user, 24);

        // Send invitation email
        Mail::to($user->email)->send(new UserInvitationMail($user, $magicLink));

        return redirect()->route('admin.users.index')
            ->with('success', "Invitation sent to {$user->email}");
    }

    /**
     * Toggle user active status
     */
    public function toggleActive(User $user)
    {
        // Prevent deactivating yourself
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }

        // Prevent modifying system admin
        if ($user->role === 'system_admin') {
            return back()->with('error', 'Cannot modify system admin.');
        }

        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "{$user->name} has been {$status}.");
    }

    /**
     * Resend invitation
     */
    public function resendInvite(User $user)
    {
        // Create new magic link
        $magicLink = MagicLink::generateFor($user, 24);

        // Send invitation email
        Mail::to($user->email)->send(new UserInvitationMail($user, $magicLink));

        return back()->with('success', "Invitation resent to {$user->email}");
    }

    /**
     * Update user accounts
     */
    public function updateAccounts(Request $request, User $user)
    {
        $request->validate([
            'account_ids' => 'required|array|min:1',
            'account_ids.*' => 'exists:accounts,id',
        ]);

        $user->accounts()->sync($request->account_ids);

        return back()->with('success', "Accounts updated for {$user->name}");
    }

    /**
     * Show edit user form
     */
    public function edit(User $user)
    {
        // Prevent editing system admin
        if ($user->role === 'system_admin') {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot edit system admin.');
        }

        $user->load('accounts');
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        $canEditRole = Auth::user()->role === 'system_admin';

        return view('admin.users.edit', compact('user', 'accounts', 'canEditRole'));
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        // Prevent editing system admin
        if ($user->role === 'system_admin') {
            return back()->with('error', 'Cannot edit system admin.');
        }

        // Determine role (use submitted role if system_admin, otherwise keep existing)
        $effectiveRole = Auth::user()->role === 'system_admin' && $request->has('role')
            ? $request->role
            : $user->role;

        // Site admins see all offices, so account assignment is optional
        $accountRules = $effectiveRole === 'site_admin'
            ? 'nullable|array'
            : 'required|array|min:1';

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:manager,site_admin',
            'account_ids' => $accountRules,
            'account_ids.*' => 'exists:accounts,id',
            'is_active' => 'boolean',
        ]);

        // Only system admin can change roles
        if (isset($validated['role']) && Auth::user()->role !== 'system_admin') {
            unset($validated['role']);
        }

        // Can't deactivate yourself
        if (isset($validated['is_active']) && !$validated['is_active'] && $user->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'] ?? $user->role,
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ]);

        // Sync accounts (empty array for site_admin with no assignments)
        $user->accounts()->sync($validated['account_ids'] ?? []);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }
}
