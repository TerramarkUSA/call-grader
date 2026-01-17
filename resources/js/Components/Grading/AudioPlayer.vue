<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3">
    <div class="flex items-center gap-4">
      <!-- Skip Back -->
      <button
        @click="skip(-10)"
        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors"
        title="Back 10s"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.333 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"/>
        </svg>
      </button>

      <!-- Play/Pause -->
      <button
        @click="togglePlay"
        class="w-12 h-12 flex items-center justify-center bg-blue-600 text-white rounded-full hover:bg-blue-700 transition-colors shadow-sm"
      >
        <svg v-if="!isPlaying" class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M8 5v14l11-7z"/>
        </svg>
        <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
        </svg>
      </button>

      <!-- Skip Forward -->
      <button
        @click="skip(10)"
        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors"
        title="Forward 10s"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4zM19.933 12.8a1 1 0 000-1.6l-5.333-4A1 1 0 0013 8v8a1 1 0 001.6.8l5.333-4z"/>
        </svg>
      </button>

      <!-- Current Time -->
      <span class="text-sm text-gray-500 font-mono w-12">
        {{ formatTime(currentTime) }}
      </span>

      <!-- Progress Bar -->
      <div
        class="flex-1 h-2 bg-gray-200 rounded-full cursor-pointer relative"
        @click="seek"
        ref="progressBar"
      >
        <div
          class="h-full bg-blue-500 rounded-full transition-all duration-100"
          :style="{ width: `${progress}%` }"
        />
        <div
          class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-blue-600 rounded-full shadow-sm"
          :style="{ left: `calc(${progress}% - 6px)` }"
        />
      </div>

      <!-- Duration -->
      <span class="text-sm text-gray-500 font-mono w-12">
        {{ formatTime(duration) }}
      </span>

      <!-- Speed Control -->
      <select
        v-model="playbackSpeed"
        @change="updateSpeed"
        class="text-sm border border-gray-200 rounded-lg px-2 py-1 text-gray-600 bg-white"
      >
        <option value="0.5">0.5x</option>
        <option value="0.75">0.75x</option>
        <option value="1">1x</option>
        <option value="1.25">1.25x</option>
        <option value="1.5">1.5x</option>
        <option value="2">2x</option>
      </select>

      <!-- Volume -->
      <button
        @click="toggleMute"
        class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors"
      >
        <svg v-if="!isMuted && volume > 0" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
        </svg>
        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
        </svg>
      </button>
    </div>

    <!-- Hidden Audio Element -->
    <audio
      ref="audioElement"
      :src="audioUrl"
      @timeupdate="onTimeUpdate"
      @loadedmetadata="onLoadedMetadata"
      @ended="isPlaying = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  call: { type: Object, required: true },
  audioUrl: { type: String, required: true },
});

const emit = defineEmits(['time-update']);

const audioElement = ref(null);
const progressBar = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const playbackSpeed = ref('1');
const volume = ref(1);
const isMuted = ref(false);

const progress = computed(() => {
  return duration.value > 0 ? (currentTime.value / duration.value) * 100 : 0;
});

function formatTime(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function togglePlay() {
  if (isPlaying.value) {
    audioElement.value.pause();
  } else {
    audioElement.value.play();
  }
  isPlaying.value = !isPlaying.value;
}

function skip(seconds) {
  audioElement.value.currentTime = Math.max(0, Math.min(duration.value, currentTime.value + seconds));
}

function seek(event) {
  const rect = progressBar.value.getBoundingClientRect();
  const percent = (event.clientX - rect.left) / rect.width;
  audioElement.value.currentTime = percent * duration.value;
}

function updateSpeed() {
  audioElement.value.playbackRate = parseFloat(playbackSpeed.value);
}

function toggleMute() {
  isMuted.value = !isMuted.value;
  audioElement.value.muted = isMuted.value;
}

function onTimeUpdate() {
  currentTime.value = audioElement.value.currentTime;
  emit('time-update', currentTime.value);
}

function onLoadedMetadata() {
  duration.value = audioElement.value.duration;
}
</script>
