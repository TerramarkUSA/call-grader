<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-7xl mx-auto px-8 py-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Users</h1>
                <p class="text-sm text-gray-500">Manage user accounts and permissions</p>
            </div>
            <a
                href="{{ route('admin.users.create') }}"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors"
            >
                + Invite User
            </a>
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

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-2xl font-semibold text-gray-900">{{ $stats['total'] }}</p>
                <p class="text-sm text-gray-500">Total Users</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-2xl font-semibold text-green-600">{{ $stats['active'] }}</p>
                <p class="text-sm text-gray-500">Active</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-2xl font-semibold text-blue-600">{{ $stats['managers'] }}</p>
                <p class="text-sm text-gray-500">Managers</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-2xl font-semibold text-purple-600">{{ $stats['admins'] }}</p>
                <p class="text-sm text-gray-500">Site Admins</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('admin.users.index') }}">
                <div class="flex gap-4 items-end flex-wrap">
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">Role</label>
                        <select name="role" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Roles</option>
                            <option value="manager" {{ request('role') == 'manager' ? 'selected' : '' }}>Manager</option>
                            <option value="site_admin" {{ request('role') == 'site_admin' ? 'selected' : '' }}>Site Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">Status</label>
                        <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">Office</label>
                        <select name="office" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Offices</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ request('office') == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm text-gray-500 mb-1">Search</label>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Name or email..."
                        />
                    </div>
                    <button type="submit" class="bg-blue-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-blue-700 transition-colors">
                        Apply Filters
                    </button>
                    @if(request()->hasAny(['role', 'status', 'office', 'search']))
                        <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-700 transition-colors">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Offices</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-4 text-sm font-medium text-gray-900">{{ $user->name }}</td>
                            <td class="px-4 py-4 text-sm text-gray-500">{{ $user->email }}</td>
                            <td class="px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $user->role === 'site_admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $user->role === 'site_admin' ? 'Site Admin' : 'Manager' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-500">
                                @if($user->accounts->count() > 0)
                                    {{ $user->accounts->pluck('name')->join(', ') }}
                                @else
                                    <span class="text-gray-400 italic">Unassigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a
                                    href="{{ route('admin.users.edit', $user) }}"
                                    class="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors mr-3"
                                >
                                    Edit
                                </a>
                                @if(!$user->email_verified_at)
                                    <form method="POST" action="{{ route('admin.users.resend-invite', $user) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-sm font-medium text-yellow-600 hover:text-yellow-700 transition-colors">
                                            Resend Invite
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            @if($users->hasPages())
                <div class="px-4 py-3 border-t border-gray-100 flex justify-between items-center">
                    <p class="text-sm text-gray-500">
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
