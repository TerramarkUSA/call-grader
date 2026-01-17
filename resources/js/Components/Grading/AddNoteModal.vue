<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900">Add Note</h3>
        <p v-if="utterance" class="text-sm text-gray-500 mt-1">
          At {{ formatTime(utterance.start) }} - {{ utterance.speaker }}
        </p>
      </div>

      <!-- Body -->
      <div class="px-6 py-4 space-y-4">
        <!-- Category -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
          <select
            v-model="noteData.rubric_category_id"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Select category...</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">
              {{ category.name }}
            </option>
          </select>
        </div>

        <!-- Is Objection -->
        <div>
          <label class="flex items-center gap-2">
            <input
              type="checkbox"
              v-model="noteData.is_objection"
              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span class="text-sm text-gray-700">This is an objection</span>
          </label>
        </div>

        <!-- Objection Type (if is_objection) -->
        <div v-if="noteData.is_objection">
          <label class="block text-sm font-medium text-gray-700 mb-1">Objection Type</label>
          <select
            v-model="noteData.objection_type_id"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Select objection type...</option>
            <option v-for="type in objectionTypes" :key="type.id" :value="type.id">
              {{ type.name }}
            </option>
          </select>
        </div>

        <!-- Note Text -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
          <textarea
            v-model="noteData.note_text"
            rows="4"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
            placeholder="Enter your note..."
          />
        </div>

        <!-- Transcript Quote -->
        <div v-if="utterance" class="bg-gray-50 rounded-lg p-3">
          <p class="text-xs text-gray-500 mb-1">Transcript quote:</p>
          <p class="text-sm text-gray-700 italic">"{{ utterance.text }}"</p>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
        >
          Cancel
        </button>
        <button
          @click="saveNote"
          class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          Save Note
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  utterance: { type: Object, default: null },
  categories: { type: Array, default: () => [] },
  objectionTypes: { type: Array, default: () => [] },
});

const emit = defineEmits(['save', 'close']);

const noteData = ref({
  rubric_category_id: '',
  is_objection: false,
  objection_type_id: '',
  note_text: '',
  transcript_quote: '',
  timestamp_start: 0,
  timestamp_end: 0,
});

// Update timestamps when utterance changes
watch(() => props.utterance, (newUtterance) => {
  if (newUtterance) {
    noteData.value.timestamp_start = newUtterance.start;
    noteData.value.timestamp_end = newUtterance.end;
    noteData.value.transcript_quote = newUtterance.text;
  }
}, { immediate: true });

function formatTime(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

function saveNote() {
  emit('save', {
    ...noteData.value,
    rubric_category_id: noteData.value.rubric_category_id || null,
    objection_type_id: noteData.value.is_objection ? noteData.value.objection_type_id : null,
  });
}
</script>
