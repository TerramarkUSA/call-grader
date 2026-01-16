<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-2xl mx-auto px-4 py-6">
        <div class="mb-6">
            <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-gray-700 text-sm">
                &larr; Back to Users
            </a>
            <h1 class="text-2xl font-bold text-gray-900 mt-2">Edit User</h1>
        </div>

        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6">
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
                            class="w-full border rounded px-3 py-2"
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
                            class="w-full border rounded px-3 py-2"
                            required
                        />
                    </div>

                    <!-- Role -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select
                            name="role"
                            class="w-full border rounded px-3 py-2"
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
                                class="rounded border-gray-300"
                            />
                            <span class="text-sm font-medium text-gray-700">Active</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1">Inactive users cannot log in.</p>
                    </div>

                    <!-- Offices -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Offices</label>
                        <div class="border rounded p-3 max-h-48 overflow-y-auto space-y-2">
                            @foreach($accounts as $account)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="account_ids[]"
                                        value="{{ $account->id }}"
                                        {{ in_array($account->id, old('account_ids', $user->accounts->pluck('id')->toArray())) ? 'checked' : '' }}
                                        class="rounded border-gray-300"
                                    />
                                    <span class="text-sm">{{ $account->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Select at least one office.</p>
                    </div>
                </div>

                <!-- Submit -->
                <div class="mt-6 flex justify-between">
                    <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="px-4 py-2 text-red-600 hover:text-red-800"
                            onclick="return confirm('{{ $user->is_active ? 'Deactivate' : 'Activate' }} this user?')"
                        >
                            {{ $user->is_active ? 'Deactivate User' : 'Activate User' }}
                        </button>
                    </form>
                    <div class="flex gap-3">
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="px-4 py-2 border rounded text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </a>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
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
