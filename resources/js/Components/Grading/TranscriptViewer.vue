<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <!-- Header -->
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Transcript</h2>
        <p class="text-sm text-gray-500">{{ utterances.length }} utterances · Click to add note</p>
      </div>
      <button
        @click="$emit('swap-speakers')"
        class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
        </svg>
        Swap Speakers
      </button>
    </div>

    <!-- Utterances List -->
    <div class="max-h-[calc(100vh-280px)] overflow-y-auto p-4 space-y-3">
      <div
        v-for="(utterance, index) in utterances"
        :key="index"
        @click="$emit('click-utterance', utterance, index)"
        :class="[
          'rounded-lg p-4 cursor-pointer transition-all duration-150',
          'border-l-4',
          utterance.speaker === 'Rep'
            ? 'bg-blue-50 border-l-blue-400 hover:bg-blue-100'
            : 'bg-gray-50 border-l-green-400 hover:bg-gray-100',
          isCurrentUtterance(utterance) ? 'ring-2 ring-blue-400' : ''
        ]"
      >
        <!-- Speaker & Timestamp -->
        <div class="flex items-center gap-2 mb-2">
          <span
            :class="[
              'text-xs font-semibold px-2 py-0.5 rounded-full',
              utterance.speaker === 'Rep'
                ? 'bg-blue-100 text-blue-700'
                : 'bg-green-100 text-green-700'
            ]"
          >
            {{ utterance.speaker }}
          </span>
          <span class="text-xs text-gray-400 flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ formatTime(utterance.start) }}
          </span>
        </div>

        <!-- Text -->
        <p class="text-sm text-gray-700 leading-relaxed">{{ utterance.text }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  utterances: { type: Array, default: () => [] },
  currentTime: { type: Number, default: 0 },
  speakersSwapped: { type: Boolean, default: false },
});

defineEmits(['click-utterance', 'swap-speakers']);

function formatTime(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

function isCurrentUtterance(utterance) {
  return props.currentTime >= utterance.start && props.currentTime < utterance.end;
}
</script>
