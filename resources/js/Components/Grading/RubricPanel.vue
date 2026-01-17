<template>
  <div class="space-y-4">
    <!-- Call Details Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
      <h3 class="text-sm font-semibold text-gray-900 mb-3">Call Details</h3>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Rep</label>
          <select
            v-model="localCallDetails.rep_id"
            @change="emitCallDetails"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Select Rep...</option>
            <option v-for="rep in reps" :key="rep.id" :value="rep.id">
              {{ rep.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Project</label>
          <select
            v-model="localCallDetails.project_id"
            @change="emitCallDetails"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Select Project...</option>
            <option v-for="project in projects" :key="project.id" :value="project.id">
              {{ project.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Outcome</label>
          <select
            v-model="localCallDetails.outcome"
            @change="emitCallDetails"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Select Outcome...</option>
            <option value="appointment_set">Appointment Set</option>
            <option value="no_appointment">No Appointment</option>
            <option value="callback">Callback Scheduled</option>
            <option value="not_qualified">Not Qualified</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Rubric Scoring Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <!-- Header with Scale Legend -->
      <div class="px-4 py-3 border-b border-gray-100">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-lg font-semibold text-gray-900">Sales Call Evaluation</h2>
          <span class="text-sm text-gray-500">{{ scoredCount }}/{{ categories.length }} categories</span>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-gray-100 rounded-full h-1.5 mb-3">
          <div
            class="bg-blue-500 h-1.5 rounded-full transition-all duration-300"
            :style="{ width: `${(scoredCount / categories.length) * 100}%` }"
          />
        </div>

        <!-- Scale Legend -->
        <div class="flex items-center gap-4 text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
          <span class="font-medium text-gray-700">Scale:</span>
          <span><strong class="text-gray-900">1</strong> = Did not demonstrate</span>
          <span><strong class="text-gray-900">2</strong> = Partially demonstrated</span>
          <span><strong class="text-gray-900">3</strong> = Fully demonstrated</span>
          <span><strong class="text-gray-900">4</strong> = Exceeded expectations</span>
        </div>
      </div>

      <!-- Categories -->
      <div class="divide-y divide-gray-100">
        <CategoryScoreRow
          v-for="category in categories"
          :key="category.id"
          :category="category"
          :score="scores[category.id]"
          :is-expanded="expandedCategory === category.id"
          :is-next-to-score="nextToScore === category.id"
          @update-score="(score) => $emit('update-score', category.id, score)"
          @toggle-expand="toggleExpand(category.id)"
        />
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex gap-3">
      <button
        @click="$emit('save-draft')"
        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-colors"
      >
        Save Draft
      </button>
      <button
        @click="$emit('submit-grade')"
        class="flex-1 px-4 py-2.5 bg-blue-600 rounded-xl text-white font-medium hover:bg-blue-700 transition-colors"
      >
        Submit Grade
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import CategoryScoreRow from './CategoryScoreRow.vue';

const props = defineProps({
  categories: { type: Array, default: () => [] },
  scores: { type: Object, default: () => ({}) },
  call: { type: Object, required: true },
  reps: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
});

const emit = defineEmits(['update-score', 'update-call-details', 'save-draft', 'submit-grade']);

const expandedCategory = ref(null);

const localCallDetails = ref({
  rep_id: props.call.rep_id || '',
  project_id: props.call.project_id || '',
  outcome: props.call.outcome || '',
});

const scoredCount = computed(() => {
  return Object.values(props.scores).filter(s => s !== null && s !== undefined).length;
});

// Auto-expand next unscored category
const nextToScore = computed(() => {
  for (const cat of props.categories) {
    if (!props.scores[cat.id]) {
      return cat.id;
    }
  }
  return null;
});

// Auto-expand the next category to score
watch(nextToScore, (newVal) => {
  if (newVal && !expandedCategory.value) {
    expandedCategory.value = newVal;
  }
}, { immediate: true });

function toggleExpand(categoryId) {
  expandedCategory.value = expandedCategory.value === categoryId ? null : categoryId;
}

function emitCallDetails() {
  emit('update-call-details', localCallDetails.value);
}
</script>
