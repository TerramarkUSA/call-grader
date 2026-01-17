<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
    <p class="text-sm text-gray-500 mb-1">{{ title }}</p>
    <div class="flex items-end justify-between">
      <span class="text-2xl font-bold text-gray-900">{{ value }}</span>
      <span
        v-if="change !== null && change !== undefined"
        :class="[
          'text-sm font-medium flex items-center',
          change > 0 ? 'text-green-600' : change < 0 ? 'text-red-600' : 'text-gray-400'
        ]"
      >
        <svg v-if="change > 0" class="w-4 h-4 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
        </svg>
        <svg v-else-if="change < 0" class="w-4 h-4 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
        </svg>
        {{ Math.abs(change) }}%
      </span>
    </div>
    <p class="text-xs text-gray-400 mt-1">vs previous period</p>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  value: [String, Number],
  change: Number,
});
</script>
