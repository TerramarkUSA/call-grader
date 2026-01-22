<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Queue - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('manager.partials.nav')

    <!-- Office Selector (if multiple accounts) -->
    @if($accounts->count() > 1)
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-8 py-2">
                <form method="GET" action="{{ route('manager.calls.index') }}" class="flex items-center gap-2">
                    <!-- Preserve current filters when changing account -->
                    <input type="hidden" name="date_filter" value="{{ $dateFilter }}">
                    @if($dateFilter === 'custom')
                        <input type="hidden" name="date_start" value="{{ request('date_start') }}">
                        <input type="hidden" name="date_end" value="{{ request('date_end') }}">
                    @endif
                    @if(request('display_status'))
                        <input type="hidden" name="display_status" value="{{ request('display_status') }}">
                    @endif
                    @if(request('grading_status'))
                        <input type="hidden" name="grading_status" value="{{ request('grading_status') }}">
                    @endif
                    @if(request('rep_id'))
                        <input type="hidden" name="rep_id" value="{{ request('rep_id') }}">
                    @endif
                    @if(request('project_id'))
                        <input type="hidden" name="project_id" value="{{ request('project_id') }}">
                    @endif
                    <label class="text-sm text-gray-500">Office:</label>
                    <select name="account_id" onchange="this.form.submit()" class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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

    <div class="max-w-7xl mx-auto px-8 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Call Queue</h1>
            <p class="text-sm text-gray-500">Calls waiting to be graded</p>
        </div>

        <!-- Stat Cards -->
        @php
            $currentStatus = request('display_status');
            $currentGradingStatus = request('grading_status');
            // Build base filter params to preserve across all links
            $filterParams = [
                'account_id' => $selectedAccount->id,
                'date_filter' => $dateFilter,
            ];
            if ($dateFilter === 'custom') {
                $filterParams['date_start'] = request('date_start');
                $filterParams['date_end'] = request('date_end');
            }
            if (request('rep_id')) {
                $filterParams['rep_id'] = request('rep_id');
            }
            if (request('project_id')) {
                $filterParams['project_id'] = request('project_id');
            }
            if (request('grading_status')) {
                $filterParams['grading_status'] = request('grading_status');
            }
        @endphp
        <div class="grid grid-cols-6 gap-4 mb-4">
            <!-- Total -->
            <a href="{{ route('manager.calls.index', $filterParams) }}"
               class="bg-white rounded-xl shadow-sm border-l-4 border-l-gray-400 p-4 cursor-pointer hover:shadow-md transition-shadow {{ !$currentStatus ? 'ring-2 ring-blue-500' : '' }}">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</div>
                <div class="text-sm text-gray-500">Total</div>
                <div class="text-xs text-gray-400">all calls</div>
            </a>

            <!-- Ready (Conversation) -->
            <a href="{{ route('manager.calls.index', array_merge($filterParams, ['display_status' => 'conversation'])) }}"
               class="bg-white rounded-xl shadow-sm border-l-4 border-l-green-500 p-4 cursor-pointer hover:shadow-md transition-shadow {{ $currentStatus === 'conversation' ? 'ring-2 ring-blue-500' : '' }}">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['conversation']) }}</div>
                <div class="text-sm text-gray-500">Ready</div>
                <div class="text-xs text-gray-400">> 60 sec</div>
            </a>

            <!-- Short Call -->
            <a href="{{ route('manager.calls.index', array_merge($filterParams, ['display_status' => 'short_call'])) }}"
               class="bg-white rounded-xl shadow-sm border-l-4 border-l-yellow-500 p-4 cursor-pointer hover:shadow-md transition-shadow {{ $currentStatus === 'short_call' ? 'ring-2 ring-blue-500' : '' }}">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['short_call']) }}</div>
                <div class="text-sm text-gray-500">Short</div>
                <div class="text-xs text-gray-400">10-60 sec</div>
            </a>

            <!-- No Conversation -->
            <a href="{{ route('manager.calls.index', array_merge($filterParams, ['display_status' => 'no_conversation'])) }}"
               class="bg-white rounded-xl shadow-sm border-l-4 border-l-red-500 p-4 cursor-pointer hover:shadow-md transition-shadow {{ $currentStatus === 'no_conversation' ? 'ring-2 ring-blue-500' : '' }}">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['no_conversation']) }}</div>
                <div class="text-sm text-gray-500">No Conv</div>
                <div class="text-xs text-gray-400">< 10 sec</div>
            </a>

            <!-- Abandoned -->
            <a href="{{ route('manager.calls.index', array_merge($filterParams, ['display_status' => 'abandoned'])) }}"
               class="bg-white rounded-xl shadow-sm border-l-4 border-l-gray-300 p-4 cursor-pointer hover:shadow-md transition-shadow {{ $currentStatus === 'abandoned' ? 'ring-2 ring-blue-500' : '' }}">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['abandoned']) }}</div>
                <div class="text-sm text-gray-500">Abandoned</div>
                <div class="text-xs text-gray-400">instant hangup</div>
            </a>

            <!-- Voicemail -->
            <a href="{{ route('manager.calls.index', array_merge($filterParams, ['display_status' => 'voicemail'])) }}"
               class="bg-white rounded-xl shadow-sm border-l-4 border-l-purple-500 p-4 cursor-pointer hover:shadow-md transition-shadow {{ $currentStatus === 'voicemail' ? 'ring-2 ring-blue-500' : '' }}">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['voicemail']) }}</div>
                <div class="text-sm text-gray-500">Voicemail</div>
                <div class="text-xs text-gray-400">left message</div>
            </a>
        </div>

        <!-- Summary Line -->
        @php
            $avgMinutes = floor($summaryStats['avg_duration'] / 60);
            $avgSeconds = $summaryStats['avg_duration'] % 60;
        @endphp
        <div class="text-sm text-gray-500 mb-4">
            <span class="font-medium text-gray-700">{{ $dateRangeLabel }}</span>
            <span class="mx-2">|</span>
            <span class="font-medium text-gray-700">{{ number_format($stats['total']) }} calls</span> in queue
            <span class="mx-2">|</span>
            <span class="font-medium text-gray-700">{{ number_format($stats['conversation']) }}</span> ready to grade
            <span class="mx-2">|</span>
            Avg Duration: <span class="font-medium text-gray-700">{{ $avgMinutes }}:{{ str_pad($avgSeconds, 2, '0', STR_PAD_LEFT) }}</span>
        </div>

        <!-- Quick Filter Chips -->
        <div class="flex gap-2 mb-6">
            <a href="{{ route('manager.calls.index', $filterParams) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ !$currentStatus ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                All ({{ number_format($stats['total']) }})
            </a>
            <a href="{{ route('manager.calls.index', array_merge($filterParams, ['display_status' => 'conversation'])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ $currentStatus === 'conversation' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>
                Ready ({{ number_format($stats['conversation']) }})
            </a>
            <a href="{{ route('manager.calls.index', array_merge($filterParams, ['display_status' => 'short_call'])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ $currentStatus === 'short_call' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <span class="inline-block w-2 h-2 rounded-full bg-yellow-500 mr-1"></span>
                Short ({{ number_format($stats['short_call']) }})
            </a>
            <a href="{{ route('manager.calls.index', array_merge($filterParams, ['display_status' => 'no_conversation'])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ $currentStatus === 'no_conversation' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1"></span>
                No Conv ({{ number_format($stats['no_conversation']) }})
            </a>
            <a href="{{ route('manager.calls.index', array_merge($filterParams, ['display_status' => 'abandoned'])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ $currentStatus === 'abandoned' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <span class="inline-block w-2 h-2 rounded-full bg-gray-400 mr-1"></span>
                Abandoned ({{ number_format($stats['abandoned']) }})
            </a>
            <a href="{{ route('manager.calls.index', array_merge($filterParams, ['display_status' => 'voicemail'])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ $currentStatus === 'voicemail' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <span class="inline-block w-2 h-2 rounded-full bg-purple-500 mr-1"></span>
                Voicemail ({{ number_format($stats['voicemail']) }})
            </a>
        </div>

        <!-- Grading Status Quick Filter Chips -->
        @php
            // Build params without grading_status for the chips
            $gradingFilterParams = array_diff_key($filterParams, ['grading_status' => '']);
        @endphp
        <div class="flex gap-2 mb-6">
            <span class="text-sm text-gray-500 py-1.5">Action:</span>
            <a href="{{ route('manager.calls.index', $gradingFilterParams) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ !$currentGradingStatus ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                All ({{ number_format($stats['total']) }})
            </a>
            <a href="{{ route('manager.calls.index', array_merge($gradingFilterParams, ['grading_status' => 'needs_processing'])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ $currentGradingStatus === 'needs_processing' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <span class="inline-block w-2 h-2 rounded-full bg-blue-600 mr-1"></span>
                Process ({{ number_format($gradingStats['needs_processing']) }})
            </a>
            <a href="{{ route('manager.calls.index', array_merge($gradingFilterParams, ['grading_status' => 'ready'])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ $currentGradingStatus === 'ready' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <span class="inline-block w-2 h-2 rounded-full bg-blue-400 mr-1"></span>
                Ready ({{ number_format($gradingStats['ready']) }})
            </a>
            <a href="{{ route('manager.calls.index', array_merge($gradingFilterParams, ['grading_status' => 'in_progress'])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ $currentGradingStatus === 'in_progress' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <span class="inline-block w-2 h-2 rounded-full bg-amber-500 mr-1"></span>
                In Progress ({{ number_format($gradingStats['in_progress']) }})
            </a>
            <a href="{{ route('manager.calls.index', array_merge($gradingFilterParams, ['grading_status' => 'graded'])) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors {{ $currentGradingStatus === 'graded' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>
                Graded ({{ number_format($gradingStats['graded']) }})
            </a>
        </div>

        @if (session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('manager.calls.index') }}" class="flex flex-wrap gap-4 items-end">
                <input type="hidden" name="account_id" value="{{ $selectedAccount->id }}">

                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm text-gray-500 mb-1">Search</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Caller name or number..."
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>

                <div>
                    <label class="block text-sm text-gray-500 mb-1">Rep</label>
                    <select name="rep_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Reps</option>
                        @foreach($reps as $rep)
                            <option value="{{ $rep->id }}" {{ request('rep_id') == $rep->id ? 'selected' : '' }}>
                                {{ $rep->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-500 mb-1">Project</label>
                    <select name="project_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-500 mb-1">Call Type</label>
                    <select name="display_status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Types</option>
                        @foreach($displayStatuses as $value => $label)
                            <option value="{{ $value }}" {{ request('display_status') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-500 mb-1">Action</label>
                    <select name="grading_status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Actions</option>
                        @foreach($gradingStatuses as $value => $label)
                            <option value="{{ $value }}" {{ request('grading_status') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-500 mb-1">Date Range</label>
                    <select name="date_filter" id="date-filter-select" onchange="handleDateFilterChange(this)" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="today" {{ (string)$dateFilter === 'today' ? 'selected' : '' }}>Today</option>
                        <option value="yesterday" {{ (string)$dateFilter === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                        <option value="7" {{ (string)$dateFilter === '7' ? 'selected' : '' }}>Last 7 days</option>
                        <option value="14" {{ (string)$dateFilter === '14' ? 'selected' : '' }}>Last 14 days</option>
                        <option value="30" {{ (string)$dateFilter === '30' ? 'selected' : '' }}>Last 30 days</option>
                        <option value="90" {{ (string)$dateFilter === '90' ? 'selected' : '' }}>Last 90 days</option>
                        <option value="custom" {{ (string)$dateFilter === 'custom' ? 'selected' : '' }}>Custom range</option>
                    </select>
                </div>

                <!-- Custom Date Range (shown when "Custom range" is selected) -->
                <div id="custom-date-range" class="flex gap-2 items-end" style="{{ $dateFilter == 'custom' ? '' : 'display: none;' }}">
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">From</label>
                        <input type="date" name="date_start" id="date-start" value="{{ request('date_start', $startDate->format('Y-m-d')) }}" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">To</label>
                        <input type="date" name="date_end" id="date-end" value="{{ request('date_end', $endDate->format('Y-m-d')) }}" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors text-sm">
                        Filter
                    </button>
                    <a href="{{ route('manager.calls.index', ['account_id' => $selectedAccount->id]) }}" class="px-4 py-2 border border-gray-300 text-gray-600 font-medium rounded-lg hover:bg-gray-50 transition-colors text-sm">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Calls Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Date/Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Caller</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rep</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Project</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Land Sale</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Appt</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Toured</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Contract</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Duration</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Call Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($calls as $call)
                        @php
                            // Format phone number
                            $phone = preg_replace('/[^0-9]/', '', $call->caller_number ?? '');
                            if (strlen($phone) === 11 && $phone[0] === '1') {
                                $formattedPhone = '+1 (' . substr($phone, 1, 3) . ') ' . substr($phone, 4, 3) . '-' . substr($phone, 7);
                            } elseif (strlen($phone) === 10) {
                                $formattedPhone = '+1 (' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
                            } else {
                                $formattedPhone = $call->caller_number;
                            }
                        @endphp
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $call->called_at->format('M j, Y') }}</div>
                                <div class="text-sm text-gray-500">{{ $call->called_at->format('g:i A') }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-gray-900">{{ $call->caller_name ?? 'Unknown' }}</div>
                                <div class="text-sm text-gray-500">{{ $formattedPhone }}</div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if($call->rep?->name)
                                    <span class="text-sm text-gray-700">{{ $call->rep->name }}</span>
                                @else
                                    <span class="text-sm text-gray-400 italic">Unassigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if($call->project?->name)
                                    <span class="text-sm text-gray-700">{{ $call->project->name }}</span>
                                @else
                                    <span class="text-sm text-gray-400 italic">Unassigned</span>
                                @endif
                            </td>
                            <!-- Land Sale -->
                            <td class="px-4 py-4 text-sm text-gray-600 whitespace-nowrap">
                                {{ $call->sf_land_sale ?? '—' }}
                            </td>
                            <!-- Appointment -->
                            <td class="px-4 py-4 text-center whitespace-nowrap">
                                @if($call->sf_appointment_made === true)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <!-- Toured -->
                            <td class="px-4 py-4 text-center whitespace-nowrap">
                                @if($call->sf_toured_property === true)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <!-- Contract -->
                            <td class="px-4 py-4 text-center whitespace-nowrap">
                                @if($call->sf_opportunity_created === true)
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if($call->talk_time < 30)
                                    <span class="text-sm font-medium text-red-500">
                                        {{ floor($call->talk_time / 60) }}:{{ str_pad($call->talk_time % 60, 2, '0', STR_PAD_LEFT) }}
                                        <svg class="inline-block w-4 h-4 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </span>
                                @else
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ floor($call->talk_time / 60) }}:{{ str_pad($call->talk_time % 60, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center justify-center min-w-[100px] px-2.5 py-1 rounded-full text-xs font-medium {{ $call->display_status_color }}">
                                    {{ $call->display_status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @php $gradingStatus = $call->grading_status; @endphp
                                @if($gradingStatus === 'needs_processing')
                                    <a href="{{ route('manager.calls.process', $call) }}" class="inline-flex items-center justify-center min-w-[90px] py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                        Process
                                    </a>
                                @elseif($gradingStatus === 'ready')
                                    <a href="{{ route('manager.calls.grade', $call) }}" class="inline-flex items-center justify-center min-w-[90px] py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-lg hover:bg-blue-200 transition-colors">
                                        Grade
                                    </a>
                                @elseif($gradingStatus === 'in_progress')
                                    <a href="{{ route('manager.calls.grade', $call) }}" class="inline-flex items-center justify-center min-w-[90px] py-1.5 bg-amber-100 text-amber-700 text-xs font-medium rounded-lg hover:bg-amber-200 transition-colors">
                                        In Progress
                                    </a>
                                @else
                                    <a href="{{ route('manager.calls.grade', $call) }}" class="inline-flex items-center justify-center min-w-[90px] py-1.5 bg-green-100 text-green-700 text-xs font-medium rounded-lg hover:bg-green-200 transition-colors">
                                        Graded ✓
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-12 text-center text-sm text-gray-500">
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
        @if($calls->hasPages())
            <div class="mt-6 flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    Showing {{ $calls->firstItem() }} to {{ $calls->lastItem() }} of {{ $calls->total() }} calls
                </div>
                <div class="flex items-center gap-1">
                    {{-- Previous --}}
                    @if($calls->onFirstPage())
                        <span class="px-3 py-2 text-sm text-gray-400 bg-white border border-gray-200 rounded-lg cursor-not-allowed">Previous</span>
                    @else
                        <a href="{{ $calls->previousPageUrl() }}" class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">Previous</a>
                    @endif

                    {{-- Page Numbers --}}
                    @foreach($calls->getUrlRange(max(1, $calls->currentPage() - 2), min($calls->lastPage(), $calls->currentPage() + 2)) as $page => $url)
                        @if($page == $calls->currentPage())
                            <span class="px-3 py-2 text-sm text-white bg-blue-600 rounded-lg">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">{{ $page }}</a>
                        @endif
                    @endforeach

                    {{-- Next --}}
                    @if($calls->hasMorePages())
                        <a href="{{ $calls->nextPageUrl() }}" class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">Next</a>
                    @else
                        <span class="px-3 py-2 text-sm text-gray-400 bg-white border border-gray-200 rounded-lg cursor-not-allowed">Next</span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <script>
        function handleDateFilterChange(select) {
            const customRange = document.getElementById('custom-date-range');
            const dateStart = document.getElementById('date-start');
            const dateEnd = document.getElementById('date-end');

            if (select.value === 'custom') {
                customRange.style.display = 'flex';
                // Set default dates if not already set
                if (!dateStart.value) {
                    const today = new Date();
                    const twoWeeksAgo = new Date(today);
                    twoWeeksAgo.setDate(today.getDate() - 14);
                    dateStart.value = twoWeeksAgo.toISOString().split('T')[0];
                    dateEnd.value = today.toISOString().split('T')[0];
                }
                // Don't auto-submit for custom - user needs to pick dates first
            } else {
                customRange.style.display = 'none';
                // Clear custom date values when switching away from custom
                dateStart.value = '';
                dateEnd.value = '';
                // Auto-submit form for non-custom date filters
                select.form.submit();
            }
        }
    </script>
</body>
</html>
