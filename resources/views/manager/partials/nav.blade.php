<nav class="bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-8">
        <div class="flex justify-between h-16">
            <!-- Left: Logo + Nav Links -->
            <div class="flex items-center">
                <!-- Logo/Home Link -->
                <a href="{{ route('manager.dashboard') }}" class="flex items-center px-2 text-xl font-semibold text-gray-900 hover:text-blue-600">
                    Call Grader
                </a>

                <!-- Nav Links (Desktop) -->
                <div class="hidden sm:ml-8 sm:flex sm:space-x-1">
                    <a
                        href="{{ route('manager.dashboard') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('manager.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                        </svg>
                        Dashboard
                    </a>

                    <a
                        href="{{ route('manager.performance.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('manager.performance.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                        Performance
                    </a>

                    <a
                        href="{{ route('manager.calls.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('manager.calls.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        Calls
                    </a>

                    <a
                        href="{{ route('manager.graded-calls') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('manager.graded-calls') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Graded
                    </a>

                    <a
                        href="{{ route('manager.notes-library') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('manager.notes-library') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Notes
                    </a>

                    <a
                        href="{{ route('manager.objections') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('manager.objections') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Objections
                    </a>

                    <!-- Reports Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button
                            @click="open = !open"
                            @click.away="open = false"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('manager.reports.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Reports
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute left-0 mt-1 w-48 bg-white rounded-xl shadow-lg py-1 z-50 ring-1 ring-black ring-opacity-5 border border-gray-200"
                            style="display: none;"
                        >
                            <a
                                href="{{ route('manager.reports.rep-performance') }}"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 {{ request()->routeIs('manager.reports.rep-performance') ? 'bg-gray-50 font-medium' : '' }}"
                            >
                                Rep Performance
                            </a>
                            <a
                                href="{{ route('manager.reports.category-breakdown') }}"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 {{ request()->routeIs('manager.reports.category-breakdown') ? 'bg-gray-50 font-medium' : '' }}"
                            >
                                Category Breakdown
                            </a>
                            <a
                                href="{{ route('manager.reports.objection-analysis') }}"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 {{ request()->routeIs('manager.reports.objection-analysis') ? 'bg-gray-50 font-medium' : '' }}"
                            >
                                Objection Analysis
                            </a>
                            <a
                                href="{{ route('manager.reports.grading-activity') }}"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 {{ request()->routeIs('manager.reports.grading-activity') ? 'bg-gray-50 font-medium' : '' }}"
                            >
                                My Grading Activity
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: User Menu -->
            <div class="flex items-center gap-4">
                @if(auth()->user()->role !== 'manager')
                    <a href="{{ route('admin.accounts.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                        Admin &rarr;
                    </a>
                @endif
                <button
                    onclick="document.getElementById('feedback-modal').classList.remove('hidden')"
                    class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition-colors"
                >
                    Feedback
                </button>
                <span class="text-sm text-gray-500">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">
                        Sign Out
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="sm:hidden border-t border-gray-100">
        <div class="flex flex-wrap justify-center gap-1 py-2 px-2">
            <a
                href="{{ route('manager.dashboard') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('manager.dashboard') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Dashboard
            </a>
            <a
                href="{{ route('manager.performance.index') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('manager.performance.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Performance
            </a>
            <a
                href="{{ route('manager.calls.index') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('manager.calls.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Calls
            </a>
            <a
                href="{{ route('manager.graded-calls') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('manager.graded-calls') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Graded
            </a>
            <a
                href="{{ route('manager.notes-library') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('manager.notes-library') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Notes
            </a>
            <a
                href="{{ route('manager.objections') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('manager.objections') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Objections
            </a>
        </div>
        <!-- Mobile Reports Links -->
        <div class="flex flex-wrap justify-center gap-1 py-2 px-2 border-t border-gray-100 bg-gray-50">
            <span class="text-xs text-gray-400 w-full text-center mb-1">Reports</span>
            <a
                href="{{ route('manager.reports.rep-performance') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('manager.reports.rep-performance') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Rep Performance
            </a>
            <a
                href="{{ route('manager.reports.category-breakdown') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('manager.reports.category-breakdown') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Categories
            </a>
            <a
                href="{{ route('manager.reports.objection-analysis') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('manager.reports.objection-analysis') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Objections
            </a>
            <a
                href="{{ route('manager.reports.grading-activity') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('manager.reports.grading-activity') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}"
            >
                My Activity
            </a>
        </div>
    </div>
</nav>
<!-- Feedback Modal -->
<div id="feedback-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/40 transition-opacity" onclick="document.getElementById('feedback-modal').classList.add('hidden')"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 z-10">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Send Feedback</h3>
                <button onclick="document.getElementById('feedback-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <p class="text-sm text-gray-500 mb-3">Bug reports, feature ideas, or anything on your mind.</p>
            <textarea
                id="feedback-message"
                rows="5"
                maxlength="2000"
                placeholder="What's on your mind?"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
            ></textarea>
            <div class="flex justify-between items-center mt-3">
                <span id="feedback-char-count" class="text-xs text-gray-400">0 / 2000</span>
                <div class="flex gap-2">
                    <button
                        onclick="document.getElementById('feedback-modal').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        id="feedback-send-btn"
                        onclick="sendFeedback()"
                        disabled
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        Send
                    </button>
                </div>
            </div>
            <div id="feedback-toast" class="hidden mt-3 px-3 py-2 rounded-lg text-sm font-medium text-center"></div>
        </div>
    </div>
</div>

<script>
    (function() {
        const textarea = document.getElementById('feedback-message');
        const charCount = document.getElementById('feedback-char-count');
        const sendBtn = document.getElementById('feedback-send-btn');

        textarea.addEventListener('input', function() {
            charCount.textContent = this.value.length + ' / 2000';
            sendBtn.disabled = this.value.trim().length === 0;
        });
    })();

    async function sendFeedback() {
        const textarea = document.getElementById('feedback-message');
        const sendBtn = document.getElementById('feedback-send-btn');
        const toast = document.getElementById('feedback-toast');
        const message = textarea.value.trim();

        if (!message) return;

        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending...';

        try {
            const response = await fetch('{{ route("manager.feedback") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message: message }),
            });

            const data = await response.json();

            if (response.ok && data.success) {
                toast.textContent = 'Feedback sent — thanks!';
                toast.className = 'mt-3 px-3 py-2 rounded-lg text-sm font-medium text-center bg-green-50 text-green-700';
                toast.classList.remove('hidden');
                textarea.value = '';
                document.getElementById('feedback-char-count').textContent = '0 / 2000';
                setTimeout(() => {
                    document.getElementById('feedback-modal').classList.add('hidden');
                    toast.classList.add('hidden');
                }, 1500);
            } else {
                throw new Error(data.message || 'Failed to send');
            }
        } catch (error) {
            toast.textContent = 'Failed to send. Please try again.';
            toast.className = 'mt-3 px-3 py-2 rounded-lg text-sm font-medium text-center bg-red-50 text-red-700';
            toast.classList.remove('hidden');
        } finally {
            sendBtn.textContent = 'Send';
            sendBtn.disabled = textarea.value.trim().length === 0;
        }
    }
</script>

<!-- Alpine.js for dropdown -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
