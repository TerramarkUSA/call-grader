<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-gray-900 text-white">
      <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
          <!-- Left: Logo + Nav Links -->
          <div class="flex items-center">
            <a href="/admin/accounts" class="flex items-center px-2 text-xl font-bold hover:text-blue-400">
              Call Grader
              <span class="ml-2 text-xs bg-blue-600 px-2 py-0.5 rounded">Admin</span>
            </a>

            <!-- Nav Links (Desktop) -->
            <div class="hidden sm:ml-8 sm:flex sm:space-x-1">
              <a
                href="/admin/accounts"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                  isRoute('accounts') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800'
                ]"
              >
                Offices
              </a>
              <a
                href="/admin/users"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                  isRoute('users') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800'
                ]"
              >
                Users
              </a>
              <a
                href="/admin/reps"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                  isRoute('reps') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800'
                ]"
              >
                Reps
              </a>
              <a
                href="/admin/projects"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                  isRoute('projects') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800'
                ]"
              >
                Projects
              </a>
              <a
                href="/admin/rubric/categories"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                  isRoute('rubric') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800'
                ]"
              >
                Rubric
              </a>
              <a
                href="/admin/objection-types"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                  isRoute('objection') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800'
                ]"
              >
                Objections
              </a>
              <a
                href="/admin/costs"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                  isRoute('costs') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800'
                ]"
              >
                Costs
              </a>
              <a
                href="/admin/quality"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                  isRoute('quality') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800'
                ]"
              >
                Quality
              </a>
              <a
                href="/admin/leaderboard"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                  isRoute('leaderboard') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800'
                ]"
              >
                Leaderboard
              </a>
              <a
                v-if="isSystemAdmin"
                href="/admin/settings"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                  isRoute('settings') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:text-white hover:bg-gray-800'
                ]"
              >
                Settings
              </a>
            </div>
          </div>

          <!-- Right: Switch to Manager + User Menu -->
          <div class="flex items-center gap-4">
            <a href="/manager/dashboard" class="text-sm text-gray-300 hover:text-white">
              &larr; Manager View
            </a>
            <span class="text-gray-400">{{ $page.props.auth.user?.name }}</span>
            <a href="/logout" class="text-sm text-gray-400 hover:text-white">
              Sign Out
            </a>
          </div>
        </div>
      </div>
    </nav>

    <!-- Flash Messages -->
    <div v-if="$page.props.flash.success" class="max-w-7xl mx-auto px-4 mt-4">
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        {{ $page.props.flash.success }}
      </div>
    </div>
    <div v-if="$page.props.flash.error" class="max-w-7xl mx-auto px-4 mt-4">
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        {{ $page.props.flash.error }}
      </div>
    </div>

    <!-- Main Content -->
    <main>
      <slot />
    </main>
  </div>
</template>

<script setup>
import { usePage, computed } from '@inertiajs/vue3';

const page = usePage();

function isRoute(name) {
  const currentPath = window.location.pathname;
  return currentPath.includes(`/admin/${name}`);
}

const isSystemAdmin = computed(() => {
  return page.props.auth?.user?.role === 'system_admin';
});
</script>
