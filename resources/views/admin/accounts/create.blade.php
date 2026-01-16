<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Office - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-2xl mx-auto px-4 py-6">
        <div class="mb-6">
            <a href="{{ route('admin.accounts.index') }}" class="text-blue-600 hover:text-blue-800">
                &larr; Back to Offices
            </a>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold mb-6">Connect CTM Account</h2>

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-6">
                <h3 class="font-medium text-blue-900 mb-2">Where to find your CTM credentials:</h3>
                <ol class="text-sm text-blue-800 list-decimal list-inside space-y-1">
                    <li>Log into your CTM dashboard</li>
                    <li>Go to Settings &rarr; API</li>
                    <li>Copy your Account ID, API Key, and API Secret</li>
                </ol>
            </div>

            <form method="POST" action="{{ route('admin.accounts.store') }}">
                @csrf

                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Office Name</label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name') }}"
                        placeholder="e.g., Tampa Office"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">A friendly name to identify this office</p>
                </div>

                <div class="mb-4">
                    <label for="ctm_account_id" class="block text-sm font-medium text-gray-700 mb-1">CTM Account ID</label>
                    <input
                        type="text"
                        name="ctm_account_id"
                        id="ctm_account_id"
                        value="{{ old('ctm_account_id') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label for="ctm_api_key" class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                    <input
                        type="text"
                        name="ctm_api_key"
                        id="ctm_api_key"
                        value="{{ old('ctm_api_key') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                        required
                    >
                </div>

                <div class="mb-6">
                    <label for="ctm_api_secret" class="block text-sm font-medium text-gray-700 mb-1">API Secret</label>
                    <input
                        type="password"
                        name="ctm_api_secret"
                        id="ctm_api_secret"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">Your credentials are encrypted and stored securely</p>
                </div>

                <div class="flex justify-end gap-4">
                    <a href="{{ route('admin.accounts.index') }}" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        Connect & Test
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
