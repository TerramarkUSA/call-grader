<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-4 py-3 border-b border-gray-100">
      <h2 class="text-lg font-semibold text-gray-900">Checkpoints</h2>
    </div>

    <div class="p-4 space-y-6">
      <!-- Should Observe -->
      <div>
        <h3 class="text-sm font-semibold text-green-700 mb-3 flex items-center gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          Should Observe
        </h3>
        <div class="space-y-3">
          <CheckpointRow
            v-for="cp in positiveCheckpoints"
            :key="cp.id"
            :checkpoint="cp"
            :value="checkpointValues[cp.id]"
            @update="(val) => $emit('update-checkpoint', cp.id, val)"
          />
        </div>
      </div>

      <!-- Should NOT Observe -->
      <div>
        <h3 class="text-sm font-semibold text-red-700 mb-3 flex items-center gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
          Should NOT Observe
        </h3>
        <div class="space-y-3">
          <CheckpointRow
            v-for="cp in negativeCheckpoints"
            :key="cp.id"
            :checkpoint="cp"
            :value="checkpointValues[cp.id]"
            @update="(val) => $emit('update-checkpoint', cp.id, val)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import CheckpointRow from './CheckpointRow.vue';

const props = defineProps({
  checkpoints: { type: Array, default: () => [] },
  checkpointValues: { type: Object, default: () => ({}) },
});

defineEmits(['update-checkpoint']);

const positiveCheckpoints = computed(() =>
  props.checkpoints.filter(cp => cp.type === 'positive')
);

const negativeCheckpoints = computed(() =>
  props.checkpoints.filter(cp => cp.type === 'negative')
);
</script>
