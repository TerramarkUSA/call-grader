<nav class="bg-gray-900 text-white shadow-sm">
    <div class="max-w-7xl mx-auto px-8">
        <div class="flex justify-between h-16">
            <!-- Left: Logo + Nav Links -->
            <div class="flex items-center">
                <a href="{{ route('admin.accounts.index') }}" class="flex items-center px-2 text-xl font-semibold hover:text-blue-400 transition-colors">
                    Call Grader
                    <span class="ml-2 text-xs bg-blue-600 px-2 py-0.5 rounded-full font-medium">Admin</span>
                </a>

                <!-- Nav Links (Desktop) -->
                <div class="hidden sm:ml-8 sm:flex sm:space-x-1">
                    <a
                        href="{{ route('admin.accounts.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.accounts.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                    >
                        Offices
                    </a>

                    <a
                        href="{{ route('admin.users.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.users.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                    >
                        Users
                    </a>

                    <a
                        href="{{ route('admin.reps.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.reps.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                    >
                        Reps
                    </a>

                    <a
                        href="{{ route('admin.projects.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.projects.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                    >
                        Projects
                    </a>

                    <a
                        href="{{ route('admin.rubric.categories') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.rubric.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                    >
                        Rubric
                    </a>

                    <a
                        href="{{ route('admin.objection-types.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.objection-types.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                    >
                        Objections
                    </a>

                    <a
                        href="{{ route('admin.costs.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.costs.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                    >
                        Costs
                    </a>

                    <a
                        href="{{ route('admin.quality.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.quality.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                    >
                        Quality
                    </a>

                    <a
                        href="{{ route('admin.leaderboard.index') }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.leaderboard.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                    >
                        Leaderboard
                    </a>

                    @if(auth()->user()->role === 'system_admin')
                        <a
                            href="{{ route('admin.settings.index') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.settings.*') && !request()->routeIs('admin.salesforce.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                        >
                            Settings
                        </a>
                        <a
                            href="{{ route('admin.salesforce.index') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.salesforce.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}"
                        >
                            Salesforce
                        </a>
                    @endif
                </div>
            </div>

            <!-- Right: Switch to Manager + User Menu -->
            <div class="flex items-center gap-4">
                <a href="{{ route('manager.dashboard') }}" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">
                    &larr; Manager View
                </a>
                <span class="text-sm text-gray-400">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-gray-400 hover:text-white transition-colors">
                        Sign Out
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="sm:hidden border-t border-gray-700">
        <div class="flex flex-wrap justify-center gap-1 py-2 px-2">
            <a
                href="{{ route('admin.accounts.index') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.accounts.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
            >
                Offices
            </a>
            <a
                href="{{ route('admin.users.index') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.users.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
            >
                Users
            </a>
            <a
                href="{{ route('admin.reps.index') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.reps.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
            >
                Reps
            </a>
            <a
                href="{{ route('admin.projects.index') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.projects.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
            >
                Projects
            </a>
            <a
                href="{{ route('admin.rubric.categories') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.rubric.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
            >
                Rubric
            </a>
            <a
                href="{{ route('admin.objection-types.index') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.objection-types.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
            >
                Objections
            </a>
            <a
                href="{{ route('admin.costs.index') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.costs.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
            >
                Costs
            </a>
            <a
                href="{{ route('admin.quality.index') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.quality.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
            >
                Quality
            </a>
            <a
                href="{{ route('admin.leaderboard.index') }}"
                class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.leaderboard.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
            >
                Leaderboard
            </a>
            @if(auth()->user()->role === 'system_admin')
                <a
                    href="{{ route('admin.settings.index') }}"
                    class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.settings.*') && !request()->routeIs('admin.salesforce.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
                >
                    Settings
                </a>
                <a
                    href="{{ route('admin.salesforce.index') }}"
                    class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors {{ request()->routeIs('admin.salesforce.*') ? 'bg-gray-700 text-white' : 'text-gray-300' }}"
                >
                    Salesforce
                </a>
            @endif
        </div>
    </div>
</nav>
