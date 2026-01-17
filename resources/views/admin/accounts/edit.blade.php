<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Office - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-2xl mx-auto px-8 py-6">
        <div class="mb-6">
            <a href="{{ route('admin.accounts.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors">
                &larr; Back to Offices
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Edit Office: {{ $account->name }}</h2>

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.accounts.update', $account) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Office Name</label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name', $account->name) }}"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label for="ctm_account_id" class="block text-sm font-medium text-gray-700 mb-1">CTM Account ID</label>
                    <input
                        type="text"
                        name="ctm_account_id"
                        id="ctm_account_id"
                        value="{{ old('ctm_account_id', $account->ctm_account_id) }}"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label for="ctm_api_key" class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                    <input
                        type="text"
                        name="ctm_api_key"
                        id="ctm_api_key"
                        placeholder="Leave blank to keep current"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono"
                    >
                    <p class="text-xs text-gray-500 mt-1">Leave blank to keep existing credentials</p>
                </div>

                <div class="mb-6">
                    <label for="ctm_api_secret" class="block text-sm font-medium text-gray-700 mb-1">API Secret</label>
                    <input
                        type="password"
                        name="ctm_api_secret"
                        id="ctm_api_secret"
                        placeholder="Leave blank to keep current"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono"
                    >
                </div>

                <div class="flex justify-end gap-4">
                    <a href="{{ route('admin.accounts.index') }}" class="px-4 py-2 border border-gray-200 rounded-lg font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
