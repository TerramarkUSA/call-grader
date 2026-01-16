# Slice 10: Settings + User Management

## Objective
Build admin pages for managing users, offices, rubric configuration, and system settings. This is primarily for site admins and system admins.

## Prerequisites
- **Slice 1-2 complete**: Database schema and auth working
- **Slice 3 complete**: Offices (accounts) exist

## What This Slice Builds

1. **User Management** — Invite, edit, deactivate users
2. **Office Management** — Assign users to offices, manage CTM connections
3. **Rubric Management** — Edit categories, checkpoints, weights
4. **Objection Types Management** — Add/edit objection types
5. **System Settings** — Global configuration options

---

## File Structure

```
resources/
├── js/
│   └── Pages/Admin/
│       ├── Users/
│       │   ├── Index.vue              # User list
│       │   ├── Create.vue             # Invite user
│       │   └── Edit.vue               # Edit user
│       ├── Offices/
│       │   ├── Index.vue              # Office list (update from Slice 3)
│       │   └── Edit.vue               # Edit office + assign users
│       ├── Rubric/
│       │   ├── Categories.vue         # Manage categories
│       │   └── Checkpoints.vue        # Manage checkpoints
│       ├── ObjectionTypes/
│       │   └── Index.vue              # Manage objection types
│       └── Settings/
│           └── Index.vue              # System settings
app/
├── Http/Controllers/Admin/
│   ├── UserController.php
│   ├── AccountController.php          # Update existing
│   ├── RubricController.php
│   ├── ObjectionTypeController.php
│   └── SettingsController.php
```

---

## Step 1: User Controller

Create `app/Http/Controllers/Admin/UserController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Account;
use App\Models\MagicLink;
use App\Mail\InvitationEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->with('accounts:id,name');

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

        // Get filter options
        $offices = Account::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['role', 'status', 'office', 'search']),
            'filterOptions' => [
                'offices' => $offices,
            ],
            'stats' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'managers' => User::where('role', 'manager')->count(),
                'admins' => User::whereIn('role', ['site_admin', 'system_admin'])->count(),
            ],
        ]);
    }

    public function create()
    {
        $offices = Account::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Users/Create', [
            'offices' => $offices,
            'canCreateAdmin' => Auth::user()->role === 'system_admin',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:manager,site_admin,system_admin',
            'office_ids' => 'required|array|min:1',
            'office_ids.*' => 'exists:accounts,id',
        ]);

        // Only system admin can create other admins
        if (in_array($validated['role'], ['site_admin', 'system_admin']) && Auth::user()->role !== 'system_admin') {
            abort(403, 'Only system admins can create admin users.');
        }

        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => true,
        ]);

        // Assign offices
        $user->accounts()->attach($validated['office_ids']);

        // Create invitation magic link
        $token = Str::random(64);
        MagicLink::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(72), // 3 days for invitation
        ]);

        // Send invitation email
        try {
            Mail::to($user->email)->send(new InvitationEmail($user, $token));
            $emailSent = true;
        } catch (\Exception $e) {
            $emailSent = false;
        }

        return redirect()->route('admin.users.index')
            ->with('success', "User invited successfully." . ($emailSent ? '' : ' (Email failed to send - check mail config)'));
    }

    public function edit(User $user)
    {
        $user->load('accounts:id,name');
        $offices = Account::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'offices' => $offices,
            'canEditRole' => Auth::user()->role === 'system_admin',
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:manager,site_admin,system_admin',
            'office_ids' => 'required|array|min:1',
            'office_ids.*' => 'exists:accounts,id',
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

        // Can't demote yourself from system admin
        if (isset($validated['role']) && $validated['role'] !== 'system_admin' && $user->id === Auth::id() && $user->role === 'system_admin') {
            return back()->with('error', 'You cannot demote yourself from system admin.');
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'] ?? $user->role,
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ]);

        // Sync offices
        $user->accounts()->sync($validated['office_ids']);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function resendInvite(User $user)
    {
        // Delete old magic links
        MagicLink::where('user_id', $user->id)->delete();

        // Create new invitation magic link
        $token = Str::random(64);
        MagicLink::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(72),
        ]);

        // Send invitation email
        try {
            Mail::to($user->email)->send(new InvitationEmail($user, $token));
            return back()->with('success', 'Invitation resent successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send email. Check mail configuration.');
        }
    }

    public function destroy(User $user)
    {
        // Can't delete yourself
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        // Soft delete - just deactivate
        $user->update(['is_active' => false]);

        return back()->with('success', 'User deactivated successfully.');
    }
}
```

---

## Step 2: Invitation Email

Create `app/Mail/InvitationEmail.php`:

```php
<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve been invited to Call Grader',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
            with: [
                'user' => $this->user,
                'url' => url("/login/verify/{$this->token}"),
            ],
        );
    }
}
```

Create `resources/views/emails/invitation.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 6px; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome to Call Grader!</h2>
        
        <p>Hi {{ $user->name }},</p>
        
        <p>You've been invited to join Call Grader as a <strong>{{ ucfirst(str_replace('_', ' ', $user->role)) }}</strong>.</p>
        
        <p>Click the button below to set up your account:</p>
        
        <p style="margin: 30px 0;">
            <a href="{{ $url }}" class="button">Accept Invitation</a>
        </p>
        
        <p>This link will expire in 72 hours.</p>
        
        <p>If the button doesn't work, copy and paste this URL into your browser:</p>
        <p style="word-break: break-all; color: #666; font-size: 14px;">{{ $url }}</p>
        
        <div class="footer">
            <p>This email was sent by Call Grader. If you didn't expect this invitation, you can ignore this email.</p>
        </div>
    </div>
</body>
</html>
```

---

## Step 3: Rubric Controller

Create `app/Http/Controllers/Admin/RubricController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RubricCategory;
use App\Models\RubricCheckpoint;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RubricController extends Controller
{
    public function categories()
    {
        $categories = RubricCategory::orderBy('sort_order')->get();

        return Inertia::render('Admin/Rubric/Categories', [
            'categories' => $categories,
        ]);
    }

    public function updateCategory(Request $request, RubricCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'weight' => 'required|integer|min:1|max:100',
            'training_reference' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return back()->with('success', 'Category updated successfully.');
    }

    public function reorderCategories(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'exists:rubric_categories,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            RubricCategory::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Categories reordered.');
    }

    public function checkpoints()
    {
        $checkpoints = RubricCheckpoint::orderBy('type')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Rubric/Checkpoints', [
            'checkpoints' => $checkpoints,
        ]);
    }

    public function storeCheckpoint(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:500',
            'type' => 'required|in:positive,negative',
            'description' => 'nullable|string|max:1000',
        ]);

        $maxSort = RubricCheckpoint::where('type', $validated['type'])->max('sort_order') ?? 0;

        RubricCheckpoint::create([
            'label' => $validated['label'],
            'type' => $validated['type'],
            'description' => $validated['description'],
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Checkpoint added.');
    }

    public function updateCheckpoint(Request $request, RubricCheckpoint $checkpoint)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:500',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $checkpoint->update($validated);

        return back()->with('success', 'Checkpoint updated.');
    }

    public function deleteCheckpoint(RubricCheckpoint $checkpoint)
    {
        $checkpoint->delete();

        return back()->with('success', 'Checkpoint deleted.');
    }

    public function reorderCheckpoints(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:positive,negative',
            'order' => 'required|array',
            'order.*' => 'exists:rubric_checkpoints,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            RubricCheckpoint::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Checkpoints reordered.');
    }
}
```

---

## Step 4: Objection Type Controller

Create `app/Http/Controllers/Admin/ObjectionTypeController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ObjectionType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ObjectionTypeController extends Controller
{
    public function index()
    {
        $types = ObjectionType::orderBy('sort_order')->get();

        return Inertia::render('Admin/ObjectionTypes/Index', [
            'types' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:objection_types,name',
        ]);

        $maxSort = ObjectionType::max('sort_order') ?? 0;

        ObjectionType::create([
            'name' => $validated['name'],
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Objection type added.');
    }

    public function update(Request $request, ObjectionType $objectionType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:objection_types,name,' . $objectionType->id,
            'is_active' => 'boolean',
        ]);

        $objectionType->update($validated);

        return back()->with('success', 'Objection type updated.');
    }

    public function destroy(ObjectionType $objectionType)
    {
        // Check if in use
        if ($objectionType->coachingNotes()->exists()) {
            return back()->with('error', 'Cannot delete - this type is used in coaching notes. Deactivate it instead.');
        }

        $objectionType->delete();

        return back()->with('success', 'Objection type deleted.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'exists:objection_types,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            ObjectionType::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Order updated.');
    }
}
```

---

## Step 5: Settings Controller

Create `app/Http/Controllers/Admin/SettingsController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        // Defaults
        $defaults = [
            'grading_quality_flag_threshold' => 25,
            'grading_quality_warn_threshold' => 50,
            'call_sync_days' => 14,
            'short_call_threshold' => 30,
            'allow_partial_grades' => true,
            'require_appointment_quality' => true,
            'require_no_appointment_reason' => true,
        ];

        $settings = array_merge($defaults, $settings);

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'grading_quality_flag_threshold' => 'required|integer|min:0|max:100',
            'grading_quality_warn_threshold' => 'required|integer|min:0|max:100',
            'call_sync_days' => 'required|integer|min:1|max:90',
            'short_call_threshold' => 'required|integer|min:0|max:300',
            'allow_partial_grades' => 'boolean',
            'require_appointment_quality' => 'boolean',
            'require_no_appointment_reason' => 'boolean',
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
            );
        }

        return back()->with('success', 'Settings saved.');
    }
}
```

Create Setting model if not exists - `app/Models/Setting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
}
```

Create migration if not exists:

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->timestamps();
});
```

---

## Step 6: Routes

Add to `routes/admin.php`:

```php
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RubricController;
use App\Http\Controllers\Admin\ObjectionTypeController;
use App\Http\Controllers\Admin\SettingsController;

Route::middleware(['auth', 'role:site_admin,system_admin'])->prefix('admin')->name('admin.')->group(function () {
    // Users
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/resend-invite', [UserController::class, 'resendInvite'])->name('users.resend-invite');

    // Rubric
    Route::get('/rubric/categories', [RubricController::class, 'categories'])->name('rubric.categories');
    Route::patch('/rubric/categories/{category}', [RubricController::class, 'updateCategory'])->name('rubric.categories.update');
    Route::post('/rubric/categories/reorder', [RubricController::class, 'reorderCategories'])->name('rubric.categories.reorder');
    Route::get('/rubric/checkpoints', [RubricController::class, 'checkpoints'])->name('rubric.checkpoints');
    Route::post('/rubric/checkpoints', [RubricController::class, 'storeCheckpoint'])->name('rubric.checkpoints.store');
    Route::patch('/rubric/checkpoints/{checkpoint}', [RubricController::class, 'updateCheckpoint'])->name('rubric.checkpoints.update');
    Route::delete('/rubric/checkpoints/{checkpoint}', [RubricController::class, 'deleteCheckpoint'])->name('rubric.checkpoints.destroy');
    Route::post('/rubric/checkpoints/reorder', [RubricController::class, 'reorderCheckpoints'])->name('rubric.checkpoints.reorder');

    // Objection Types
    Route::get('/objection-types', [ObjectionTypeController::class, 'index'])->name('objection-types.index');
    Route::post('/objection-types', [ObjectionTypeController::class, 'store'])->name('objection-types.store');
    Route::patch('/objection-types/{objectionType}', [ObjectionTypeController::class, 'update'])->name('objection-types.update');
    Route::delete('/objection-types/{objectionType}', [ObjectionTypeController::class, 'destroy'])->name('objection-types.destroy');
    Route::post('/objection-types/reorder', [ObjectionTypeController::class, 'reorder'])->name('objection-types.reorder');

    // Settings (system admin only)
    Route::middleware('role:system_admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    // ... existing account routes ...
});
```

---

## Step 7: Admin Navigation Layout

Create or update `resources/js/Layouts/AdminLayout.vue`:

```vue
<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Top Navigation Bar -->
    <nav class="bg-gray-900 text-white">
      <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
          <!-- Left: Logo + Nav Links -->
          <div class="flex">
            <Link 
              href="/admin/accounts" 
              class="flex items-center px-2 text-xl font-bold hover:text-blue-400"
            >
              Call Grader <span class="ml-2 text-xs bg-blue-600 px-2 py-0.5 rounded">Admin</span>
            </Link>

            <div class="hidden sm:ml-8 sm:flex sm:space-x-1">
              <AdminNavLink href="/admin/accounts" :active="isActive('/admin/accounts')">
                Offices
              </AdminNavLink>
              <AdminNavLink href="/admin/users" :active="isActive('/admin/users')">
                Users
              </AdminNavLink>
              <AdminNavLink href="/admin/rubric/categories" :active="isActive('/admin/rubric')">
                Rubric
              </AdminNavLink>
              <AdminNavLink href="/admin/objection-types" :active="isActive('/admin/objection-types')">
                Objection Types
              </AdminNavLink>
              <AdminNavLink 
                v-if="$page.props.auth.user.role === 'system_admin'"
                href="/admin/settings" 
                :active="isActive('/admin/settings')"
              >
                Settings
              </AdminNavLink>
            </div>
          </div>

          <!-- Right: Switch to Manager + User Menu -->
          <div class="flex items-center gap-4">
            <Link 
              href="/manager/calls"
              class="text-sm text-gray-300 hover:text-white"
            >
              ← Manager View
            </Link>

            <div class="relative" v-click-outside="() => showUserMenu = false">
              <button 
                @click="showUserMenu = !showUserMenu"
                class="flex items-center gap-2 px-3 py-2 text-sm hover:text-gray-300"
              >
                <span>{{ $page.props.auth.user.name }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>

              <div 
                v-if="showUserMenu"
                class="absolute right-0 mt-1 w-48 bg-white rounded-md shadow-lg py-1 z-50"
              >
                <Link
                  :href="route('logout')"
                  method="post"
                  as="button"
                  class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                >
                  Sign Out
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Page Content -->
    <main>
      <slot />
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const showUserMenu = ref(false);

const AdminNavLink = {
  props: ['href', 'active'],
  template: `
    <Link 
      :href="href" 
      :class="[
        'inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors',
        active 
          ? 'bg-gray-700 text-white' 
          : 'text-gray-300 hover:text-white hover:bg-gray-800'
      ]"
    >
      <slot />
    </Link>
  `,
  components: { Link }
};

function isActive(path) {
  return window.location.pathname.startsWith(path);
}

const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => {
      if (!el.contains(e.target)) binding.value();
    };
    document.addEventListener('click', el._clickOutside);
  },
  unmounted(el) {
    document.removeEventListener('click', el._clickOutside);
  }
};
</script>
```

---

## Step 8: Users Index Page

Create `resources/js/Pages/Admin/Users/Index.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Users</h1>
          <p class="text-gray-600">Manage user accounts and permissions</p>
        </div>
        <Link 
          :href="route('admin.users.create')"
          class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
        >
          + Invite User
        </Link>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
          <p class="text-sm text-gray-500">Total Users</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-2xl font-bold text-green-600">{{ stats.active }}</p>
          <p class="text-sm text-gray-500">Active</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-2xl font-bold text-blue-600">{{ stats.managers }}</p>
          <p class="text-sm text-gray-500">Managers</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-2xl font-bold text-purple-600">{{ stats.admins }}</p>
          <p class="text-sm text-gray-500">Admins</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex gap-4 items-end flex-wrap">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Role</label>
            <select v-model="localFilters.role" class="border rounded px-3 py-2 text-sm">
              <option value="">All Roles</option>
              <option value="manager">Manager</option>
              <option value="site_admin">Site Admin</option>
              <option value="system_admin">System Admin</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Status</label>
            <select v-model="localFilters.status" class="border rounded px-3 py-2 text-sm">
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Office</label>
            <select v-model="localFilters.office" class="border rounded px-3 py-2 text-sm">
              <option value="">All Offices</option>
              <option v-for="office in filterOptions.offices" :key="office.id" :value="office.id">
                {{ office.name }}
              </option>
            </select>
          </div>
          <div class="flex-1">
            <label class="block text-sm text-gray-600 mb-1">Search</label>
            <input 
              type="text" 
              v-model="localFilters.search" 
              class="w-full border rounded px-3 py-2 text-sm"
              placeholder="Name or email..."
            />
          </div>
          <button @click="applyFilters" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">
            Apply
          </button>
        </div>
      </div>

      <!-- Users Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Offices</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ user.name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ user.email }}</td>
              <td class="px-4 py-3">
                <span :class="['text-xs px-2 py-0.5 rounded', roleClass(user.role)]">
                  {{ formatRole(user.role) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ user.accounts.map(a => a.name).join(', ') || '—' }}
              </td>
              <td class="px-4 py-3">
                <span :class="[
                  'text-xs px-2 py-0.5 rounded',
                  user.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'
                ]">
                  {{ user.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link 
                  :href="route('admin.users.edit', user.id)"
                  class="text-blue-600 hover:text-blue-800 text-sm mr-3"
                >
                  Edit
                </Link>
                <button
                  v-if="!user.email_verified_at"
                  @click="resendInvite(user)"
                  class="text-orange-600 hover:text-orange-800 text-sm"
                >
                  Resend Invite
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="users.data.length === 0" class="p-8 text-center text-gray-500">
          No users found.
        </div>

        <!-- Pagination -->
        <div v-if="users.last_page > 1" class="px-4 py-3 border-t flex justify-between items-center">
          <p class="text-sm text-gray-600">
            Showing {{ users.from }} to {{ users.to }} of {{ users.total }}
          </p>
          <div class="flex gap-1">
            <Link
              v-for="link in users.links"
              :key="link.label"
              :href="link.url"
              :class="[
                'px-3 py-1 text-sm rounded',
                link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700',
                !link.url ? 'opacity-50 cursor-not-allowed' : ''
              ]"
              v-html="link.label"
            />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  users: Object,
  filters: Object,
  filterOptions: Object,
  stats: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('admin.users.index'), localFilters.value, { preserveState: true });
}

function formatRole(role) {
  return {
    manager: 'Manager',
    site_admin: 'Site Admin',
    system_admin: 'System Admin',
  }[role] || role;
}

function roleClass(role) {
  return {
    manager: 'bg-blue-100 text-blue-800',
    site_admin: 'bg-purple-100 text-purple-800',
    system_admin: 'bg-red-100 text-red-800',
  }[role] || 'bg-gray-100 text-gray-800';
}

function resendInvite(user) {
  if (confirm(`Resend invitation to ${user.email}?`)) {
    router.post(route('admin.users.resend-invite', user.id));
  }
}
</script>
```

---

## Step 9: Users Create Page

Create `resources/js/Pages/Admin/Users/Create.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto px-4 py-6">
      <div class="mb-6">
        <Link href="/admin/users" class="text-gray-500 hover:text-gray-700 text-sm">
          ← Back to Users
        </Link>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Invite User</h1>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <form @submit.prevent="submit">
          <div class="space-y-4">
            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input 
                type="text" 
                v-model="form.name"
                class="w-full border rounded px-3 py-2"
                required
              />
            </div>

            <!-- Email -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input 
                type="email" 
                v-model="form.email"
                class="w-full border rounded px-3 py-2"
                required
              />
            </div>

            <!-- Role -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
              <select v-model="form.role" class="w-full border rounded px-3 py-2">
                <option value="manager">Manager</option>
                <option v-if="canCreateAdmin" value="site_admin">Site Admin</option>
                <option v-if="canCreateAdmin" value="system_admin">System Admin</option>
              </select>
              <p class="text-xs text-gray-500 mt-1">
                <span v-if="form.role === 'manager'">Can grade calls and view their own data.</span>
                <span v-else-if="form.role === 'site_admin'">Can manage users and view all data.</span>
                <span v-else>Full system access including settings.</span>
              </p>
            </div>

            <!-- Offices -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Assign to Offices</label>
              <div class="border rounded p-3 max-h-48 overflow-y-auto space-y-2">
                <label 
                  v-for="office in offices" 
                  :key="office.id"
                  class="flex items-center gap-2 cursor-pointer"
                >
                  <input 
                    type="checkbox" 
                    :value="office.id"
                    v-model="form.office_ids"
                    class="rounded border-gray-300"
                  />
                  <span class="text-sm">{{ office.name }}</span>
                </label>
              </div>
              <p v-if="form.office_ids.length === 0" class="text-xs text-red-500 mt-1">
                Select at least one office.
              </p>
            </div>
          </div>

          <!-- Submit -->
          <div class="mt-6 flex gap-3">
            <Link 
              href="/admin/users"
              class="px-4 py-2 border rounded text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </Link>
            <button
              type="submit"
              :disabled="processing || form.office_ids.length === 0"
              class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {{ processing ? 'Sending...' : 'Send Invitation' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  offices: Array,
  canCreateAdmin: Boolean,
});

const processing = ref(false);
const form = ref({
  name: '',
  email: '',
  role: 'manager',
  office_ids: [],
});

function submit() {
  processing.value = true;
  router.post(route('admin.users.store'), form.value, {
    onFinish: () => processing.value = false,
  });
}
</script>
```

---

## Step 10: Users Edit Page

Create `resources/js/Pages/Admin/Users/Edit.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto px-4 py-6">
      <div class="mb-6">
        <Link href="/admin/users" class="text-gray-500 hover:text-gray-700 text-sm">
          ← Back to Users
        </Link>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Edit User</h1>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <form @submit.prevent="submit">
          <div class="space-y-4">
            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input 
                type="text" 
                v-model="form.name"
                class="w-full border rounded px-3 py-2"
                required
              />
            </div>

            <!-- Email -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input 
                type="email" 
                v-model="form.email"
                class="w-full border rounded px-3 py-2"
                required
              />
            </div>

            <!-- Role -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
              <select 
                v-model="form.role" 
                class="w-full border rounded px-3 py-2"
                :disabled="!canEditRole"
              >
                <option value="manager">Manager</option>
                <option value="site_admin">Site Admin</option>
                <option value="system_admin">System Admin</option>
              </select>
              <p v-if="!canEditRole" class="text-xs text-gray-500 mt-1">
                Only system admins can change roles.
              </p>
            </div>

            <!-- Status -->
            <div>
              <label class="flex items-center gap-2 cursor-pointer">
                <input 
                  type="checkbox" 
                  v-model="form.is_active"
                  class="rounded border-gray-300"
                />
                <span class="text-sm font-medium text-gray-700">Active</span>
              </label>
              <p class="text-xs text-gray-500 mt-1">
                Inactive users cannot log in.
              </p>
            </div>

            <!-- Offices -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Offices</label>
              <div class="border rounded p-3 max-h-48 overflow-y-auto space-y-2">
                <label 
                  v-for="office in offices" 
                  :key="office.id"
                  class="flex items-center gap-2 cursor-pointer"
                >
                  <input 
                    type="checkbox" 
                    :value="office.id"
                    v-model="form.office_ids"
                    class="rounded border-gray-300"
                  />
                  <span class="text-sm">{{ office.name }}</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Submit -->
          <div class="mt-6 flex justify-between">
            <button
              type="button"
              @click="confirmDeactivate"
              class="px-4 py-2 text-red-600 hover:text-red-800"
            >
              Deactivate User
            </button>
            <div class="flex gap-3">
              <Link 
                href="/admin/users"
                class="px-4 py-2 border rounded text-gray-700 hover:bg-gray-50"
              >
                Cancel
              </Link>
              <button
                type="submit"
                :disabled="processing"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
              >
                {{ processing ? 'Saving...' : 'Save Changes' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  user: Object,
  offices: Array,
  canEditRole: Boolean,
});

const processing = ref(false);
const form = ref({
  name: props.user.name,
  email: props.user.email,
  role: props.user.role,
  is_active: props.user.is_active,
  office_ids: props.user.accounts.map(a => a.id),
});

function submit() {
  processing.value = true;
  router.patch(route('admin.users.update', props.user.id), form.value, {
    onFinish: () => processing.value = false,
  });
}

function confirmDeactivate() {
  if (confirm('Deactivate this user? They will no longer be able to log in.')) {
    router.delete(route('admin.users.destroy', props.user.id));
  }
}
</script>
```

---

## Step 11: Rubric Categories Page

Create `resources/js/Pages/Admin/Rubric/Categories.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto px-4 py-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Rubric Categories</h1>
        <p class="text-gray-600">Configure the grading categories and weights</p>
        <div class="mt-2 flex gap-4 text-sm">
          <Link href="/admin/rubric/categories" class="text-blue-600 font-medium">Categories</Link>
          <Link href="/admin/rubric/checkpoints" class="text-gray-500 hover:text-gray-700">Checkpoints</Link>
        </div>
      </div>

      <!-- Total Weight Warning -->
      <div 
        v-if="totalWeight !== 100"
        class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6"
      >
        <p class="text-yellow-800">
          ⚠️ Category weights total <strong>{{ totalWeight }}%</strong>. They should total 100%.
        </p>
      </div>

      <!-- Categories List -->
      <div class="space-y-4">
        <div 
          v-for="category in categories" 
          :key="category.id"
          class="bg-white rounded-lg shadow"
        >
          <div class="p-4">
            <div class="flex items-start justify-between mb-3">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <input
                    v-model="editForms[category.id].name"
                    class="text-lg font-medium border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none"
                  />
                  <span 
                    :class="[
                      'text-xs px-2 py-0.5 rounded',
                      category.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'
                    ]"
                  >
                    {{ category.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600">Weight:</label>
                <input
                  type="number"
                  v-model.number="editForms[category.id].weight"
                  class="w-16 border rounded px-2 py-1 text-center"
                  min="1"
                  max="100"
                />
                <span class="text-sm text-gray-600">%</span>
              </div>
            </div>

            <div class="mb-3">
              <label class="block text-xs text-gray-500 mb-1">Description</label>
              <textarea
                v-model="editForms[category.id].description"
                rows="2"
                class="w-full border rounded px-3 py-2 text-sm"
                placeholder="What should managers look for in this category?"
              />
            </div>

            <div class="mb-3">
              <label class="block text-xs text-gray-500 mb-1">Training Reference</label>
              <textarea
                v-model="editForms[category.id].training_reference"
                rows="2"
                class="w-full border rounded px-3 py-2 text-sm"
                placeholder="Quote or reference from training materials..."
              />
            </div>

            <div class="flex items-center justify-between">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="editForms[category.id].is_active"
                  class="rounded"
                />
                <span class="text-sm text-gray-600">Active</span>
              </label>
              <button
                @click="saveCategory(category.id)"
                :disabled="saving === category.id"
                class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 disabled:opacity-50"
              >
                {{ saving === category.id ? 'Saving...' : 'Save' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <p class="mt-6 text-sm text-gray-500">
        Note: Categories cannot be added or deleted to maintain data integrity. Contact support if you need structural changes.
      </p>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  categories: Array,
});

const saving = ref(null);

// Initialize edit forms for each category
const editForms = ref({});
props.categories.forEach(cat => {
  editForms.value[cat.id] = {
    name: cat.name,
    description: cat.description || '',
    weight: cat.weight,
    training_reference: cat.training_reference || '',
    is_active: cat.is_active,
  };
});

const totalWeight = computed(() => {
  return Object.values(editForms.value).reduce((sum, form) => sum + (form.weight || 0), 0);
});

function saveCategory(categoryId) {
  saving.value = categoryId;
  router.patch(route('admin.rubric.categories.update', categoryId), editForms.value[categoryId], {
    preserveScroll: true,
    onFinish: () => saving.value = null,
  });
}
</script>
```

---

## Step 12: Rubric Checkpoints Page

Create `resources/js/Pages/Admin/Rubric/Checkpoints.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto px-4 py-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Rubric Checkpoints</h1>
        <p class="text-gray-600">Configure the binary checkpoints (should observe / should NOT observe)</p>
        <div class="mt-2 flex gap-4 text-sm">
          <Link href="/admin/rubric/categories" class="text-gray-500 hover:text-gray-700">Categories</Link>
          <Link href="/admin/rubric/checkpoints" class="text-blue-600 font-medium">Checkpoints</Link>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Positive Checkpoints -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <h2 class="font-medium text-green-700">✓ Should Observe</h2>
            <button
              @click="showAddModal('positive')"
              class="text-sm text-blue-600 hover:text-blue-800"
            >
              + Add
            </button>
          </div>
          <div class="bg-white rounded-lg shadow divide-y">
            <div 
              v-for="cp in positiveCheckpoints" 
              :key="cp.id"
              class="p-3"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1 pr-4">
                  <p class="text-sm text-gray-900">{{ cp.label }}</p>
                  <p v-if="cp.description" class="text-xs text-gray-500 mt-1">{{ cp.description }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <span 
                    :class="[
                      'text-xs px-1.5 py-0.5 rounded',
                      cp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                    ]"
                  >
                    {{ cp.is_active ? 'On' : 'Off' }}
                  </span>
                  <button @click="editCheckpoint(cp)" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
            <div v-if="positiveCheckpoints.length === 0" class="p-4 text-center text-gray-500 text-sm">
              No positive checkpoints.
            </div>
          </div>
        </div>

        <!-- Negative Checkpoints -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <h2 class="font-medium text-red-700">✗ Should NOT Observe</h2>
            <button
              @click="showAddModal('negative')"
              class="text-sm text-blue-600 hover:text-blue-800"
            >
              + Add
            </button>
          </div>
          <div class="bg-white rounded-lg shadow divide-y">
            <div 
              v-for="cp in negativeCheckpoints" 
              :key="cp.id"
              class="p-3"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1 pr-4">
                  <p class="text-sm text-gray-900">{{ cp.label }}</p>
                  <p v-if="cp.description" class="text-xs text-gray-500 mt-1">{{ cp.description }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <span 
                    :class="[
                      'text-xs px-1.5 py-0.5 rounded',
                      cp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                    ]"
                  >
                    {{ cp.is_active ? 'On' : 'Off' }}
                  </span>
                  <button @click="editCheckpoint(cp)" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
            <div v-if="negativeCheckpoints.length === 0" class="p-4 text-center text-gray-500 text-sm">
              No negative checkpoints.
            </div>
          </div>
        </div>
      </div>

      <!-- Add/Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
          <h3 class="text-lg font-medium mb-4">
            {{ editingCheckpoint ? 'Edit Checkpoint' : 'Add Checkpoint' }}
          </h3>
          
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
              <textarea
                v-model="modalForm.label"
                rows="2"
                class="w-full border rounded px-3 py-2 text-sm"
                placeholder="What behavior should be observed?"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
              <textarea
                v-model="modalForm.description"
                rows="2"
                class="w-full border rounded px-3 py-2 text-sm"
                placeholder="Additional context..."
              />
            </div>
            <div v-if="editingCheckpoint">
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="modalForm.is_active" class="rounded" />
                <span class="text-sm">Active</span>
              </label>
            </div>
          </div>

          <div class="mt-6 flex justify-between">
            <button
              v-if="editingCheckpoint"
              @click="deleteCheckpoint"
              class="text-red-600 hover:text-red-800 text-sm"
            >
              Delete
            </button>
            <div class="flex gap-3 ml-auto">
              <button @click="closeModal" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded">
                Cancel
              </button>
              <button
                @click="saveCheckpoint"
                :disabled="!modalForm.label"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
              >
                Save
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  checkpoints: Array,
});

const showModal = ref(false);
const editingCheckpoint = ref(null);
const modalType = ref('positive');
const modalForm = ref({
  label: '',
  description: '',
  is_active: true,
});

const positiveCheckpoints = computed(() => 
  props.checkpoints.filter(cp => cp.type === 'positive')
);

const negativeCheckpoints = computed(() => 
  props.checkpoints.filter(cp => cp.type === 'negative')
);

function showAddModal(type) {
  modalType.value = type;
  editingCheckpoint.value = null;
  modalForm.value = { label: '', description: '', is_active: true };
  showModal.value = true;
}

function editCheckpoint(cp) {
  editingCheckpoint.value = cp;
  modalType.value = cp.type;
  modalForm.value = {
    label: cp.label,
    description: cp.description || '',
    is_active: cp.is_active,
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingCheckpoint.value = null;
}

function saveCheckpoint() {
  if (editingCheckpoint.value) {
    router.patch(route('admin.rubric.checkpoints.update', editingCheckpoint.value.id), modalForm.value, {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    });
  } else {
    router.post(route('admin.rubric.checkpoints.store'), {
      ...modalForm.value,
      type: modalType.value,
    }, {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    });
  }
}

function deleteCheckpoint() {
  if (confirm('Delete this checkpoint?')) {
    router.delete(route('admin.rubric.checkpoints.destroy', editingCheckpoint.value.id), {
      preserveScroll: true,
      onSuccess: () => closeModal(),
    });
  }
}
</script>
```

---

## Step 13: Objection Types Page

Create `resources/js/Pages/Admin/ObjectionTypes/Index.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto px-4 py-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Objection Types</h1>
        <p class="text-gray-600">Configure the list of objection types managers can flag</p>
      </div>

      <!-- Add Form -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form @submit.prevent="addType" class="flex gap-3">
          <input
            v-model="newTypeName"
            type="text"
            class="flex-1 border rounded px-3 py-2"
            placeholder="New objection type..."
          />
          <button
            type="submit"
            :disabled="!newTypeName.trim()"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
          >
            Add
          </button>
        </form>
      </div>

      <!-- Types List -->
      <div class="bg-white rounded-lg shadow divide-y">
        <div 
          v-for="type in types" 
          :key="type.id"
          class="p-4 flex items-center justify-between"
        >
          <div class="flex items-center gap-3">
            <span 
              :class="[
                'w-2 h-2 rounded-full',
                type.is_active ? 'bg-green-500' : 'bg-gray-300'
              ]"
            />
            <span v-if="editingId !== type.id" class="text-gray-900">{{ type.name }}</span>
            <input
              v-else
              v-model="editingName"
              class="border rounded px-2 py-1"
              @keyup.enter="saveEdit(type.id)"
              @keyup.escape="cancelEdit"
            />
          </div>
          
          <div class="flex items-center gap-2">
            <template v-if="editingId !== type.id">
              <button
                @click="toggleActive(type)"
                :class="[
                  'text-xs px-2 py-1 rounded',
                  type.is_active 
                    ? 'bg-gray-100 text-gray-600 hover:bg-gray-200' 
                    : 'bg-green-100 text-green-700 hover:bg-green-200'
                ]"
              >
                {{ type.is_active ? 'Deactivate' : 'Activate' }}
              </button>
              <button
                @click="startEdit(type)"
                class="text-gray-400 hover:text-gray-600"
              >
                Edit
              </button>
              <button
                @click="deleteType(type)"
                class="text-red-400 hover:text-red-600"
              >
                Delete
              </button>
            </template>
            <template v-else>
              <button @click="saveEdit(type.id)" class="text-green-600 hover:text-green-800 text-sm">
                Save
              </button>
              <button @click="cancelEdit" class="text-gray-500 hover:text-gray-700 text-sm">
                Cancel
              </button>
            </template>
          </div>
        </div>

        <div v-if="types.length === 0" class="p-8 text-center text-gray-500">
          No objection types defined.
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  types: Array,
});

const newTypeName = ref('');
const editingId = ref(null);
const editingName = ref('');

function addType() {
  router.post(route('admin.objection-types.store'), { name: newTypeName.value }, {
    preserveScroll: true,
    onSuccess: () => newTypeName.value = '',
  });
}

function startEdit(type) {
  editingId.value = type.id;
  editingName.value = type.name;
}

function cancelEdit() {
  editingId.value = null;
  editingName.value = '';
}

function saveEdit(id) {
  router.patch(route('admin.objection-types.update', id), { name: editingName.value }, {
    preserveScroll: true,
    onSuccess: () => cancelEdit(),
  });
}

function toggleActive(type) {
  router.patch(route('admin.objection-types.update', type.id), { is_active: !type.is_active }, {
    preserveScroll: true,
  });
}

function deleteType(type) {
  if (confirm(`Delete "${type.name}"? This cannot be undone.`)) {
    router.delete(route('admin.objection-types.destroy', type.id), {
      preserveScroll: true,
    });
  }
}
</script>
```

---

## Step 14: Settings Page

Create `resources/js/Pages/Admin/Settings/Index.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto px-4 py-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
        <p class="text-gray-600">Global configuration options</p>
      </div>

      <form @submit.prevent="save" class="space-y-6">
        <!-- Grading Quality -->
        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Grading Quality Thresholds</h2>
          <p class="text-sm text-gray-600 mb-4">
            Flag grades where playback time is below these percentages of call duration.
          </p>
          
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Flag Threshold (%)
              </label>
              <input
                type="number"
                v-model.number="form.grading_quality_flag_threshold"
                class="w-full border rounded px-3 py-2"
                min="0"
                max="100"
              />
              <p class="text-xs text-gray-500 mt-1">Grades below this are flagged as suspicious.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Warning Threshold (%)
              </label>
              <input
                type="number"
                v-model.number="form.grading_quality_warn_threshold"
                class="w-full border rounded px-3 py-2"
                min="0"
                max="100"
              />
              <p class="text-xs text-gray-500 mt-1">Grades below this get a soft warning.</p>
            </div>
          </div>
        </div>

        <!-- Call Sync -->
        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Call Sync</h2>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Sync Window (days)
            </label>
            <input
              type="number"
              v-model.number="form.call_sync_days"
              class="w-32 border rounded px-3 py-2"
              min="1"
              max="90"
            />
            <p class="text-xs text-gray-500 mt-1">How many days of calls to sync from CTM.</p>
          </div>

          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Short Call Threshold (seconds)
            </label>
            <input
              type="number"
              v-model.number="form.short_call_threshold"
              class="w-32 border rounded px-3 py-2"
              min="0"
              max="300"
            />
            <p class="text-xs text-gray-500 mt-1">Calls shorter than this show a warning before grading.</p>
          </div>
        </div>

        <!-- Grading Requirements -->
        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Grading Requirements</h2>
          
          <div class="space-y-3">
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.allow_partial_grades"
                class="rounded"
              />
              <div>
                <span class="text-sm font-medium text-gray-700">Allow Partial Grades</span>
                <p class="text-xs text-gray-500">Let managers submit without scoring all categories.</p>
              </div>
            </label>

            <label class="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.require_appointment_quality"
                class="rounded"
              />
              <div>
                <span class="text-sm font-medium text-gray-700">Require Appointment Quality</span>
                <p class="text-xs text-gray-500">Require solid/tentative/backed-in for booked calls.</p>
              </div>
            </label>

            <label class="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.require_no_appointment_reason"
                class="rounded"
              />
              <div>
                <span class="text-sm font-medium text-gray-700">Require No-Appointment Reason</span>
                <p class="text-xs text-gray-500">Require objection selection for failed calls.</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end">
          <button
            type="submit"
            :disabled="saving"
            class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
          >
            {{ saving ? 'Saving...' : 'Save Settings' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  settings: Object,
});

const saving = ref(false);
const form = ref({
  grading_quality_flag_threshold: parseInt(props.settings.grading_quality_flag_threshold) || 25,
  grading_quality_warn_threshold: parseInt(props.settings.grading_quality_warn_threshold) || 50,
  call_sync_days: parseInt(props.settings.call_sync_days) || 14,
  short_call_threshold: parseInt(props.settings.short_call_threshold) || 30,
  allow_partial_grades: props.settings.allow_partial_grades === '1' || props.settings.allow_partial_grades === true,
  require_appointment_quality: props.settings.require_appointment_quality === '1' || props.settings.require_appointment_quality === true,
  require_no_appointment_reason: props.settings.require_no_appointment_reason === '1' || props.settings.require_no_appointment_reason === true,
});

function save() {
  saving.value = true;
  router.post(route('admin.settings.update'), form.value, {
    preserveScroll: true,
    onFinish: () => saving.value = false,
  });
}
</script>
```

---

## Verification Checklist

After implementation:

**Admin Navigation:**
- [ ] Dark navbar with admin badge
- [ ] Offices, Users, Rubric, Objection Types, Settings links
- [ ] "Manager View" link switches to manager portal
- [ ] Settings only visible to system admin

**User Management:**
- [ ] Users list with filters (role, status, office, search)
- [ ] Stats cards show totals
- [ ] Invite user form works
- [ ] Email sent (or check magic_links table)
- [ ] Edit user works
- [ ] Can assign/remove offices
- [ ] Can change role (system admin only)
- [ ] Can deactivate user
- [ ] Resend invite works

**Rubric Categories:**
- [ ] All 8 categories displayed
- [ ] Can edit name, description, weight
- [ ] Can add training reference
- [ ] Can toggle active status
- [ ] Weight total warning shows if not 100%

**Rubric Checkpoints:**
- [ ] Positive and negative sections
- [ ] Can add new checkpoint
- [ ] Can edit existing checkpoint
- [ ] Can toggle active
- [ ] Can delete (if not in use)

**Objection Types:**
- [ ] List all types
- [ ] Add new type
- [ ] Edit existing type
- [ ] Toggle active
- [ ] Delete (with in-use protection)

**Settings (System Admin):**
- [ ] Grading quality thresholds editable
- [ ] Call sync days editable
- [ ] Short call threshold editable
- [ ] Toggle checkboxes work
- [ ] Save persists changes

---

## Test Flow

1. Log in as system admin
2. Visit `/admin/users` → see user list
3. Click "Invite User" → fill form → submit
4. Check `magic_links` table for token (or email)
5. Edit a user → change offices → save
6. Visit `/admin/rubric/categories` → edit weights
7. Visit `/admin/rubric/checkpoints` → add a new one
8. Visit `/admin/objection-types` → add a new type
9. Visit `/admin/settings` → change thresholds → save
10. Click "Manager View" → verify switch works

---

## Notes

- Site admins can manage users and rubric, but not system settings
- System admins have full access
- Users must have at least one office assigned
- Deactivating a user soft-deletes (keeps data, blocks login)
- Invitation links expire after 72 hours
- Settings are stored as key-value pairs for flexibility
