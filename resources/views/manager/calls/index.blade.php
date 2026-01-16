<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Queue - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('manager.partials.nav')

    <!-- Office Selector (if multiple accounts) -->
    @if($accounts->count() > 1)
        <div class="bg-white border-b">
            <div class="max-w-7xl mx-auto px-4 py-2">
                <form method="GET" action="{{ route('manager.calls.index') }}" class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Office:</label>
                    <select name="account_id" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm">
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ $selectedAccount->id == $account->id ? 'selected' : '' }}>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-2xl font-bold">{{ $stats['total_in_queue'] }}</div>
                <div class="text-gray-600 text-sm">Calls in Queue</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-2xl font-bold {{ $stats['expiring_soon'] > 0 ? 'text-orange-600' : '' }}">
                    {{ $stats['expiring_soon'] }}
                </div>
                <div class="text-gray-600 text-sm">Expiring Soon (10-14 days)</div>
            </div>
        </div>

        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('manager.calls.index') }}" class="flex flex-wrap gap-4 items-end">
                <input type="hidden" name="account_id" value="{{ $selectedAccount->id }}">

                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Caller name or number..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rep</label>
                    <select name="rep_id" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">All Reps</option>
                        @foreach($reps as $rep)
                            <option value="{{ $rep->id }}" {{ request('rep_id') == $rep->id ? 'selected' : '' }}>
                                {{ $rep->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                    <select name="project_id" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">All</option>
                        <option value="answered" {{ request('status') == 'answered' ? 'selected' : '' }}>Answered</option>
                        <option value="no_answer" {{ request('status') == 'no_answer' ? 'selected' : '' }}>No Answer</option>
                        <option value="busy" {{ request('status') == 'busy' ? 'selected' : '' }}>Busy</option>
                        <option value="voicemail" {{ request('status') == 'voicemail' ? 'selected' : '' }}>Voicemail</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                    <select name="date_filter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="14" {{ request('date_filter', '14') == '14' ? 'selected' : '' }}>Last 14 days</option>
                        <option value="7" {{ request('date_filter') == '7' ? 'selected' : '' }}>Last 7 days</option>
                        <option value="30" {{ request('date_filter') == '30' ? 'selected' : '' }}>Last 30 days</option>
                        <option value="all" {{ request('date_filter') == 'all' ? 'selected' : '' }}>All (up to 90 days)</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                        Filter
                    </button>
                    <a href="{{ route('manager.calls.index', ['account_id' => $selectedAccount->id]) }}" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 text-sm">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <form id="bulk-form" method="POST">
            @csrf
            <div class="bg-white rounded-lg shadow mb-4 p-3 flex items-center gap-4" id="bulk-actions" style="display: none;">
                <span class="text-sm text-gray-600"><span id="selected-count">0</span> selected</span>
                <button type="button" onclick="bulkIgnore()" class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                    Ignore Selected
                </button>
                <button type="button" onclick="bulkMarkBad('voicemail')" class="px-3 py-1 bg-orange-600 text-white rounded text-sm hover:bg-orange-700">
                    Mark as Voicemail
                </button>
                <button type="button" onclick="bulkMarkBad('no_conversation')" class="px-3 py-1 bg-orange-600 text-white rounded text-sm hover:bg-orange-700">
                    Mark as No Conversation
                </button>
            </div>
        </form>

        <!-- Calls Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            <input type="checkbox" id="select-all" onchange="toggleSelectAll()" class="rounded">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Caller</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rep</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($calls as $call)
                        <tr class="{{ $call->is_expiring ? 'bg-orange-50' : ($call->is_old ? 'bg-yellow-50' : '') }}">
                            <td class="px-4 py-3">
                                <input type="checkbox" name="call_ids[]" value="{{ $call->id }}" class="call-checkbox rounded" onchange="updateBulkActions()">
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm">{{ $call->called_at->format('M j, Y') }}</div>
                                <div class="text-xs text-gray-500">{{ $call->called_at->format('g:i A') }}</div>
                                @if($call->is_expiring)
                                    <span class="text-xs text-orange-600 font-medium">Expiring soon</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium">{{ $call->caller_name ?? 'Unknown' }}</div>
                                <div class="text-xs text-gray-500">{{ $call->caller_number }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $call->rep?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $call->project?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm {{ $call->talk_time < 30 ? 'text-orange-600' : '' }}">
                                {{ floor($call->talk_time / 60) }}:{{ str_pad($call->talk_time % 60, 2, '0', STR_PAD_LEFT) }}
                                @if($call->talk_time < 30)
                                    <span class="text-xs">⚠️</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $statusColors = [
                                        'answered' => 'bg-green-100 text-green-800',
                                        'no_answer' => 'bg-gray-100 text-gray-800',
                                        'busy' => 'bg-yellow-100 text-yellow-800',
                                        'voicemail' => 'bg-purple-100 text-purple-800',
                                    ];
                                @endphp
                                <span class="px-2 py-1 text-xs rounded {{ $statusColors[$call->dial_status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst(str_replace('_', ' ', $call->dial_status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm space-x-2">
                                @if($call->transcript)
                                    <a href="{{ route('manager.calls.grade', $call) }}" class="text-green-600 hover:text-green-900 font-medium">
                                        Grade
                                    </a>
                                @else
                                    <a href="{{ route('manager.calls.process', $call) }}" class="text-blue-600 hover:text-blue-900 font-medium">
                                        Process
                                    </a>
                                @endif
                                <button type="button" onclick="openIgnoreModal({{ $call->id }})" class="text-gray-600 hover:text-gray-900">
                                    Ignore
                                </button>
                                <button type="button" onclick="openBadCallModal({{ $call->id }})" class="text-orange-600 hover:text-orange-900">
                                    Bad Call
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                @if($showingSearch)
                                    No calls match your search criteria.
                                @else
                                    No calls in queue. Sync calls from CTM in the Offices settings.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $calls->withQueryString()->links() }}
        </div>
    </div>

    <!-- Ignore Modal -->
    <div id="ignore-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">Ignore Call</h3>
            <form id="ignore-form" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                    <input type="text" name="reason" placeholder="Why are you ignoring this call?" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeIgnoreModal()" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                        Ignore Call
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bad Call Modal -->
    <div id="bad-call-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">Mark as Bad Call</h3>
            <form id="bad-call-form" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">What's wrong with this call?</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="call_quality" value="voicemail" class="mr-2" required> Voicemail
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="call_quality" value="wrong_number" class="mr-2"> Wrong Number
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="call_quality" value="no_conversation" class="mr-2"> No Conversation (hang up)
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="call_quality" value="test" class="mr-2"> Test Call
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="call_quality" value="spam" class="mr-2"> Spam / Robo
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="call_quality" value="other" class="mr-2"> Other
                        </label>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Note (optional)</label>
                    <input type="text" name="call_quality_note" placeholder="Any additional details..." class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="delete_recording" value="1" checked class="mr-2">
                        <span class="text-sm text-gray-700">Delete recording to save storage</span>
                    </label>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeBadCallModal()" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700">
                        Mark as Bad
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.call-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.call-checkbox:checked');
            const bulkActions = document.getElementById('bulk-actions');
            const selectedCount = document.getElementById('selected-count');

            if (checkboxes.length > 0) {
                bulkActions.style.display = 'flex';
                selectedCount.textContent = checkboxes.length;
            } else {
                bulkActions.style.display = 'none';
            }
        }

        function getSelectedIds() {
            const checkboxes = document.querySelectorAll('.call-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        function bulkIgnore() {
            const ids = getSelectedIds();
            if (ids.length === 0) return;

            const form = document.getElementById('bulk-form');
            form.action = '{{ route("manager.calls.bulk-ignore") }}';

            // Add hidden inputs for call_ids
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'call_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            form.submit();
        }

        function bulkMarkBad(quality) {
            const ids = getSelectedIds();
            if (ids.length === 0) return;

            const form = document.getElementById('bulk-form');
            form.action = '{{ route("manager.calls.bulk-mark-bad") }}';

            // Add hidden inputs
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'call_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            const qualityInput = document.createElement('input');
            qualityInput.type = 'hidden';
            qualityInput.name = 'call_quality';
            qualityInput.value = quality;
            form.appendChild(qualityInput);

            form.submit();
        }

        function openIgnoreModal(callId) {
            const modal = document.getElementById('ignore-modal');
            const form = document.getElementById('ignore-form');
            form.action = `/manager/calls/${callId}/ignore`;
            modal.style.display = 'flex';
        }

        function closeIgnoreModal() {
            document.getElementById('ignore-modal').style.display = 'none';
        }

        function openBadCallModal(callId) {
            const modal = document.getElementById('bad-call-modal');
            const form = document.getElementById('bad-call-form');
            form.action = `/manager/calls/${callId}/mark-bad`;
            modal.style.display = 'flex';
        }

        function closeBadCallModal() {
            document.getElementById('bad-call-modal').style.display = 'none';
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeIgnoreModal();
                closeBadCallModal();
            }
        });
    </script>
</body>
</html>
