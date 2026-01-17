<template>
  <div class="relative">
    <div class="flex items-end justify-between h-32">
      <div
        v-for="(stage, index) in data"
        :key="stage.stage"
        class="flex flex-col items-center flex-1"
      >
        <div
          class="w-full bg-blue-500 rounded-t transition-all duration-500"
          :style="{
            height: `${Math.max(stage.percentage, 5)}%`,
            opacity: 1 - (index * 0.15)
          }"
        ></div>
      </div>
    </div>
    <div class="flex justify-between mt-4">
      <div
        v-for="stage in data"
        :key="stage.stage"
        class="flex-1 text-center"
      >
        <p class="text-sm font-medium text-gray-900">{{ stage.count }}</p>
        <p class="text-xs text-gray-500">{{ stage.stage }}</p>
        <p class="text-xs text-gray-400">{{ stage.percentage }}%</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  data: Array,
});
</script>
