<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">Notes ({{ notes.length }})</h2>
    </div>

    <div class="p-4">
      <!-- Notes List -->
      <div v-if="notes.length > 0" class="space-y-2 mb-4">
        <div
          v-for="note in notes"
          :key="note.id"
          @click="$emit('click-note', note)"
          class="p-2 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors"
        >
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs text-blue-600 font-medium">
              {{ formatTime(note.timestamp_start) }}
            </span>
            <span v-if="note.category" class="text-xs bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded">
              {{ note.category.name }}
            </span>
            <span v-if="note.is_objection" class="text-xs bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded">
              Objection
            </span>
          </div>
          <p class="text-sm text-gray-700 line-clamp-2">{{ note.note_text }}</p>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-4">
        <p class="text-sm text-gray-400">No notes yet</p>
        <p class="text-xs text-gray-400 mt-1">Click transcript to add</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  notes: { type: Array, default: () => [] },
  callId: { type: Number, required: true },
});

defineEmits(['add-note', 'click-note']);

function formatTime(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}
</script>
