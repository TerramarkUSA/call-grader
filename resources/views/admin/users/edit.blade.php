<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-2xl mx-auto px-8 py-6">
        <div class="mb-6">
            <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors">
                &larr; Back to Users
            </a>
            <h1 class="text-xl font-semibold text-gray-900 mt-2">Edit User</h1>
        </div>

        @if (session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PATCH')

                <div class="space-y-4">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name', $user->name) }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                        />
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email', $user->email) }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                        />
                    </div>

                    <!-- Role -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select
                            name="role"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            {{ $canEditRole ? '' : 'disabled' }}
                        >
                            <option value="manager" {{ old('role', $user->role) == 'manager' ? 'selected' : '' }}>Manager</option>
                            <option value="site_admin" {{ old('role', $user->role) == 'site_admin' ? 'selected' : '' }}>Site Admin</option>
                        </select>
                        @if(!$canEditRole)
                            <input type="hidden" name="role" value="{{ $user->role }}" />
                            <p class="text-xs text-gray-500 mt-1">Only system admins can change roles.</p>
                        @else
                            <p class="text-xs text-gray-500 mt-1">
                                <span class="font-medium">Manager:</span> Can grade calls and view their own data.
                                <span class="font-medium">Site Admin:</span> Can manage users and view all data.
                            </p>
                        @endif
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm font-medium text-gray-700">Active</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1">Inactive users cannot log in.</p>
                    </div>

                    <!-- Offices -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Assigned Offices
                            <span id="officesRequired" class="text-red-500" style="{{ $user->role === 'site_admin' ? 'display:none' : '' }}">*</span>
                        </label>
                        <div class="border border-gray-200 rounded-lg p-3 max-h-48 overflow-y-auto space-y-2">
                            @foreach($accounts as $account)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="account_ids[]"
                                        value="{{ $account->id }}"
                                        {{ in_array($account->id, old('account_ids', $user->accounts->pluck('id')->toArray())) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm text-gray-700">{{ $account->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p id="officesHelp" class="text-xs text-gray-500 mt-1">
                            {{ $user->role === 'site_admin' ? 'Site Admins can see all offices. Assignment is optional.' : 'Managers can only see calls from assigned offices.' }}
                        </p>
                    </div>
                </div>

                <script>
                    const roleSelect = document.querySelector('select[name="role"]');
                    const officesRequired = document.getElementById('officesRequired');
                    const officesHelp = document.getElementById('officesHelp');

                    if (roleSelect && !roleSelect.disabled) {
                        roleSelect.addEventListener('change', function() {
                            const isSiteAdmin = this.value === 'site_admin';
                            officesRequired.style.display = isSiteAdmin ? 'none' : 'inline';
                            officesHelp.textContent = isSiteAdmin 
                                ? 'Site Admins can see all offices. Assignment is optional.'
                                : 'Managers can only see calls from assigned offices.';
                        });
                    }
                </script>

                <!-- Submit -->
                <div class="mt-6 flex justify-between">
                    <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700 transition-colors"
                            onclick="return confirm('{{ $user->is_active ? 'Deactivate' : 'Activate' }} this user?')"
                        >
                            {{ $user->is_active ? 'Deactivate User' : 'Activate User' }}
                        </button>
                    </form>
                    <div class="flex gap-3">
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="px-4 py-2 border border-gray-200 rounded-lg font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </a>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors"
                        >
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
