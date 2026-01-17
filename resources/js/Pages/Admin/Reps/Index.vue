<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto px-8 py-6">
      <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Reps Management</h1>
        <p class="text-sm text-gray-500">Manage sales reps for each office</p>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex gap-4">
          <div class="flex-1">
            <label class="block text-sm text-gray-500 mb-1">Office</label>
            <select
              v-model="selectedAccount"
              @change="applyFilters"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">All Offices</option>
              <option v-for="account in accounts" :key="account.id" :value="account.id">
                {{ account.name }}
              </option>
            </select>
          </div>
          <div class="flex-1">
            <label class="block text-sm text-gray-500 mb-1">Search</label>
            <input
              v-model="searchQuery"
              @input="debouncedSearch"
              type="text"
              placeholder="Search by name..."
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>
      </div>

      <!-- Add New Rep Form -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <h3 class="font-medium text-gray-900 mb-3">Add New Rep</h3>
        <form @submit.prevent="addRep">
          <div class="grid grid-cols-4 gap-4">
            <div>
              <select
                v-model="newRep.account_id"
                required
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Select Office...</option>
                <option v-for="account in accounts" :key="account.id" :value="account.id">
                  {{ account.name }}
                </option>
              </select>
            </div>
            <div>
              <input
                v-model="newRep.name"
                type="text"
                placeholder="Rep name"
                required
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <div>
              <input
                v-model="newRep.ctm_name"
                type="text"
                placeholder="CTM Name (optional)"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <div>
              <button
                type="submit"
                class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
              >
                Add Rep
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- Reps List -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Name</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Office</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">CTM Name</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Email</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rep in reps.data" :key="rep.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
              <td class="px-4 py-4">
                <input
                  v-model="rep.name"
                  type="text"
                  class="w-full border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none bg-transparent text-sm"
                />
              </td>
              <td class="px-4 py-4 text-sm text-gray-500">
                {{ rep.account?.name || '—' }}
              </td>
              <td class="px-4 py-4">
                <input
                  v-model="rep.ctm_name"
                  type="text"
                  :placeholder="rep.ctm_name ? '' : '—'"
                  class="w-full border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none bg-transparent text-sm text-gray-500"
                />
              </td>
              <td class="px-4 py-4">
                <input
                  v-model="rep.email"
                  type="email"
                  :placeholder="rep.email ? '' : '—'"
                  class="w-full border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none bg-transparent text-sm text-gray-500"
                />
              </td>
              <td class="px-4 py-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    v-model="rep.is_active"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span :class="[
                    'rounded-full px-2.5 py-0.5 text-xs font-medium',
                    rep.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                  ]">
                    {{ rep.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </label>
              </td>
              <td class="px-4 py-4 text-right">
                <button
                  @click="updateRep(rep)"
                  class="px-3 py-1 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 transition-colors"
                >
                  Save
                </button>
                <button
                  @click="deleteRep(rep)"
                  class="ml-2 px-3 py-1 text-red-600 text-xs font-medium hover:text-red-700 transition-colors"
                >
                  Delete
                </button>
              </td>
            </tr>
            <tr v-if="!reps.data?.length">
              <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                No reps found.
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="reps.links && reps.data?.length" class="px-4 py-3 border-t border-gray-100 flex justify-between items-center">
          <span class="text-sm text-gray-500">
            Showing {{ reps.from }} to {{ reps.to }} of {{ reps.total }} reps
          </span>
          <div class="flex gap-1">
            <a
              v-for="link in reps.links"
              :key="link.label"
              :href="link.url"
              v-html="link.label"
              :class="[
                'px-3 py-1 text-sm rounded-lg transition-colors',
                link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''
              ]"
            />
          </div>
        </div>
      </div>

      <p class="mt-6 text-sm text-gray-500">
        Note: Reps are automatically matched to incoming calls based on CTM Name when available.
      </p>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  reps: { type: Object, required: true },
  accounts: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});

const selectedAccount = ref(props.filters.account || '');
const searchQuery = ref(props.filters.search || '');

const newRep = ref({
  account_id: '',
  name: '',
  email: '',
  ctm_name: '',
});

let searchTimeout = null;

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 300);
}

function applyFilters() {
  router.get('/admin/reps', {
    account: selectedAccount.value || undefined,
    search: searchQuery.value || undefined,
  }, {
    preserveState: true,
    replace: true,
  });
}

function addRep() {
  router.post('/admin/reps', newRep.value, {
    onSuccess: () => {
      newRep.value = { account_id: '', name: '', email: '', ctm_name: '' };
    },
  });
}

function updateRep(rep) {
  router.patch(`/admin/reps/${rep.id}`, {
    name: rep.name,
    email: rep.email,
    ctm_name: rep.ctm_name,
    is_active: rep.is_active,
  });
}

function deleteRep(rep) {
  if (confirm('Are you sure you want to delete this rep?')) {
    router.delete(`/admin/reps/${rep.id}`);
  }
}
</script>
