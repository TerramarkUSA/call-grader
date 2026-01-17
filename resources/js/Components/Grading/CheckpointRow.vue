<template>
  <div class="flex items-start gap-3">
    <!-- Label -->
    <p class="flex-1 text-sm text-gray-700 leading-snug">
      {{ checkpoint.label }}
    </p>

    <!-- Buttons -->
    <div class="flex items-center gap-1 flex-shrink-0">
      <button
        @click="$emit('update', null)"
        :class="[
          'w-8 h-8 rounded-lg text-xs font-medium transition-colors',
          value === null || value === undefined
            ? 'bg-gray-200 text-gray-600'
            : 'bg-gray-100 text-gray-400 hover:bg-gray-200'
        ]"
      >
        —
      </button>
      <button
        @click="$emit('update', true)"
        :class="[
          'w-8 h-8 rounded-lg text-xs font-medium transition-colors',
          value === true
            ? 'bg-green-500 text-white'
            : 'bg-gray-100 text-gray-400 hover:bg-gray-200'
        ]"
      >
        Y
      </button>
      <button
        @click="$emit('update', false)"
        :class="[
          'w-8 h-8 rounded-lg text-xs font-medium transition-colors',
          value === false
            ? 'bg-red-500 text-white'
            : 'bg-gray-100 text-gray-400 hover:bg-gray-200'
        ]"
      >
        N
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  checkpoint: { type: Object, required: true },
  value: { default: null },
});

defineEmits(['update']);
</script>
