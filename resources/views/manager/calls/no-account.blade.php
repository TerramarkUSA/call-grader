<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No Office Assigned - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow p-8 max-w-md text-center">
        <h1 class="text-2xl font-bold mb-4">No Office Assigned</h1>
        <p class="text-gray-600 mb-6">
            You haven't been assigned to any office yet. Please contact your administrator to get access.
        </p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-blue-600 hover:text-blue-800">
                Logout
            </button>
        </form>
    </div>
</body>
</html>
