<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invite User - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-2xl mx-auto px-8 py-6">
        <div class="mb-6">
            <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors">
                &larr; Back to Users
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Invite Team Member</h2>

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name') }}"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select
                        name="role"
                        id="role"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required
                    >
                        <option value="manager" {{ old('role') === 'manager' ? 'selected' : '' }}>Manager</option>
                        @if (auth()->user()->role === 'system_admin')
                            <option value="site_admin" {{ old('role') === 'site_admin' ? 'selected' : '' }}>Site Admin</option>
                        @endif
                    </select>
                    <p id="roleDescription" class="text-xs text-gray-500 mt-1">Managers can grade calls and view reports. Site Admins can also manage users and settings.</p>
                </div>

                <div class="mb-6" id="officesSection">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Assign to Offices
                        <span id="officesRequired" class="text-red-500">*</span>
                    </label>
                    @if ($accounts->isEmpty())
                        <p id="noOfficesMsg" class="text-sm text-gray-500">No offices configured yet. <span id="noOfficesHint">Add a CTM connection first.</span></p>
                    @else
                        <div class="border border-gray-200 rounded-lg p-3 space-y-2">
                            @foreach ($accounts as $account)
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="account_ids[]"
                                        value="{{ $account->id }}"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        {{ in_array($account->id, old('account_ids', [])) ? 'checked' : '' }}
                                    >
                                    <span class="ml-2 text-sm text-gray-700">{{ $account->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <p id="officesHelp" class="text-xs text-gray-500 mt-1"></p>
                </div>

                <div class="flex justify-end gap-4">
                    <a href="{{ route('admin.users.index') }}" class="px-4 py-2 border border-gray-200 rounded-lg font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        id="submitBtn"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Send Invitation
                    </button>
                </div>

                <script>
                    const roleSelect = document.getElementById('role');
                    const officesRequired = document.getElementById('officesRequired');
                    const officesHelp = document.getElementById('officesHelp');
                    const noOfficesHint = document.getElementById('noOfficesHint');
                    const submitBtn = document.getElementById('submitBtn');
                    const hasOffices = {{ $accounts->isEmpty() ? 'false' : 'true' }};

                    function updateFormForRole() {
                        const isSiteAdmin = roleSelect.value === 'site_admin';
                        
                        // Update required indicator
                        officesRequired.style.display = isSiteAdmin ? 'none' : 'inline';
                        
                        // Update help text
                        officesHelp.textContent = isSiteAdmin 
                            ? 'Site Admins can see all offices. Assignment is optional.'
                            : 'Managers can only see calls from assigned offices.';
                        
                        // Update no offices hint
                        if (noOfficesHint) {
                            noOfficesHint.textContent = isSiteAdmin 
                                ? 'Site Admin will be able to create offices after logging in.'
                                : 'Add a CTM connection first.';
                        }
                        
                        // Enable/disable submit button
                        submitBtn.disabled = !hasOffices && !isSiteAdmin;
                    }

                    roleSelect.addEventListener('change', updateFormForRole);
                    updateFormForRole(); // Run on page load
                </script>
            </form>
        </div>
    </div>
</body>
</html>
