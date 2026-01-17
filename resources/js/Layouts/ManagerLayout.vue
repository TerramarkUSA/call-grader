<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
      <div class="max-w-[1600px] mx-auto px-8">
        <div class="flex justify-between h-16">
          <!-- Left: Logo + Nav Links -->
          <div class="flex items-center">
            <a :href="route('manager.dashboard')" class="flex items-center text-xl font-bold text-gray-900 hover:text-blue-600">
              Call Grader
            </a>

            <!-- Nav Links -->
            <div class="ml-8 flex space-x-1">
              <a
                :href="route('manager.dashboard')"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors',
                  isRoute('manager.dashboard') ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                ]"
              >
                Dashboard
              </a>
              <a
                :href="route('manager.calls.index')"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors',
                  isRoute('manager.calls.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                ]"
              >
                Call Queue
              </a>
              <a
                :href="route('manager.graded-calls')"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors',
                  isRoute('manager.graded-calls') ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                ]"
              >
                Graded Calls
              </a>
              <a
                :href="route('manager.notes-library')"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors',
                  isRoute('manager.notes-library') ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                ]"
              >
                Notes Library
              </a>
              <a
                :href="route('manager.reports.call-analytics')"
                :class="[
                  'inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors',
                  isRoute('manager.reports.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                ]"
              >
                Call Analytics
              </a>
            </div>
          </div>

          <!-- Right: Admin Link + User Menu -->
          <div class="flex items-center gap-4">
            <a
              v-if="$page.props.auth.user?.role === 'system_admin' || $page.props.auth.user?.role === 'site_admin'"
              :href="route('admin.accounts.index')"
              class="text-sm text-gray-500 hover:text-gray-700"
            >
              Admin &rarr;
            </a>
            <span class="text-gray-500 text-sm">{{ $page.props.auth.user?.name }}</span>
            <a :href="route('logout')" method="post" class="text-sm text-gray-400 hover:text-gray-600">
              Sign Out
            </a>
          </div>
        </div>
      </div>
    </nav>

    <!-- Flash Messages -->
    <div v-if="$page.props.flash.success" class="max-w-[1600px] mx-auto px-8 mt-4">
      <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
        {{ $page.props.flash.success }}
      </div>
    </div>
    <div v-if="$page.props.flash.error" class="max-w-[1600px] mx-auto px-8 mt-4">
      <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
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
import { usePage } from '@inertiajs/vue3';

const page = usePage();

function route(name, params) {
  // Simple route helper - in production you'd use ziggy
  const routes = {
    'manager.dashboard': '/manager/dashboard',
    'manager.calls.index': '/manager/calls',
    'manager.graded-calls': '/manager/graded-calls',
    'manager.notes-library': '/manager/notes-library',
    'manager.reports.call-analytics': '/manager/reports/call-analytics',
    'admin.accounts.index': '/admin/accounts',
    'logout': '/logout',
  };
  return routes[name] || '#';
}

function isRoute(pattern) {
  // Simple route check
  const currentPath = window.location.pathname;
  if (pattern.endsWith('*')) {
    const base = pattern.replace('.*', '').replace('manager.', '/manager/').replace('admin.', '/admin/');
    return currentPath.startsWith(base);
  }
  const path = pattern.replace('manager.', '/manager/').replace('admin.', '/admin/').replace('.', '/');
  return currentPath === path;
}
</script>
