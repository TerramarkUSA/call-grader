<template>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-100">
          <th class="text-left py-2 text-gray-500 font-medium">Project</th>
          <th class="text-right py-2 text-gray-500 font-medium">Calls</th>
          <th class="text-right py-2 text-gray-500 font-medium">Conv%</th>
          <th class="text-right py-2 text-gray-500 font-medium">Avg Dur</th>
          <th class="text-right py-2 text-gray-500 font-medium">Appts</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in data" :key="row.project" class="border-b border-gray-50 hover:bg-gray-50">
          <td class="py-3 font-medium text-gray-900">{{ row.project }}</td>
          <td class="py-3 text-right text-gray-600">{{ row.total_calls }}</td>
          <td class="py-3 text-right text-gray-600">{{ row.connect_rate }}%</td>
          <td class="py-3 text-right text-gray-600">{{ row.avg_duration }}</td>
          <td class="py-3 text-right text-gray-600">{{ row.appointments }}</td>
        </tr>
        <tr v-if="data.length === 0">
          <td colspan="5" class="py-8 text-center text-gray-400">No data available</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  data: Array,
});
</script>
