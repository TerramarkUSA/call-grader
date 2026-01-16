<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Users</h1>
                <p class="text-gray-600">Manage user accounts and permissions</p>
            </div>
            <a
                href="{{ route('admin.users.create') }}"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
            >
                + Invite User
            </a>
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

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
                <p class="text-sm text-gray-500">Total Users</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-2xl font-bold text-green-600">{{ $stats['active'] }}</p>
                <p class="text-sm text-gray-500">Active</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-2xl font-bold text-blue-600">{{ $stats['managers'] }}</p>
                <p class="text-sm text-gray-500">Managers</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-2xl font-bold text-purple-600">{{ $stats['admins'] }}</p>
                <p class="text-sm text-gray-500">Site Admins</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('admin.users.index') }}">
                <div class="flex gap-4 items-end flex-wrap">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Role</label>
                        <select name="role" class="border rounded px-3 py-2 text-sm">
                            <option value="">All Roles</option>
                            <option value="manager" {{ request('role') == 'manager' ? 'selected' : '' }}>Manager</option>
                            <option value="site_admin" {{ request('role') == 'site_admin' ? 'selected' : '' }}>Site Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Status</label>
                        <select name="status" class="border rounded px-3 py-2 text-sm">
                            <option value="">All</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Office</label>
                        <select name="office" class="border rounded px-3 py-2 text-sm">
                            <option value="">All Offices</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ request('office') == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm text-gray-600 mb-1">Search</label>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="Name or email..."
                        />
                    </div>
                    <button type="submit" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">
                        Apply
                    </button>
                    @if(request()->hasAny(['role', 'status', 'office', 'search']))
                        <a href="{{ route('admin.users.index') }}" class="text-gray-600 hover:text-gray-900 text-sm">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
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
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded {{ $user->role === 'site_admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $user->role === 'site_admin' ? 'Site Admin' : 'Manager' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                @if($user->accounts->count() > 0)
                                    {{ $user->accounts->pluck('name')->join(', ') }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.users.edit', $user) }}"
                                    class="text-blue-600 hover:text-blue-800 text-sm mr-3"
                                >
                                    Edit
                                </a>
                                @if(!$user->email_verified_at)
                                    <form method="POST" action="{{ route('admin.users.resend-invite', $user) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-orange-600 hover:text-orange-800 text-sm">
                                            Resend Invite
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            @if($users->hasPages())
                <div class="px-4 py-3 border-t flex justify-between items-center">
                    <p class="text-sm text-gray-600">
                        Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }}
                    </p>
                    <div>
                        {{ $users->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
