<template>
  <div class="space-y-3">
    <div v-for="(obj, index) in data" :key="obj.objection" class="flex items-center gap-3">
      <span class="w-6 h-6 rounded-full bg-gray-100 text-gray-500 text-xs font-medium flex items-center justify-center">
        {{ index + 1 }}
      </span>
      <span class="flex-1 text-sm text-gray-700">{{ obj.objection }}</span>
      <span class="text-sm font-medium text-gray-900">{{ obj.count }}</span>
    </div>
    <div v-if="data.length === 0" class="text-center py-8 text-gray-400 text-sm">
      No objections recorded yet
    </div>
  </div>
</template>

<script setup>
defineProps({
  data: Array,
});
</script>
