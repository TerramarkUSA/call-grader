<template>
  <ManagerLayout>
    <div class="max-w-[1600px] mx-auto px-8 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Call Analytics</h1>
        <p class="text-gray-500">Understand your call patterns and performance</p>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Date Range</label>
            <select v-model="localFilters.date_range" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
              <option value="today">Today</option>
              <option value="yesterday">Yesterday</option>
              <option value="last_7_days">Last 7 Days</option>
              <option value="last_14_days">Last 14 Days</option>
              <option value="last_30_days">Last 30 Days</option>
              <option value="last_90_days">Last 90 Days</option>
              <option value="this_month">This Month</option>
              <option value="last_month">Last Month</option>
              <option value="custom">Custom</option>
            </select>
          </div>

          <div v-if="localFilters.date_range === 'custom'" class="flex gap-2">
            <input type="date" v-model="localFilters.start_date" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
            <input type="date" v-model="localFilters.end_date" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
          </div>

          <div v-if="filterOptions.accounts.length > 1">
            <label class="block text-xs text-gray-500 mb-1">Office</label>
            <select v-model="localFilters.account_id" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
              <option value="">All Offices</option>
              <option v-for="a in filterOptions.accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">Project</label>
            <select v-model="localFilters.project_id" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
              <option value="">All Projects</option>
              <option v-for="p in filterOptions.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">Rep</label>
            <select v-model="localFilters.rep_id" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
              <option value="">All Reps</option>
              <option v-for="r in filterOptions.reps" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>

          <div class="ml-auto">
            <button @click="exportCsv" class="border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
              Export CSV
            </button>
          </div>
        </div>
      </div>

      <!-- Row 1: Summary Stats -->
      <div class="grid grid-cols-5 gap-4 mb-6">
        <StatCard
          title="Total Calls"
          :value="summaryStats.total_calls.value"
          :change="summaryStats.total_calls.change"
        />
        <StatCard
          title="Connect Rate"
          :value="summaryStats.connect_rate.value + '%'"
          :change="summaryStats.connect_rate.change"
        />
        <StatCard
          title="Avg Talk Time"
          :value="summaryStats.avg_talk_time.formatted"
          :change="summaryStats.avg_talk_time.change"
        />
        <StatCard
          title="Appointments"
          :value="summaryStats.appointments.value"
          :change="summaryStats.appointments.change"
        />
        <StatCard
          title="Conversion Rate"
          :value="summaryStats.conversion_rate.value + '%'"
          :change="summaryStats.conversion_rate.change"
        />
      </div>

      <!-- Row 2: Volume Trend + Type Breakdown -->
      <div class="grid grid-cols-3 gap-6 mb-6">
        <div class="col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Call Volume Trend</h3>
          <VolumeTrendChart :data="volumeTrend" />
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Call Type Breakdown</h3>
          <TypeBreakdownChart :data="typeBreakdown" />
        </div>
      </div>

      <!-- Row 3: Peak Hours Heatmap -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-gray-900">Peak Call Hours</h3>
          <span v-if="peakHours.peak" class="text-sm text-gray-500">
            Busiest: <span class="font-medium text-gray-900">{{ peakHours.peak }}</span>
          </span>
        </div>
        <PeakHoursHeatmap :data="peakHours.data" :maxCount="peakHours.max_count" />
      </div>

      <!-- Row 4: By Project + By Rep -->
      <div class="grid grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Calls by Project</h3>
          <ProjectTable :data="byProject" />
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Calls by Rep</h3>
          <RepTable :data="byRep" />
        </div>
      </div>

      <!-- Row 5: Conversion Funnel -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="font-semibold text-gray-900 mb-4">Conversion Funnel</h3>
        <ConversionFunnel :data="conversionFunnel" />
      </div>

      <!-- Row 6: Outcomes + Objections -->
      <div class="grid grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Call Outcomes</h3>
          <p class="text-sm text-gray-500 mb-4">From graded calls only</p>
          <OutcomesChart :data="outcomes" />
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Top Objections</h3>
          <ObjectionsList :data="topObjections" />
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatCard from '@/Components/Analytics/StatCard.vue';
import VolumeTrendChart from '@/Components/Analytics/VolumeTrendChart.vue';
import TypeBreakdownChart from '@/Components/Analytics/TypeBreakdownChart.vue';
import PeakHoursHeatmap from '@/Components/Analytics/PeakHoursHeatmap.vue';
import ProjectTable from '@/Components/Analytics/ProjectTable.vue';
import RepTable from '@/Components/Analytics/RepTable.vue';
import ConversionFunnel from '@/Components/Analytics/ConversionFunnel.vue';
import OutcomesChart from '@/Components/Analytics/OutcomesChart.vue';
import ObjectionsList from '@/Components/Analytics/ObjectionsList.vue';

const props = defineProps({
  summaryStats: Object,
  volumeTrend: Array,
  typeBreakdown: Array,
  peakHours: Object,
  byProject: Array,
  byRep: Array,
  conversionFunnel: Array,
  outcomes: Array,
  topObjections: Array,
  filters: Object,
  filterOptions: Object,
});

const localFilters = reactive({ ...props.filters });

function applyFilters() {
  router.get(route('manager.reports.call-analytics'), localFilters, {
    preserveState: true,
    preserveScroll: true,
  });
}

function exportCsv() {
  window.location.href = route('manager.reports.call-analytics.export', localFilters);
}
</script>
