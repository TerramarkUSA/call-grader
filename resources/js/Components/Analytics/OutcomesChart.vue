<template>
  <div class="space-y-3">
    <div v-for="outcome in data" :key="outcome.outcome" class="flex items-center gap-3">
      <div class="w-32 text-sm text-gray-600">{{ outcome.outcome }}</div>
      <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
        <div
          class="h-full bg-blue-500 rounded-full transition-all duration-500"
          :style="{ width: `${getPercentage(outcome.count)}%` }"
        ></div>
      </div>
      <div class="w-12 text-sm text-gray-900 text-right">{{ outcome.count }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: Array,
});

const maxCount = computed(() => Math.max(...props.data.map(d => d.count), 1));

function getPercentage(count) {
  return (count / maxCount.value) * 100;
}
</script>
